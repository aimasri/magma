<?php

namespace Magma\services;

/**
 * Title: Timezone Resolver Service
 *
 * Purpose:
 * - Centralizes geolocation/timezone logic for the framework.
 * - Resolves context strings (like city names) to valid PHP timezone identifiers.
 *
 * Why this design:
 * - Avoids hardcoding timezone strings or logic directly inside controllers.
 * - Provides a single source of truth for timezone resolution, which can easily
 *   be swapped later for a more complex API integration (e.g., Google Maps API).
 *
 * Teaching notes:
 * - Dependency Injection can be used to inject the $timezoneMap, making it configurable per environment.
 * - Industry standard is to store UTC in the database and only translate to local time at the view/presentation layer.
 */
class TimezoneResolverService
{
    private array $timezoneMap;

    /**
     * @param array $timezoneMap Optional map of location keywords to timezone strings.
     */
    public function __construct(array $timezoneMap = [])
    {
        $this->timezoneMap = $timezoneMap ?: [
            'new york' => 'America/New_York',
            'london' => 'Europe/London',
            'tokyo' => 'Asia/Tokyo'
        ];
    }

    /**
     * Resolves the timezone based on a given location or context.
     *
     * Execution Flow:
     * 1. Normalize the location string to lowercase.
     * 2. Iterate through the timezone map to check for substring matches.
     * 3. Return the mapped timezone if a match is found.
     * 4. Fallback to UTC if no match is identified.
     *
     * Logic behind the logic:
     * - Fail-safe default: Always returning a valid timezone ('UTC') prevents application crashes when unexpected input is provided.
     *
     * @param string $location String representing a location or context.
     * @return string Valid PHP timezone string.
     */
    public function resolve(string $location): string
    {
        $location = strtolower(trim($location));
        
        foreach ($this->timezoneMap as $keyword => $timezone) {
            if (str_contains($location, $keyword)) {
                return $timezone;
            }
        }
        
        // Default fallback timezone
        return 'UTC';
    }
}
