<?php

namespace Magma\config;

/**
 * Title: DotEnv File Parser
 *
 * Purpose:
 * - Responsible for reading and parsing a .env file.
 * - Extracts environment variables from the file into an associative array.
 * 
 * Why / Why this design:
 * - Adheres to the Single Responsibility Principle (SRP). 
 * - The Config registry should not care *how* files are parsed, it should only 
 *   care about holding configuration state. By extracting the parsing logic here, 
 *   we ensure the codebase is modular and easier to maintain.
 * 
 * Teaching notes:
 * - This is a lightweight parser meant for development and simple production needs.
 * - It does not support complex features like nested variables or multiline strings 
 *   found in advanced libraries (like `vlucas/phpdotenv`), keeping the framework's footprint small.
 */
class DotEnvParser
{
    /**
     * Parses a .env file and returns an associative array of key-value pairs.
     * 
     * Execution Flow:
     * 1. Checks if the file is readable.
     * 2. Guards against excessively large files to prevent memory exhaustion.
     * 3. Reads the file line by line, skipping comments and empty lines.
     * 4. Splits each line into a key and value based on the first '=' character.
     * 5. Strips surrounding quotes from the value if present.
     * 6. Returns the accumulated key-value pairs.
     *
     * Logic behind the logic:
     * - Reading line-by-line and splitting precisely on the first '=' ensures that 
     *   environment variables with equal signs in their values (like base64 strings) 
     *   are not improperly truncated. The memory guard prevents malicious or accidental 
     *   large files from crashing the application during boot.
     * 
     * @param string $path Path to the .env file.
     * @return array<string, string>
     */
    public static function parse(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        // Prevent memory exhaustion from large accidental file dumps
        if (filesize($path) > 65536) { // 64KB limit
            return [];
        }

        $envVariables = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            // Split by the first '='
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove surrounding quotes if they exist
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            $envVariables[$key] = $value;
        }

        return $envVariables;
    }
}
