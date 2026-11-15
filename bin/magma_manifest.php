#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Title: Magma Core Cryptographic Manifest Generator & Verifier CLI
 *
 * Purpose:
 * - Generates and verifies a deterministic SHA-256 manifest (`magma/manifest.sha256`) covering every
 *   tracked file inside the `magma/` core framework directory.
 * - Detects unauthorized modifications (`[MODIFIED]`), deleted files (`[MISSING]`), and unlisted
 *   additions (`[UNAUTHORIZED EXTRA FILE]`) across downstream deployments and CI pipelines.
 *
 * Why / Why this design:
 * - Downstream Zero-Drift Governance: Downstream applications (e.g., Lava-derived platforms) must
 *   never silently diverge from upstream `magma/` core code. Encoding `magma/manifest.sha256` in exact
 *   GNU `sha256sum` two-space format (`<sha256>  <relPath>\n`) without comment headers allows both
 *   this zero-dependency PHP CLI and standard `sha256sum -c magma/manifest.sha256` on Linux CI runners
 *   to verify core integrity identically.
 * - Zero Framework Boot Coupling: Intentionally omits `magma/config/bootstrap.php` so verification
 *   works offline before dependencies, `.env`, or the container are initialized, and even if `magma/`
 *   itself has been corrupted.
 *
 * Teaching notes:
 * - Only `magma/manifest.sha256` (the manifest itself) and `magma/config/routes.cache.php` (runtime
 *   generated OPcache artifact) are excluded from scanning.
 * - Defaults to `--verify` when invoked without flags; pass `--generate` after intentional upstream
 *   `magma/` changes to refresh the signed manifest.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERROR] bin/magma_manifest.php must be executed from the CLI.\n");
    exit(1);
}

const MANIFEST_REL_PATH = 'magma/manifest.sha256';
const IGNORED_REL_PATHS = [
    'magma/manifest.sha256' => true,
    'magma/config/routes.cache.php' => true,
];

/**
 * Prints CLI usage instructions.
 */
function printUsage(): void
{
    echo <<<TEXT
Magma Core Cryptographic Manifest Tool

Usage:
  php bin/magma_manifest.php [--verify | --generate | --help]

Options:
  --verify     Verify magma/ against magma/manifest.sha256 (default when no flag is given).
  --generate   Scan magma/ and write GNU-compatible magma/manifest.sha256.
  --help, -h   Display this help message.

TEXT;
}

/**
 * Parses CLI flags into an execution mode.
 *
 * @param list<string> $argv
 */
function parseMode(array $argv): string
{
    $args = array_slice($argv, 1);

    if ($args === []) {
        return 'verify';
    }

    $mode = null;

    foreach ($args as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            return 'help';
        }

        if ($arg === '--generate' || $arg === '--verify') {
            $selected = substr($arg, 2);
            if ($mode !== null && $mode !== $selected) {
                throw new InvalidArgumentException('Cannot combine --generate and --verify.');
            }
            $mode = $selected;
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    return $mode ?? 'verify';
}

/**
 * Normalizes a filesystem path to POSIX forward slashes.
 */
function normalizePosixPath(string $path): string
{
    return str_replace('\\', '/', $path);
}

/**
 * Recursively collects all non-ignored core files under `<repoRoot>/magma`, sorted lexicographically.
 *
 * @return list<string> Relative paths formatted as `magma/...`
 */
function collectCoreFiles(string $repoRoot): array
{
    $magmaDir = $repoRoot . DIRECTORY_SEPARATOR . 'magma';
    if (!is_dir($magmaDir)) {
        throw new RuntimeException("Magma core directory not found: {$magmaDir}");
    }

    $normalizedRoot = rtrim(normalizePosixPath($repoRoot), '/') . '/';
    $rootLen = strlen($normalizedRoot);

    $directoryIterator = new RecursiveDirectoryIterator(
        $magmaDir,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
    );
    $iterator = new RecursiveIteratorIterator(
        $directoryIterator,
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $files = [];

    /** @var SplFileInfo $fileInfo */
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $fullPath = normalizePosixPath($fileInfo->getPathname());
        if (!str_starts_with($fullPath, $normalizedRoot)) {
            throw new RuntimeException("Unexpected path outside repository root: {$fullPath}");
        }

        $relPath = substr($fullPath, $rootLen);
        if (isset(IGNORED_REL_PATHS[$relPath])) {
            continue;
        }

        $files[] = $relPath;
    }

    sort($files, SORT_STRING);

    return $files;
}

/**
 * Computes the lowercase SHA-256 hex digest for a file.
 */
function computeFileSha256(string $fullPath): string
{
    $hash = hash_file('sha256', $fullPath);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException("Failed to compute SHA-256 hash for: {$fullPath}");
    }

    return strtolower($hash);
}

/**
 * Parses and validates `magma/manifest.sha256` entries.
 *
 * @return array<string, string> Map of normalized relative path (`magma/...`) => lowercase SHA-256 hex digest
 */
