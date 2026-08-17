<?php
/**
 * Title: Magma Framework Welcome Landing View
 *
 * Purpose:
 * - Renders the default developer landing dashboard for the Magma framework.
 * - Matches the visual design system, dark canvas, and typography of the developer diagnostic viewer.
 *
 * Teaching notes:
 * - Adheres strictly to AGENTS.md: zero static inline styles (`style="..."`), standard sentence casing,
 *   and SOLID modular stylesheets (`/www/css/app.css` -> `/www/css/components/`).
 *
 * @var array $data
 * @var string $data['title']
 * @var string $data['phpVersion']
 * @var string $data['phpSapi']
 * @var string $data['environment']
 * @var bool $data['debug']
 * @var string $data['dbDriver']
 * @var string $data['memoryUsage']
 * @var string $data['serverOs']
 * @var \Magma\view\TemplateEngine $data['engine']
 */

$pageTitle = $data['title'] ?? 'Magma Framework Core';
$phpVersion = $data['phpVersion'] ?? PHP_VERSION;
$environment = $data['environment'] ?? 'development';
$isDebug = !empty($data['debug']);
$dbDriver = strtoupper((string)($data['dbDriver'] ?? 'PGSQL'));
$memoryUsage = $data['memoryUsage'] ?? '0 MB';
$serverOs = $data['serverOs'] ?? PHP_OS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="welcome-page">
    <div class="welcome-container">
        <!-- Hero Banner Card -->
        <header class="welcome-hero">
            <div class="welcome-hero__top">
                <span class="badge badge--brand">Magma Core Engine</span>
                <span class="badge badge--pill <?= $isDebug ? 'badge--success' : 'badge--neutral' ?>">
                    <?= $isDebug ? 'Debug mode active' : 'Production mode' ?>
                </span>
            </div>

            <h1 class="welcome-hero__title">Enterprise PHP foundation</h1>
            <p class="welcome-hero__tagline">
                A solid, explicit, "no magic" application framework built for high-throughput, multi-tenant SaaS environments and mission-critical workflows.
            </p>

            <div class="welcome-hero__actions">
                <a href="#features" class="btn btn--primary">Architecture tour</a>
                <a href="#cli-commands" class="btn btn--outline">CLI utilities</a>
            </div>

            <!-- Runtime Diagnostics Pill Bar -->
            <div class="metric-bar">
                <div class="metric-pill">
                    <span class="metric-pill__label">PHP</span>
                    <span class="metric-pill__val"><?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__label">Environment</span>
                    <span class="metric-pill__val"><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__label">Database</span>
                    <span class="metric-pill__val"><?= htmlspecialchars($dbDriver, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__label">Memory peak</span>
                    <span class="metric-pill__val"><?= htmlspecialchars($memoryUsage, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="metric-pill">
                    <span class="metric-pill__label">Host OS</span>
                    <span class="metric-pill__val"><?= htmlspecialchars($serverOs, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </header>

        <!-- Core Architectural Pillars Grid -->
        <section id="features">
            <div class="section-header">
                <h2 class="section-header__title">Kernel architectural pillars</h2>
                <span class="badge badge--info">SOLID & CQRS</span>
            </div>

            <div class="card-grid card-grid--2col">
                <!-- Pillar 1: Persistence & CQRS -->
                <div class="card card--accent-flame card--interactive">
                    <div class="card__header">
                        <span class="badge badge--neutral">Persistence</span>
                        <h3 class="card__title">CQRS database infrastructure</h3>
                        <p class="card__subtitle">Connection segregation, keyset pagination, and savepoints</p>
                    </div>
                    <div class="card__body">
                        Segregates read replicas from write masters at the repository boundary. Employs constant-time B-Tree keyset cursor seeking, PostgreSQL <code>RETURNING id</code>, and savepoint transaction nesting (<code>SAVEPOINT trans_N</code>).
                    </div>
                </div>

                <!-- Pillar 2: Fast Routing & Action DI -->
                <div class="card card--accent-info card--interactive">
                    <div class="card__header">
                        <span class="badge badge--neutral">Routing</span>
                        <h3 class="card__title">Compiled PCRE routing</h3>
                        <p class="card__subtitle">Immutable value objects and reflection action injection</p>
                    </div>
                    <div class="card__body">
                        FastRoute-style regular expression tree compiler with OPcache pre-compilation manifest. Auto-wires controller method dependencies and performs declarative <code>FormRequest</code> validation prior to action invocation.
                    </div>
                </div>

                <!-- Pillar 3: Async Outbox -->
                <div class="card card--accent-info card--interactive">
                    <div class="card__header">
                        <span class="badge badge--neutral">Events & Queues</span>
                        <h3 class="card__title">Transactional outbox engine</h3>
                        <p class="card__subtitle">PostgreSQL skip locked and idempotent projection guards</p>
                    </div>
                    <div class="card__body">
                        Guarantees exactly-once asynchronous event publishing using PostgreSQL's native <code>FOR UPDATE SKIP LOCKED</code>. Prevents projection race conditions via transactional sequence guards.
                    </div>
                </div>

                <!-- Pillar 4: Diagnostics & Security -->
                <div class="card card--accent-flame card--interactive">
                    <div class="card__header">
                        <span class="badge badge--neutral">Observability</span>
                        <h3 class="card__title">Interactive developer diagnostics</h3>
                        <p class="card__subtitle">Content-negotiated exception boundaries and stack traces</p>
                    </div>
                    <div class="card__body">
                        Extracts live source code context and structured stack traces in development mode while guaranteeing zero sensitive path disclosure in production. Enforces strict multi-tenant boundary auditing via AST linting.
                    </div>
                </div>
            </div>
        </section>

        <!-- Command Line Utilities Reference -->
        <section id="cli-commands">
            <div class="section-header">
                <h2 class="section-header__title">Kernel command line tools</h2>
                <span class="badge badge--neutral">CLI commands</span>
            </div>

            <div class="command-terminal">
                <div class="command-terminal__header">
                    <span>magma-cli-console</span>
                    <span>bash</span>
                </div>
                <div class="command-terminal__body">
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/migrate.php</span>
                        <span class="command-line__comment"># Discovers and executes pending PostgreSQL migrations</span>
                    </div>
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/cache_routes.php</span>
                        <span class="command-line__comment"># Compiles route definitions into routes.cache.php</span>
                    </div>
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/audit_schema.php</span>
                        <span class="command-line__comment"># Audits multi-tenant foreign keys and DTO boundaries</span>
                    </div>
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/outbox_publisher.php</span>
                        <span class="command-line__comment"># Starts the background outbox event worker</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="welcome-footer">
            <p>Magma Framework &bull; Standard PHP 8.2+ Architecture &bull; Debian Linux Optimized</p>
        </footer>
    </div>
</body>
</html>
