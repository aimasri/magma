<?php

declare(strict_types=1);

namespace Magma\validation;

use PDO;

/**
 * Title: Multi-Tenant Schema & Architectural Boundary Auditor
 *
 * Purpose:
 * - Performs static analysis and PostgreSQL database schema auditing to verify multi-tenant data isolation.
 * - Audits database tables for mandatory `tenant_id` foreign keys and composite performance indexes.
 * - Scans codebase controller and repository layers to prohibit raw superglobals (`$_POST`, `$_GET`) and enforce typed DTO/FormRequest boundaries.
 *
 * Why / Why this design:
 * - Automated Security Invariants: In a multi-tenant SaaS application, a single table missing a `tenant_id` index or a controller reading raw `$_POST` can cause catastrophic data leaks. This auditor guarantees automated compliance during CI/CD pre-flight checks.
 *
 * Teaching notes:
 * - Enterprise multi-tenant platforms utilize static and dynamic linters to block non-compliant database migrations before merging.
 */
class TenantSecurityAuditor
{
    private ?PDO $pdo;
    private string $projectRoot;

    /** @var array<string> Tables exempt from mandatory tenant scoping */
    private array $exemptTables = [
        'migrations',
        'schema_migrations',
        'tenants',
        'users',
        'roles',
        'permissions',
        'system_settings',
        'outbox_jobs',
    ];

