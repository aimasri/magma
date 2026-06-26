<?php

namespace Magma\config;

/**
 * DotEnv File Parser
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
