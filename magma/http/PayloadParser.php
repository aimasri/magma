<?php

namespace Magma\http;

/**
 * Payload Parser
 *
 * Purpose:
 * - Decouples payload extraction (like JSON decoding) from the core Request class.
 */
class PayloadParser
{
    /**
     * Parses the raw JSON body if the Content-Type header indicates JSON.
     * 
     * @param string $contentType The Content-Type header value.
     * @param string|null $rawBody The raw request body (if already read).
     * @return array|null The parsed array, or null if not JSON.
     * @throws \RuntimeException if the JSON is malformed.
     */
    public static function parseJsonPayload(string $contentType, ?string &$rawBody = null): ?array
    {
        if (str_contains(strtolower($contentType), 'json')) {
            if ($rawBody === null) {
                $rawBody = (string) file_get_contents('php://input');
            }

            if ($rawBody !== '') {
                try {
                    $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                    return is_array($decoded) ? $decoded : [];
                } catch (\JsonException $e) {
                    throw new \RuntimeException("Invalid JSON payload: " . $e->getMessage(), 400, $e);
                }
            } else {
                return [];
            }
        }
        
        return null;
    }
}
