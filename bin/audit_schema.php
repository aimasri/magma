#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Title: Multi-Tenant Schema & Codebase Boundary Security Auditor CLI
 *
 * Purpose:
 * - Executes automated static and database schema audits verifying multi-tenant isolation.
 * - Validates presence of `tenant_id` foreign keys and composite indexes across database tables.
 * - Enforces architectural boundaries by detecting prohibited superglobal access (`$_POST`, `$_GET`, `$_SESSION`) in application controllers and services.
 *
 * Why / Why this design:
 * - Shift-Left Security: Running this audit tool as a pre-commit or CI/CD gate guarantees that developer errors (like forgetting a `tenant_id` column or using raw superglobals) are detected and blocked before reaching production.
 *
 * Teaching notes:
 * - Exit code 0 indicates full compliance; exit code 1 indicates security or architectural boundary violations.
 */

require_once dirname(__DIR__) . '/magma/config/bootstrap.php';

use Magma\validation\TenantSecurityAuditor;
use Magma\database\Connection;

$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$bold = "\033[1m";
$reset = "\033[0m";

echo "{$bold}{$blue}===================================================={$reset}\n";
echo "{$bold} Magma Multi-Tenant Security & Boundary Auditor{$reset}\n";
echo "{$bold}{$blue}===================================================={$reset}\n\n";

$pdo = null;
try {
    if ($container->has(Connection::class)) {
        $connection = $container->get(Connection::class);
        $pdo = $connection->getWritePdo();
    } elseif ($container->has(PDO::class)) {
        $pdo = $container->get(PDO::class);
    }
} catch (\Throwable $e) {
    echo "{$yellow}[!] Database connection unavailable: {$e->getMessage()}{$reset}\n";
    echo "    Proceeding with static codebase boundary audit only.\n\n";
}

$auditor = new TenantSecurityAuditor($pdo, ROOT_DIR);
$results = $auditor->runFullAudit();

// 1. Display Database Schema Audit Results
echo "{$bold}[1] Database Schema Audit{$reset}\n";
echo "----------------------------------------------------\n";
$schema = $results['schema'];

if ($schema['skipped']) {
    echo "  {$yellow}[SKIPPED]{$reset} {$schema['message']}\n";
} else {
    echo sprintf("  Audited Tables: %d\n", $schema['tables_audited']);
    echo sprintf("  Compliant Tables: %d\n", count($schema['passed']));

    if (!empty($schema['violations'])) {
        echo "\n  {$red}{$bold}Schema Violations (" . count($schema['violations']) . "):{$reset}\n";
        foreach ($schema['violations'] as $v) {
            echo "    {$red}✖ [{$v['type']}] Table '{$v['table']}': {$v['message']}{$reset}\n";
        }
    }

    if (!empty($schema['warnings'])) {
        echo "\n  {$yellow}{$bold}Schema Warnings (" . count($schema['warnings']) . "):{$reset}\n";
        foreach ($schema['warnings'] as $w) {
            echo "    {$yellow}▲ [{$w['type']}] Table '{$w['table']}': {$w['message']}{$reset}\n";
        }
    }

    if (empty($schema['violations']) && empty($schema['warnings'])) {
        echo "  {$green}✔ All audited tables satisfy multi-tenant isolation invariants.{$reset}\n";
    }
}

// 2. Display Codebase Boundary Audit Results
echo "\n{$bold}[2] Codebase Architectural Boundary Audit{$reset}\n";
echo "----------------------------------------------------\n";
$codebase = $results['codebase'];

echo sprintf("  Audited PHP Source Files: %d\n", $codebase['files_audited']);

if (!empty($codebase['violations'])) {
    echo "\n  {$red}{$bold}Boundary Violations (" . count($codebase['violations']) . "):{$reset}\n";
    foreach ($codebase['violations'] as $v) {
        echo "    {$red}✖ [{$v['type']}] {$v['file']}: {$v['message']}{$reset}\n";
    }
}

if (!empty($codebase['warnings'])) {
    echo "\n  {$yellow}{$bold}Boundary Warnings (" . count($codebase['warnings']) . "):{$reset}\n";
    foreach ($codebase['warnings'] as $w) {
        echo "    {$yellow}▲ [{$w['type']}] {$w['file']}: {$w['message']}{$reset}\n";
    }
}

if (empty($codebase['violations']) && empty($codebase['warnings'])) {
    echo "  {$green}✔ Zero direct superglobal breaches detected. DTO boundaries respected.{$reset}\n";
}

// Summary
echo "\n{$bold}{$blue}===================================================={$reset}\n";
echo sprintf("{$bold} Total Violations: %s%d%s  |  Warnings: %s%d%s{$reset}\n",
    $results['violations_count'] > 0 ? $red : $green,
    $results['violations_count'],
    $reset,
    $results['warnings_count'] > 0 ? $yellow : $green,
    $results['warnings_count'],
    $reset
);
echo "{$bold}{$blue}===================================================={$reset}\n";

if (!$results['success']) {
    echo "{$red}{$bold}AUDIT FAILED: Fix security boundary violations before deployment.{$reset}\n\n";
    exit(1);
}

echo "{$green}{$bold}AUDIT PASSED: Multi-tenant security invariants satisfied.{$reset}\n\n";
exit(0);
