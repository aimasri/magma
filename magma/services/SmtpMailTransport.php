<?php

declare(strict_types=1);

namespace Magma\services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Magma\contracts\ClockInterface;
use RuntimeException;
use Throwable;

/**
 * Title: SMTP Mail Transport Service
 *
 * Purpose:
 * - Implements `MailTransportInterface` to deliver transactional emails over RFC 5321 SMTP sockets.
 * - Negotiates implicit TLS (`ssl://`) or explicit `STARTTLS` with X.509 peer verification enabled by default, `AUTH LOGIN` authentication, CRLF header sanitization, and RFC 5321 dot-stuffing.
 *
 * Why / Why this design:
 * - Zero-Dependency Transport: Provides standards-compliant SMTP delivery (SendGrid, Amazon SES, Postmark, Google Workspace, Mailtrap) without pulling external Composer mail packages into Magma core.
 * - Security by Default: Enforces `verify_peer => true` and `verify_peer_name => true` on TLS streams unless explicitly overridden for isolated local sandboxes, and strips CR/LF characters from envelope addresses and headers to block SMTP command/header injection.
 * - Time Determinism: Injects `ClockInterface` for RFC 2822 `Date` header generation instead of calling global wall-clock functions.
 *
 * Teaching notes:
 * - Implements the full RFC 5321 state machine (`220` greeting -> `EHLO` -> `STARTTLS` -> `EHLO` -> `AUTH LOGIN` -> `MAIL FROM` -> `RCPT TO` -> `DATA` -> `QUIT`).
 * - Lines in the message body starting with `.` must be dot-stuffed (`..`) so the remote MTA does not prematurely interpret a body line as the `<CRLF>.<CRLF>` end-of-data sequence.
 */
