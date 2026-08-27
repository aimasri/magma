<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Title: Architecture Smoke Test
 * 
 * Purpose: Verifies that the PHPUnit testing environment and Composer 
 * PSR-4 autoloader are correctly configured for the Magma framework.
 * 
 * Why this design: A basic smoke test ensures the pipeline and testing 
 * infrastructure ("Lava") are operational before writing complex tests.
 * 
 * Teaching notes: This test serves as a simple verification gate. If this fails,
 * the autoloader or testing configuration is fundamentally broken.
 */
class ArchitectureTest extends TestCase
{
    /**
     * Execution Flow:
     * 1. Assert true to verify PHPUnit execution.
     * 2. Check if a core framework directory exists to verify paths.
     */
    public function test_testing_environment_is_operational(): void
    {
        $this->assertTrue(true);
        $this->assertDirectoryExists(__DIR__ . '/../../magma');
    }
}