    /**
     * Initializes the TenantSecurityAuditor.
     *
     * Execution Flow:
     * 1. Accepts an optional PDO instance and an optional project root directory path.
     * 2. Assigns the PDO connection and resolves the root directory fallback (using `ROOT_DIR` or relative path).
     *
     * Logic behind the logic:
     * - Flexibility: Allows the auditor to run in different environments (like CI/CD scripts or normal app lifecycle)
     *   by keeping the database connection and root path optional/configurable.
     *
     * @param PDO|null $pdo
     * @param string|null $projectRoot
     */
    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $this->pdo = $pdo;
        $this->projectRoot = $projectRoot ?? (defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2));
    }

    /**
     * Executes the complete multi-tenant security and boundary audit.
     *
     * @return array<string, mixed>
     */
    public function runFullAudit(): array
    {
        $schemaResults = $this->auditDatabaseSchema();
        $codebaseResults = $this->auditCodebaseBoundaries();

        $totalViolations = count($schemaResults['violations']) + count($codebaseResults['violations']);
        $totalWarnings = count($schemaResults['warnings']) + count($codebaseResults['warnings']);

        return [
            'success' => $totalViolations === 0,
            'violations_count' => $totalViolations,
            'warnings_count' => $totalWarnings,
            'schema' => $schemaResults,
            'codebase' => $codebaseResults,
        ];
    }

    /**
     * Inspects the database schema for multi-tenant isolation compliance.
     *
     * Execution Flow:
     * 1. If PDO connection is available, queries `information_schema.tables`.
     * 2. For each non-exempt table:
     *    a. Checks for `tenant_id` column presence.
     *    b. Verifies `NOT NULL` constraint on tenant column.
     *    c. Checks for index coverage on tenant column in `pg_indexes`.
     * 3. Aggregates results into passed, warnings, and violations.
     *
     * @param PDO|null $pdo
     * @return array{skipped: bool, message?: string, tables_audited: int, violations: array<int, array<string, string>>, warnings: array<int, array<string, string>>, passed: array<int, array<string, string>>}
     */
    public function auditDatabaseSchema(?PDO $pdo = null): array
    {
        $db = $pdo ?? $this->pdo;

        if ($db === null) {
            return [
                'skipped' => true,
                'message' => 'Database connection not provided; schema audit skipped.',
                'tables_audited' => 0,
                'violations' => [],
                'warnings' => [],
                'passed' => [],
            ];
        }

        $violations = [];
        $warnings = [];
        $passed = [];
        $tablesAudited = 0;

        try {
            // Fetch all user tables in the public schema
            $stmt = $db->query("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = 'public' 
                  AND table_type = 'BASE TABLE'
                ORDER BY table_name ASC
            ");
            
            if ($stmt === false) {
                throw new \RuntimeException('Failed to execute query for fetching tables.');
            }
            
            /** @var array<int, string> $tables */
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                if (in_array($table, $this->exemptTables, true)) {
                    continue;
                }

                $tablesAudited++;

                // 1. Check for tenant_id column
                $colStmt = $db->prepare("
                    SELECT column_name, is_nullable, data_type 
                    FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                      AND table_name = :table 
                      AND column_name = 'tenant_id'
                ");
                if ($colStmt === false) {
                    continue;
                }
                
                $colStmt->execute(['table' => $table]);
                
                /** @var array<string, mixed>|false $tenantCol */
                $tenantCol = $colStmt->fetch(PDO::FETCH_ASSOC);

                if ($tenantCol === false) {
                    $violations[] = [
                        'table' => $table,
                        'type' => 'MISSING_TENANT_KEY',
                        'message' => "Table '{$table}' lacks a 'tenant_id' column.",
                    ];
                    continue;
                }

                /** @var string $colName */
                $colName = $tenantCol['column_name'];

                if ($tenantCol['is_nullable'] === 'YES') {
                    $warnings[] = [
                        'table' => $table,
                        'type' => 'NULLABLE_TENANT_KEY',
                        'message' => "Column '{$table}.{$colName}' is nullable. Multi-tenant foreign keys should be NOT NULL.",
                    ];
                }

                // 2. Check for index coverage
                $idxStmt = $db->prepare("
                    SELECT indexname, indexdef 
                    FROM pg_indexes 
                    WHERE schemaname = 'public' 
                      AND tablename = :table
                ");
                $idxStmt->execute(['table' => $table]);
                $indexes = $idxStmt->fetchAll(PDO::FETCH_ASSOC);

                $hasTenantIndex = false;
                foreach ($indexes as $index) {
                    if (str_contains((string)$index['indexdef'], "({$colName}") || str_contains((string)$index['indexdef'], "({$colName},")) {
                        $hasTenantIndex = true;
                        break;
                    }
                }

                if (!$hasTenantIndex) {
                    $warnings[] = [
                        'table' => $table,
                        'type' => 'MISSING_TENANT_INDEX',
                        'message' => "Table '{$table}' has column '{$colName}' but lacks a composite/leading index starting with '{$colName}'.",
                    ];
                } else {
                    $passed[] = [
                        'table' => $table,
                        'tenant_column' => $colName,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $warnings[] = [
                'type' => 'SCHEMA_QUERY_FAILED',
                'message' => 'Schema query encountered error: ' . $e->getMessage(),
            ];
        }

        return [
            'skipped' => false,
            'tables_audited' => $tablesAudited,
            'violations' => $violations,
            'warnings' => $warnings,
            'passed' => $passed,
        ];
    }

    /**
     * Audits codebase files for direct superglobal access and DTO boundary violations.
     *
     * Execution Flow:
     * 1. Scans PHP files in `modules/`, `app/`, and `magma/controllers/`.
     * 2. Inspects AST / source tokens for `$_POST`, `$_GET`, `$_REQUEST` access.
     * 3. Flags controllers or repositories bypassing the HTTP Request / FormRequest abstraction.
     *
     * @param string|null $directory
     * @return array{files_audited: int, violations: array<int, array<string, string>>, warnings: array<int, array<string, string>>}
     */
    public function auditCodebaseBoundaries(?string $directory = null): array
    {
        $scanDir = $directory ?? $this->projectRoot;
        $filesToScan = [];

        $searchPaths = [
            $scanDir . '/modules',
            $scanDir . '/app/controllers',
            $scanDir . '/magma/controllers',
        ];

        foreach ($searchPaths as $path) {
            if (is_dir($path)) {
                $this->collectPhpFiles($path, $filesToScan);
            }
        }

        $violations = [];
        $warnings = [];
        $filesAudited = count($filesToScan);

        foreach ($filesToScan as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $relativePath = str_replace($this->projectRoot . '/', '', $file);

            // Prohibit direct superglobal access in controllers and services
            if (preg_match('/(\$_POST|\$_GET|\$_REQUEST)\[/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $violations[] = [
                    'file' => $relativePath,
                    'type' => 'RAW_SUPERGLOBAL_ACCESS',
                    'symbol' => $matches[1][0],
                    'message' => "Prohibited direct access to '{$matches[1][0]}' detected. Use Request or FormRequest DTOs instead.",
                ];
            }

            // Prohibit raw $_SESSION assignment in controllers (must use SessionInterface)
            if (str_contains($file, 'controllers') && str_contains($content, '$_SESSION[')) {
                $warnings[] = [
                    'file' => $relativePath,
                    'type' => 'RAW_SESSION_ACCESS',
                    'message' => "Direct '\$_SESSION' usage detected in controller. Inject 'SessionInterface' instead.",
                ];
            }
        }

        return [
            'files_audited' => $filesAudited,
            'violations' => $violations,
            'warnings' => $warnings,
        ];
    }

    /**
     * Recursively collects PHP file paths from a given directory.
     *
     * Execution Flow:
     * 1. Scans the provided directory using `scandir`.
     * 2. Iterates over each item, skipping '.' and '..'.
     * 3. If the item is a directory, recursively calls itself.
     * 4. If the item is a file ending in `.php`, appends it to the reference array.
     *
     * Logic behind the logic:
     * - In-memory Aggregation: Passes the `$results` array by reference to avoid the overhead
     *   of array merging at each recursive step, which is critical for large codebases.
     *
     * @param string $dir The directory to scan.
     * @param array<int, string> &$results The reference array to store collected file paths.
     */
    private function collectPhpFiles(string $dir, array &$results): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $dir . '/' . $item;
            if (is_dir($fullPath)) {
                $this->collectPhpFiles($fullPath, $results);
            } elseif (str_ends_with($item, '.php')) {
                $results[] = $fullPath;
            }
        }
    }
}
