<?php
/**
 * Title: Magma Educational Architecture Core Landing View
 *
 * Purpose:
 * - Renders the comprehensive developer syllabus and architecture dashboard for the Magma framework.
 * - Extracts and presents all 10 core architectural modules and engineering philosophies from README.md.
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
        
        <!-- Hero Showcase Banner -->
        <header class="welcome-hero">
            <div class="welcome-hero__top">
                <span class="badge badge--brand">Magma Architecture Core</span>
                <span class="badge badge--pill <?= $isDebug ? 'badge--success' : 'badge--neutral' ?>">
                    <?= $isDebug ? 'Debug mode active' : 'Production mode' ?>
                </span>
            </div>

            <h1 class="welcome-hero__title">The educational architecture core</h1>
            <p class="welcome-hero__tagline">
                An instructional, enterprise-hardened PHP 8.2+ codebase demonstrating how to build robust, scalable, and mathematically sound web applications without relying on heavy, black-box frameworks.
            </p>

            <div class="welcome-hero__actions">
                <a href="#lifecycle" class="btn btn--primary">Request lifecycle</a>
                <a href="#philosophy" class="btn btn--outline">Engineering philosophy</a>
                <a href="#modules" class="btn btn--outline">Architectural syllabus</a>
                <a href="#cli-tools" class="btn btn--outline">CLI utilities</a>
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

        <!-- Request Lifecycle & Onion Stepper -->
        <section id="lifecycle">
            <div class="section-header">
                <h2 class="section-header__title">Request lifecycle & front controller</h2>
                <span class="badge badge--info">Onion architecture</span>
            </div>

            <div class="lifecycle-stepper">
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 01</span>
                    <h3 class="lifecycle-step__title">Front controller</h3>
                    <p class="lifecycle-step__desc">Funnel requests through <code>www/index.php</code>; keep logic outside document root.</p>
                </div>
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 02</span>
                    <h3 class="lifecycle-step__title">Bootstrap & DI</h3>
                    <p class="lifecycle-step__desc">Load environment, register service providers, and initialize container autowiring.</p>
                </div>
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 03</span>
                    <h3 class="lifecycle-step__title">Middleware onion</h3>
                    <p class="lifecycle-step__desc">Process inward pipeline: tenant context, CSRF token, rate limiting, and security headers.</p>
                </div>
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 04</span>
                    <h3 class="lifecycle-step__title">PCRE router</h3>
                    <p class="lifecycle-step__desc">Match compiled route definition and execute Reflection parameter auto-wiring.</p>
                </div>
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 05</span>
                    <h3 class="lifecycle-step__title">FormRequest</h3>
                    <p class="lifecycle-step__desc">Validate strongly-typed input DTOs before the controller action is invoked.</p>
                </div>
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 06</span>
                    <h3 class="lifecycle-step__title">Domain service</h3>
                    <p class="lifecycle-step__desc">Thin controller delegates business actions to domain services and repositories.</p>
                </div>
                <div class="lifecycle-step">
                    <span class="lifecycle-step__num">Step 07</span>
                    <h3 class="lifecycle-step__title">Response exit</h3>
                    <p class="lifecycle-step__desc">Render view or JSON envelope, unwinding outward through middleware layers.</p>
                </div>
            </div>
        </section>

        <!-- Engineering Philosophy -->
        <section id="philosophy">
            <div class="section-header">
                <h2 class="section-header__title">Core engineering philosophy</h2>
                <span class="badge badge--brand">Zero magic</span>
            </div>

            <div class="philosophy-grid">
                <div class="philosophy-item">
                    <h3 class="philosophy-item__title">SOLID principles & DIP</h3>
                    <p class="philosophy-item__desc">
                        Favor interface injection over concrete instantiation. Classes hold a single responsibility and high cohesion.
                    </p>
                </div>
                <div class="philosophy-item">
                    <h3 class="philosophy-item__title">Separation of concerns</h3>
                    <p class="philosophy-item__desc">
                        Controllers never query SQL; views never perform business math; repositories isolate data persistence entirely.
                    </p>
                </div>
                <div class="philosophy-item">
                    <h3 class="philosophy-item__title">Pragmatic DDD</h3>
                    <p class="philosophy-item__desc">
                        Behavior belongs with data. Skinny domain entities manage their own state and sanitization; services orchestrate.
                    </p>
                </div>
                <div class="philosophy-item">
                    <h3 class="philosophy-item__title">Instructional docblocks</h3>
                    <p class="philosophy-item__desc">
                        Every core file explains its title, purpose, architectural rationale, and teaching notes with strict scalar types.
                    </p>
                </div>
            </div>
        </section>

        <!-- 10-Module Architectural Syllabus -->
        <section id="modules">
            <div class="section-header">
                <h2 class="section-header__title">10-Module architectural syllabus</h2>
                <span class="badge badge--neutral">Core syllabus</span>
            </div>

            <div class="syllabus-grid">
                <!-- Module 01 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 01</span>
                        <span class="badge badge--neutral">Lifecycle</span>
                    </div>
                    <h3 class="module-card__title">The Request Lifecycle & Front Controller</h3>
                    <p class="module-card__desc">
                        Funnels all traffic through <code>www/index.php</code>. Guarantees critical security checks cannot be bypassed while shielding framework internals outside the web root.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">PSR-4</span>
                        <span class="badge badge--neutral">Front Controller</span>
                        <span class="badge badge--neutral">Buffer Isolation</span>
                    </div>
                </div>

                <!-- Module 02 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 02</span>
                        <span class="badge badge--neutral">IoC Container</span>
                    </div>
                    <h3 class="module-card__title">Dependency Injection & Autowiring</h3>
                    <p class="module-card__desc">
                        Leverages PHP's Reflection API with in-memory metadata caching to resolve and inject constructor dependencies recursively without static global facades.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Reflection</span>
                        <span class="badge badge--neutral">makeWithArgs()</span>
                        <span class="badge badge--neutral">Service Providers</span>
                    </div>
                </div>

                <!-- Module 03 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 03</span>
                        <span class="badge badge--neutral">Pipeline</span>
                    </div>
                    <h3 class="module-card__title">Pipeline & Middleware Onion Architecture</h3>
                    <p class="module-card__desc">
                        Inward/outward request processing with PSR-15 adapter support. Implements TenantContext binding, atomic CSRF verification, and Redis rate limiting.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">PSR-15</span>
                        <span class="badge badge--neutral">Tenant Isolation</span>
                        <span class="badge badge--neutral">Rate Limiting</span>
                    </div>
                </div>

                <!-- Module 04 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 04</span>
                        <span class="badge badge--neutral">Routing</span>
                    </div>
                    <h3 class="module-card__title">Compiled PCRE Routing & Action DI</h3>
                    <p class="module-card__desc">
                        High-performance regular expression tree routing with OPcache pre-compilation manifest. Auto-wires controller method dependencies and validates FormRequests.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">FastRoute PCRE</span>
                        <span class="badge badge--neutral">FormRequest</span>
                        <span class="badge badge--neutral">PRG Pattern</span>
                    </div>
                </div>

                <!-- Module 05 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 05</span>
                        <span class="badge badge--neutral">Persistence</span>
                    </div>
                    <h3 class="module-card__title">Data Persistence: Repository Pattern</h3>
                    <p class="module-card__desc">
                        Segregates <code>$dbRead</code> replicas from <code>$dbWrite</code> masters. Features PostgreSQL standard quoting, chunked bulk inserts, and domain exception mapping.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">CQRS Split</span>
                        <span class="badge badge--neutral">RETURNING id</span>
                        <span class="badge badge--neutral">Savepoints</span>
                    </div>
                </div>

                <!-- Module 06 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 06</span>
                        <span class="badge badge--neutral">Domain</span>
                    </div>
                    <h3 class="module-card__title">Domain Logic, Services & CQRS</h3>
                    <p class="module-card__desc">
                        Pragmatic DDD with skinny entities managing internal invariants. Employs finite state machines, strategy registries, and event ledger write models.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Skinny Entities</span>
                        <span class="badge badge--neutral">State Machine</span>
                        <span class="badge badge--neutral">Strategy Registry</span>
                    </div>
                </div>

                <!-- Module 07 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 07</span>
                        <span class="badge badge--neutral">Views</span>
                    </div>
                    <h3 class="module-card__title">Decoupled Template Engine</h3>
                    <p class="module-card__desc">
                        Decouples layout from partials with <code>ViewLoaderInterface</code> and modular namespaces (<code>Services::index</code>). Includes in-memory path caching.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Namespaces</span>
                        <span class="badge badge--neutral">Composers</span>
                        <span class="badge badge--neutral">Presenters</span>
                    </div>
                </div>

                <!-- Module 08 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 08</span>
                        <span class="badge badge--neutral">Frontend</span>
                    </div>
                    <h3 class="module-card__title">Frontend Architecture: Modular Vanilla JS</h3>
                    <p class="module-card__desc">
                        Strict ES6 MVC components (<code>MagmaCombobox</code>, <code>MagmaEditor</code>), <code>ObservableStore</code>, and <code>DocumentFragment</code> batching to eliminate DOM repaint thrashing.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Vanilla ES6</span>
                        <span class="badge badge--neutral">WeakSet Events</span>
                        <span class="badge badge--neutral">Cascade Layers</span>
                    </div>
                </div>

                <!-- Module 09 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 09</span>
                        <span class="badge badge--neutral">Outbox & Queues</span>
                    </div>
                    <h3 class="module-card__title">Asynchronous Processing & Event Outbox</h3>
                    <p class="module-card__desc">
                        Transactional outbox engine using PostgreSQL's native <code>FOR UPDATE SKIP LOCKED</code>. Redis list queue worker daemon with idempotent projection guards.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">SKIP LOCKED</span>
                        <span class="badge badge--neutral">Event Dispatcher</span>
                        <span class="badge badge--neutral">Worker Daemon</span>
                    </div>
                </div>

                <!-- Module 10 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Module 10</span>
                        <span class="badge badge--neutral">Performance</span>
                    </div>
                    <h3 class="module-card__title">High-Performance Optimization</h3>
                    <p class="module-card__desc">
                        Eliminates N+1 query storms via batch loaders and multi-root CTEs. Employs constant-time $O(1)$ B-Tree keyset cursor seeking and memory streaming generators.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Keyset Cursor</span>
                        <span class="badge badge--neutral">Recursive CTE</span>
                        <span class="badge badge--neutral">yield Generators</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Command Line Utilities Reference -->
        <section id="cli-tools">
            <div class="section-header">
                <h2 class="section-header__title">Kernel command line toolkit</h2>
                <span class="badge badge--info">CLI console</span>
            </div>

            <div class="command-terminal">
                <div class="command-terminal__header">
                    <span>magma-console &bull; debian-linux</span>
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
                        <span class="command-line__comment"># Pre-compiles route definitions into routes.cache.php</span>
                    </div>
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/audit_schema.php</span>
                        <span class="command-line__comment"># Audits multi-tenant foreign keys and DTO boundaries</span>
                    </div>
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/outbox_publisher.php</span>
                        <span class="command-line__comment"># Daemon worker polling PostgreSQL outbox events</span>
                    </div>
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php bin/worker.php</span>
                        <span class="command-line__comment"># Starts background Redis queue worker process</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="welcome-footer">
            <p>Magma Framework &bull; Educational Architecture Core &bull; Debian Linux &bull; Strict SOLID Architecture</p>
        </footer>
    </div>
</body>
</html>