class SmtpMailTransport implements MailTransportInterface
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromEmail;
    private string $fromName;
    private int $timeout;
    private bool $verifyTls;

    /**
     * Initializes the SMTP transport with normalized configuration settings and an optional clock.
     *
     * @param array<string, mixed> $config SMTP configuration settings from `Config::getMailerSettings()`.
     * @param ClockInterface|null $clock Optional deterministic clock for RFC 2822 Date headers.
     */
    public function __construct(
        array $config,
        private readonly ?ClockInterface $clock = null
    ) {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 587;
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $encryption = $config['encryption'] ?? 'tls';
        $fromEmail = $config['from_email'] ?? '';
        $fromName = $config['from_name'] ?? 'Magma Framework';
        $timeout = $config['timeout'] ?? 15;
        $verifyTls = $config['verify_tls'] ?? true;

        $this->host = is_scalar($host) ? trim((string) $host) : '127.0.0.1';
        $this->port = is_scalar($port) ? (int) $port : 587;
        $this->username = is_scalar($username) ? trim((string) $username) : '';
        $this->password = is_scalar($password) ? (string) $password : '';
        $this->encryption = is_scalar($encryption) ? strtolower(trim((string) $encryption)) : 'tls';
        $this->fromEmail = is_scalar($fromEmail) ? trim((string) $fromEmail) : '';
        $this->fromName = is_scalar($fromName) ? trim((string) $fromName) : 'Magma Framework';
        $this->timeout = is_scalar($timeout) ? max(1, (int) $timeout) : 15;
        $this->verifyTls = is_bool($verifyTls) ? $verifyTls : (bool) $verifyTls;
    }

    /**
     * Sends an email via an RFC 5321 SMTP socket connection.
     *
     * Execution Flow:
     * 1. Validates and sanitizes sender and recipient envelope addresses against CRLF injection.
     * 2. Opens a TCP or implicit TLS stream socket with X.509 peer certificate verification.
     * 3. Validates the server `220` greeting and issues `EHLO` (with `HELO` fallback).
     * 4. Upgrades plaintext connections via `STARTTLS` (TLSv1.2 / TLSv1.3) when configured and re-issues `EHLO` per RFC 3207.
     * 5. Authenticates via `AUTH LOGIN` when credentials are provided.
     * 6. Transmits `MAIL FROM`, `RCPT TO`, and dot-stuffed `DATA` headers/body.
     * 7. Closes the session cleanly with `QUIT` and releases the socket handle.
     *
     * Logic behind the logic:
     * - Bounded retry backoff (`maxAttempts = 3`) absorbs transient `421` / rate-limit responses from upstream relays without dropping transactional mail.
     *
     * @param string $toEmail Recipient email address.
     * @param string $subject Email subject line.
     * @param string $body Compiled HTML or text email body.
     * @param array<string, string> $headers Optional additional MIME headers.
     * @return bool True if accepted for delivery (`250`) by the SMTP server, false otherwise.
     */
    public function send(string $toEmail, string $subject, string $body, array $headers = []): bool
    {
        $rawFrom = $headers['From'] ?? $this->fromEmail;
        $fromEmail = $this->sanitizeHeaderValue($this->extractEmailAddress($rawFrom));
        $cleanTo = $this->sanitizeHeaderValue($this->extractEmailAddress($toEmail));

        if ($this->host === '' || $fromEmail === '' || $cleanTo === '') {
            error_log('SMTP Transport Error: Missing required host, sender address, or recipient address.');
            return false;
        }

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $socket = null;

            try {
                $remoteSocket = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;

                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer'       => $this->verifyTls,
                        'verify_peer_name'  => $this->verifyTls,
                        'allow_self_signed' => !$this->verifyTls,
                    ],
                ]);

                $errno = 0;
                $errstr = '';
                $client = @stream_socket_client(
                    $remoteSocket,
                    $errno,
                    $errstr,
                    $this->timeout,
                    STREAM_CLIENT_CONNECT,
                    $context
                );

                if (!is_resource($client)) {
                    error_log("SMTP Connection failed to {$remoteSocket}: [{$errno}] {$errstr}");
                    return false;
                }

                $socket = $client;
                stream_set_timeout($socket, $this->timeout);

                // 1. Read initial banner (Expected: 220)
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '220')) {
                    throw new RuntimeException("SMTP greeting failed: {$response}");
                }

                // 2. Send EHLO
                $detectedHost = gethostname();
                $clientHost = is_string($detectedHost) && $detectedHost !== '' ? $detectedHost : 'localhost';
                $this->sendCommand($socket, "EHLO {$clientHost}");
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '250')) {
                    $this->sendCommand($socket, "HELO {$clientHost}");
                    $response = $this->readResponse($socket);
                    if (!str_starts_with($response, '250')) {
                        throw new RuntimeException("SMTP EHLO/HELO failed: {$response}");
                    }
                }

                // 3. Negotiate STARTTLS when configured
                if ($this->encryption === 'tls' || $this->encryption === 'starttls') {
                    $this->sendCommand($socket, 'STARTTLS');
                    $response = $this->readResponse($socket);
                    if (!str_starts_with($response, '220')) {
                        throw new RuntimeException("SMTP STARTTLS rejected by server: {$response}");
                    }

                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT
                        | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0)
                        | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);

                    $cryptoSuccess = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                    if ($cryptoSuccess !== true) {
                        throw new RuntimeException('Failed to establish verified TLS encryption handshake with SMTP server.');
                    }

                    // Re-issue EHLO over encrypted channel per RFC 3207
                    $this->sendCommand($socket, "EHLO {$clientHost}");
                    $response = $this->readResponse($socket);
                    if (!str_starts_with($response, '250')) {
                        throw new RuntimeException("SMTP EHLO after STARTTLS failed: {$response}");
                    }
                }

                // 4. Authenticate via AUTH LOGIN if credentials are provided
                if ($this->username !== '') {
                    $this->sendCommand($socket, 'AUTH LOGIN');
                    $response = $this->readResponse($socket);
                    if (!str_starts_with($response, '334')) {
                        throw new RuntimeException("SMTP AUTH LOGIN rejected: {$response}");
                    }

                    $this->sendCommand($socket, base64_encode($this->username));
                    $response = $this->readResponse($socket);
                    if (!str_starts_with($response, '334')) {
                        throw new RuntimeException("SMTP username rejected: {$response}");
                    }

                    $this->sendCommand($socket, base64_encode($this->password));
                    $response = $this->readResponse($socket);
                    if (!str_starts_with($response, '235')) {
                        throw new RuntimeException("SMTP authentication failed: {$response}");
                    }
                }

                // 5. MAIL FROM
                $this->sendCommand($socket, "MAIL FROM:<{$fromEmail}>");
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '250')) {
                    throw new RuntimeException("SMTP MAIL FROM failed: {$response}");
                }

                // 6. RCPT TO
                $this->sendCommand($socket, "RCPT TO:<{$cleanTo}>");
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '250') && !str_starts_with($response, '251')) {
                    throw new RuntimeException("SMTP RCPT TO failed for recipient [{$cleanTo}]: {$response}");
                }

                // 7. DATA
                $this->sendCommand($socket, 'DATA');
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '354')) {
                    if ($this->isTransientRateLimit($response) && $attempt < $maxAttempts) {
                        fclose($socket);
                        $socket = null;
                        usleep(3500000);
                        continue;
                    }
                    throw new RuntimeException("SMTP DATA command rejected: {$response}");
                }

                // 8. Build sanitized RFC 5322 message payload
                $safeFromName = $this->sanitizeHeaderValue($this->fromName !== '' ? $this->fromName : 'Magma Framework');
                $formattedFrom = "{$safeFromName} <{$fromEmail}>";
                $now = $this->clock !== null
                    ? $this->clock->now()
                    : new DateTimeImmutable('now', new DateTimeZone('UTC'));

                $headersMap = array_merge([
                    'From'                      => $formattedFrom,
                    'To'                        => $this->sanitizeHeaderValue($toEmail),
                    'Subject'                   => $this->sanitizeHeaderValue($subject),
                    'Date'                      => $now->format(DateTimeInterface::RFC2822),
                    'MIME-Version'              => '1.0',
                    'Content-Type'              => 'text/html; charset=UTF-8',
                    'Content-Transfer-Encoding' => '8bit',
                    'X-Mailer'                  => 'Magma SMTP Transport',
                ], $headers);

                $headerLines = [];
                foreach ($headersMap as $hKey => $hVal) {
                    $cleanKey = $this->sanitizeHeaderValue((string) $hKey);
                    $cleanVal = $this->sanitizeHeaderValue((string) $hVal);
                    if ($cleanKey !== '') {
                        $headerLines[] = "{$cleanKey}: {$cleanVal}";
                    }
                }

                // Dot-stuffing according to RFC 5321 §4.5.2
                $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
                $bodyLines = explode("\n", $normalizedBody);
                $stuffedBodyLines = [];
                foreach ($bodyLines as $line) {
                    $stuffedBodyLines[] = str_starts_with($line, '.') ? '.' . $line : $line;
                }

                $payload = implode("\r\n", $headerLines)
                    . "\r\n\r\n"
                    . implode("\r\n", $stuffedBodyLines)
                    . "\r\n.\r\n";

                $this->sendCommand($socket, $payload, false);
                $response = $this->readResponse($socket);
                if (!str_starts_with($response, '250')) {
                    if ($this->isTransientRateLimit($response) && $attempt < $maxAttempts) {
                        fclose($socket);
                        $socket = null;
                        usleep(3500000);
                        continue;
                    }
                    throw new RuntimeException("SMTP data transmission failed: {$response}");
                }

                // 9. QUIT
                $this->sendCommand($socket, 'QUIT');
                $this->readResponse($socket);

                return true;
            } catch (Throwable $e) {
                if (is_resource($socket)) {
                    fclose($socket);
                    $socket = null;
                }

                if ($this->isTransientRateLimit($e->getMessage()) && $attempt < $maxAttempts) {
                    usleep(3500000);
                    continue;
                }

                error_log("SMTP Transport Exception while sending to {$cleanTo}: " . $e->getMessage());
                return false;
            } finally {
                if (is_resource($socket)) {
                    fclose($socket);
                }
            }
        }

        return false;
    }

    /**
     * Writes raw command text to the open SMTP socket stream.
     *
     * @param resource $socket
     * @param string $command
     * @param bool $appendNewline
     */
    private function sendCommand($socket, string $command, bool $appendNewline = true): void
    {
        $payload = $appendNewline ? $command . "\r\n" : $command;
        fwrite($socket, $payload);
    }

    /**
     * Reads single-line or multi-line RFC 5321 responses from the SMTP socket stream.
     *
     * @param resource $socket
     * @return string
     */
    private function readResponse($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    /**
     * Extracts the raw email address from a formatted RFC 5322 mailbox string (`Name <user@example.com>`).
     *
     * @param string $raw
     * @return string
     */
    private function extractEmailAddress(string $raw): string
    {
        if (preg_match('/<([^>]+)>/', $raw, $matches) === 1) {
            return trim($matches[1]);
        }
        return trim($raw);
    }

    /**
     * Strips CR and LF characters to prevent SMTP command and MIME header injection.
     *
     * @param string $value
     * @return string
     */
    private function sanitizeHeaderValue(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }

    /**
     * Detects transient SMTP rate-limit or throttling responses eligible for bounded retry.
     *
     * @param string $message
     * @return bool
     */
    private function isTransientRateLimit(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'too many emails')
            || str_contains($message, '421 ')
            || str_starts_with($message, '421')
            || str_contains($message, '550 5.7.0');
    }
}
