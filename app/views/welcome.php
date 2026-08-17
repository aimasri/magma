<?php
/**
 * Title: Magma Educational Architecture Core Landing View
 *
 * Purpose:
 * - Renders the comprehensive developer syllabus and architecture dashboard for the Magma framework.
 * - Extracts and presents all 12 core architectural modules and engineering philosophies from README.md.
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

        <!-- 12-Module Architectural Syllabus (Direct from README.md) -->
        <section id="modules">
            <div class="section-header">
                <h2 class="section-header__title">Architectural syllabus & module catalog</h2>
                <span class="badge badge--neutral">12 core chapters</span>
            </div>

            <div class="syllabus-grid">
                <!-- Chapter 01 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 01</span>
                        <span class="badge badge--neutral">Philosophy</span>
                    </div>
                    <h3 class="module-card__title">Architectural Philosophy</h3>
                    <p class="module-card__desc">
                        Zero black-box magic. Explicit dependency injection, strict scalar typing, defensive exception isolation, and educational docblocks across all kernel files.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">SOLID</span>
                        <span class="badge badge--neutral">Strict Types</span>
                        <span class="badge badge--neutral">Pragmatic DDD</span>
                    </div>
                </div>

                <!-- Chapter 02 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 02</span>
                        <span class="badge badge--neutral">Lifecycle</span>
                    </div>
                    <h3 class="module-card__title">Request Lifecycle & Front Controller</h3>
                    <p class="module-card__desc">
                        Funnels all traffic through <code>www/index.php</code>. Dual-mode kernel supports both standard HTTP execution (<code>Application::run</code>) and headless testing (<code>Application::handle</code>).
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Front Controller</span>
                        <span class="badge badge--neutral">Buffer Isolation</span>
                        <span class="badge badge--neutral">Headless Kernel</span>
                    </div>
                </div>

                <!-- Chapter 03 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 03</span>
                        <span class="badge badge--neutral">IoC Container</span>
                    </div>
                    <h3 class="module-card__title">Dependency Injection Container</h3>
                    <p class="module-card__desc">
                        Recursive reflection autowiring with in-memory metadata caching. Autoloader delegation in <code>Container::has()</code> and dynamic instantiation via <code>makeWithArgs()</code>.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Reflection</span>
                        <span class="badge badge--neutral">makeWithArgs()</span>
                        <span class="badge badge--neutral">Service Providers</span>
                    </div>
                </div>

                <!-- Chapter 04 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 04</span>
                        <span class="badge badge--neutral">Pipeline</span>
                    </div>
                    <h3 class="module-card__title">Pipeline & Middleware Onion</h3>
                    <p class="module-card__desc">
                        Dual-mode middleware onion supporting closures, callable classes, and PSR-15 middlewares. Inward/outward request wrapping with CSRF and rate-limiting guards.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">PSR-15</span>
                        <span class="badge badge--neutral">Tenant Context</span>
                        <span class="badge badge--neutral">Security Headers</span>
                    </div>
                </div>

                <!-- Chapter 05 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 05</span>
                        <span class="badge badge--neutral">Routing</span>
                    </div>
                    <h3 class="module-card__title">PCRE Routing & Thin Controllers</h3>
                    <p class="module-card__desc">
                        Immutable <code>Route</code> Value Objects and compiled regular expression tree routing. Reflection-based action auto-wiring and declarative <code>FormRequest</code> validation injection.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">FastRoute PCRE</span>
                        <span class="badge badge--neutral">FormRequest</span>
                        <span class="badge badge--neutral">routes.cache.php</span>
                    </div>
                </div>

                <!-- Chapter 06 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 06</span>
                        <span class="badge badge--neutral">Persistence</span>
                    </div>
                    <h3 class="module-card__title">Data Persistence: CQRS Repositories</h3>
                    <p class="module-card__desc">
                        Segregated base repositories (<code>$dbRead</code> vs <code>$dbWrite</code>). PostgreSQL double quoting, atomic <code>RETURNING id</code> insertion, and savepoint transaction nesting (<code>SAVEPOINT trans_N</code>).
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">CQRS Split</span>
                        <span class="badge badge--neutral">RETURNING id</span>
                        <span class="badge badge--neutral">Savepoints</span>
                    </div>
                </div>

                <!-- Chapter 07 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 07</span>
                        <span class="badge badge--neutral">Domain</span>
                    </div>
                    <h3 class="module-card__title">Domain Logic, FSM & Strategy Patterns</h3>
                    <p class="module-card__desc">
                        Pragmatic DDD with skinny entities managing internal invariants. Finite State Machine engine with terminal state invariants and container-aware Polymorphic Strategy Registries.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Skinny Entities</span>
                        <span class="badge badge--neutral">State Machine</span>
                        <span class="badge badge--neutral">Strategy Registry</span>
                    </div>
                </div>

                <!-- Chapter 08 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 08</span>
                        <span class="badge badge--neutral">Views</span>
                    </div>
                    <h3 class="module-card__title">Decoupled Template Engine & Presenters</h3>
                    <p class="module-card__desc">
                        Namespaced template loading (<code>Services::index</code>) with in-memory path caching. View composers, ViewModels, and presenters eliminating business logic from templates.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Namespaces</span>
                        <span class="badge badge--neutral">View Composers</span>
                        <span class="badge badge--neutral">Asset Versioning</span>
                    </div>
                </div>

                <!-- Chapter 09 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 09</span>
                        <span class="badge badge--neutral">Frontend</span>
                    </div>
                    <h3 class="module-card__title">Frontend: Modular ES6 & CSS Layers</h3>
                    <p class="module-card__desc">
                        Reactive <code>ObservableStore</code>, WeakSet DOM event registries with AbortController signals, zero-dependency WYSIWYG editor, and native CSS Cascade Layers.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Vanilla ES6</span>
                        <span class="badge badge--neutral">WeakSet Events</span>
                        <span class="badge badge--neutral">Cascade Layers</span>
                    </div>
                </div>

                <!-- Chapter 10 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 10</span>
                        <span class="badge badge--neutral">Outbox & Queues</span>
                    </div>
                    <h3 class="module-card__title">Transactional Outbox & Event Processing</h3>
                    <p class="module-card__desc">
                        Atomically records domain events within database transactions. PostgreSQL <code>FOR UPDATE SKIP LOCKED</code> queue publisher daemon and idempotent projection guards.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">SKIP LOCKED</span>
                        <span class="badge badge--neutral">Event Dispatcher</span>
                        <span class="badge badge--neutral">Projection Guards</span>
                    </div>
                </div>

                <!-- Chapter 11 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 11</span>
                        <span class="badge badge--neutral">Security</span>
                    </div>
                    <h3 class="module-card__title">Multi-Tenant Security & AST Auditing</h3>
                    <p class="module-card__desc">
                        Pluggable <code>TenantContext</code> scoping across queries and requests. Static AST boundary linter (<code>bin/audit_schema.php</code>) and tokenized file/S3 storage abstraction.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Tenant Scoping</span>
                        <span class="badge badge--neutral">AST Linter</span>
                        <span class="badge badge--neutral">S3 & Storage</span>
                    </div>
                </div>

                <!-- Chapter 12 -->
                <div class="module-card">
                    <div class="module-card__header">
                        <span class="module-card__num">Chapter 12</span>
                        <span class="badge badge--neutral">Observability</span>
                    </div>
                    <h3 class="module-card__title">Optimizations & Developer Diagnostics</h3>
                    <p class="module-card__desc">
                        Keyset B-Tree pagination ($O(1)$ cursor seeking), recursive multi-root CTEs, streaming generators (<code>yield</code>), and interactive developer stack trace diagnostics.
                    </p>
                    <div class="module-card__tags">
                        <span class="badge badge--neutral">Keyset Seeking</span>
                        <span class="badge badge--neutral">Recursive CTE</span>
                        <span class="badge badge--neutral">Debug Presenter</span>
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