function loadManifestEntries(string $manifestPath): array
{
    if (!is_file($manifestPath) || !is_readable($manifestPath)) {
        throw new RuntimeException('Manifest file missing or unreadable: ' . MANIFEST_REL_PATH);
    }

    $raw = file_get_contents($manifestPath);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException('Manifest file is empty or unreadable: ' . MANIFEST_REL_PATH);
    }

    $normalizedRaw = str_replace("\r\n", "\n", $raw);
    if (!str_ends_with($normalizedRaw, "\n")) {
        throw new RuntimeException('Malformed manifest: missing trailing newline.');
    }

    $lines = explode("\n", substr($normalizedRaw, 0, -1));
    $entries = [];

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        if (!preg_match('/^([0-9a-fA-F]{64})  (magma\/[^\0\r\n]+)$/', $line, $matches)) {
            throw new RuntimeException("Malformed manifest entry at line {$lineNumber}.");
        }

        $hash = strtolower($matches[1]);
        $relPath = $matches[2];

        if (str_contains($relPath, '\\') || preg_match('#(^|/)\.\.(/|$)#', $relPath) === 1) {
            throw new RuntimeException("Invalid relative path in manifest at line {$lineNumber}: {$relPath}");
        }

        if (isset(IGNORED_REL_PATHS[$relPath])) {
            throw new RuntimeException("Ignored file must not appear in manifest at line {$lineNumber}: {$relPath}");
        }

        if (isset($entries[$relPath])) {
            throw new RuntimeException("Duplicate manifest entry at line {$lineNumber}: {$relPath}");
        }

        $entries[$relPath] = $hash;
    }

    if ($entries === []) {
        throw new RuntimeException('Malformed manifest: contains zero entries.');
    }

    return $entries;
}

/**
 * Generates `magma/manifest.sha256` in strict GNU `sha256sum` format.
 */
function runGenerate(string $repoRoot, string $manifestPath): int
{
    $relPaths = collectCoreFiles($repoRoot);
    $buffer = '';

    foreach ($relPaths as $relPath) {
        $fullPath = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $hash = computeFileSha256($fullPath);
        $buffer .= "{$hash}  {$relPath}\n";
    }

    if (file_put_contents($manifestPath, $buffer, LOCK_EX) === false) {
        throw new RuntimeException('Failed to write manifest file: ' . MANIFEST_REL_PATH);
    }

    $count = count($relPaths);
    echo "[OK] Generated " . MANIFEST_REL_PATH . " ({$count} files).\n";

    return 0;
}

/**
 * Verifies the live `magma/` directory against `magma/manifest.sha256`.
 */
function runVerify(string $repoRoot, string $manifestPath): int
{
    $expectedEntries = loadManifestEntries($manifestPath);
    $liveFiles = collectCoreFiles($repoRoot);
    $liveLookup = array_fill_keys($liveFiles, true);

    $modified = [];
    $missing = [];
    $extra = [];

    foreach ($expectedEntries as $relPath => $expectedHash) {
        $fullPath = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        if (!isset($liveLookup[$relPath]) || !is_file($fullPath)) {
            $missing[] = $relPath;
            continue;
        }

        $actualHash = computeFileSha256($fullPath);
        if (!hash_equals($expectedHash, $actualHash)) {
            $modified[] = $relPath;
        }
    }

    foreach ($liveFiles as $relPath) {
        if (!isset($expectedEntries[$relPath])) {
            $extra[] = $relPath;
        }
    }

    sort($modified, SORT_STRING);
    sort($missing, SORT_STRING);
    sort($extra, SORT_STRING);

    $driftCount = count($modified) + count($missing) + count($extra);

    if ($driftCount > 0) {
        foreach ($modified as $relPath) {
            echo "[MODIFIED] {$relPath}\n";
        }
        foreach ($missing as $relPath) {
            echo "[MISSING] {$relPath}\n";
        }
        foreach ($extra as $relPath) {
            echo "[UNAUTHORIZED EXTRA FILE] {$relPath}\n";
        }

        echo sprintf(
            "[FAIL] Magma core manifest drift detected (%d modified, %d missing, %d extra).\n",
            count($modified),
            count($missing),
            count($extra)
        );

        return 1;
    }

    $verifiedCount = count($expectedEntries);
    echo "[OK] Magma core manifest verified ({$verifiedCount} files, 0 drift).\n";

    return 0;
}

/**
 * CLI entrypoint.
 *
 * @param list<string> $argv
 */
function main(array $argv): int
{
    $repoRoot = dirname(__DIR__);
    $manifestPath = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, MANIFEST_REL_PATH);

    try {
        $mode = parseMode($argv);
    } catch (InvalidArgumentException $e) {
        fwrite(STDERR, "[ERROR] {$e->getMessage()}\n");
        printUsage();
        return 1;
    }

    if ($mode === 'help') {
        printUsage();
        return 0;
    }

    try {
        return $mode === 'generate'
            ? runGenerate($repoRoot, $manifestPath)
            : runVerify($repoRoot, $manifestPath);
    } catch (Throwable $e) {
        fwrite(STDERR, "[ERROR] {$e->getMessage()}\n");
        return 1;
    }
}

/** @var list<string> $cliArgv */
$cliArgv = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? array_values($_SERVER['argv']) : [];
exit(main($cliArgv));
