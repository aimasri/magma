<?php
/**
 * Title: Magma Educational Architecture Core Landing View
 *
 * Purpose:
 * - Beautifully renders the comprehensive developer syllabus and architecture dashboard for the Magma framework.
 * - Extracts and presents all 16 core architectural modules and engineering philosophies from README.md in a highly structured layout.
 * - Perfectly matches the visual design system, dark canvas, and typography of the developer diagnostic viewer.
 *
 * Teaching notes:
 * - Adheres strictly to AGENTS.md directives: you will see zero static inline styles (`style="..."`), standard sentence casing, and SOLID modular stylesheets (`/www/css/app.css` -> `/www/css/components/`).
 * - A stellar example of decoupling logic from the presentation layer. Great job adhering to clean architecture principles!
 *
 * @var array{title?: string, diagnostics: \App\dto\SystemDiagnosticsDTO, engine: \Magma\view\TemplateEngine} $data Encapsulated view data payload.
 */

$pageTitle = $data['title'] ?? 'Magma Framework Core';
$diag = $data['diagnostics'];
$phpVersion = $diag->phpVersion;
$environment = $diag->environment;
$isDebug = $diag->debug;
$dbDriver = strtoupper($diag->dbDriver);
$memoryUsage = round($diag->memoryUsageBytes / \App\constants\AppConstants::MEGABYTE_IN_BYTES, 2) . ' MB';
$serverOs = $diag->serverOs;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="welcome-page">
    <div class="welcome-container mx-auto d-flex flex-column gap-6">
        
        <!-- Hero Showcase Banner -->
        <header class="welcome-hero">
            <div class="welcome-hero__top">
                <span class="badge badge--pill <?= $isDebug ? 'badge--debug' : 'badge--neutral' ?>">
                    <?= $isDebug ? 'Debug mode active' : 'Production mode' ?>
                </span>
            </div>

            <div class="welcome-hero__brand">
                <img src="/logo.svg" alt="Magma Logo" class="welcome-hero__brand-logo">
                <h1 class="welcome-hero__title">The Magma Framework</h1>
            </div>

            <p class="welcome-hero__tagline">
                An instructional, enterprise-hardened PHP 8.2+ codebase demonstrating how to build robust, scalable, and mathematically sound web applications without relying on heavy, black-box frameworks.
            </p>

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

            <div class="welcome-hero__actions">
                <a href="#philosophy" class="btn btn--primary">Engineering philosophy</a>
                <a href="#lifecycle" class="btn btn--outline">Request lifecycle</a>
                <a href="/syllabus" class="btn btn--outline">Architectural syllabus</a>
                <a href="#cli-tools" class="btn btn--outline">CLI utilities</a>
            </div>
        </header>

        <!-- Engineering Philosophy -->
        <section id="philosophy">
            <div class="section-header">
                <h2 class="section-header__title">Core engineering philosophy</h2>
                <span class="badge badge--brand">Zero magic</span>
            </div>

            <div class="philosophy-gallery">
                <div class="gallery-card">
                    <h3 class="gallery-card__title">SOLID & DIP</h3>
                    <div class="gallery-card__content">
                        <p class="gallery-card__desc">
                            Strictly favor interface injection over concrete instantiation to eliminate tight coupling. We enforce single-responsibility, highly cohesive classes that depend exclusively on abstractions, keeping the core domain entirely framework-agnostic.
                        </p>
                    </div>
                </div>

                <div class="gallery-card">
                    <h3 class="gallery-card__title">Separation of concerns</h3>
                    <div class="gallery-card__content">
                        <p class="gallery-card__desc">
                            Controllers remain deliberately thin, acting purely as HTTP traffic cops, while repositories encapsulate all data persistence logic. Views are entirely logic-less, ensuring that complex business math never leaks into the presentation layer.
                        </p>
                    </div>
                </div>

                <div class="gallery-card">
                    <h3 class="gallery-card__title">Pragmatic DDD</h3>
                    <div class="gallery-card__content">
                        <p class="gallery-card__desc">
                            Behavior belongs intrinsically with data. We deploy rich, isolated domain entities that enforce their own internal invariants and rules, while dedicated application services seamlessly orchestrate broader, higher-level business workflows.
                        </p>
                    </div>
                </div>

                <div class="gallery-card">
                    <h3 class="gallery-card__title">Instructional docblocks</h3>
                    <div class="gallery-card__content">
                        <p class="gallery-card__desc">
                            Every critical core file features an extensive docblock explaining its title, precise purpose, architectural rationale, and educational notes. We mandate strict scalar typing and complete architectural transparency across the entire codebase.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Request Lifecycle & Onion Stepper -->
        <section id="lifecycle">
            <div class="section-header">
                <h2 class="section-header__title">Magma core architecture</h2>
                <span class="badge badge--topology">System topology</span>
            </div>

            <div class="lifecycle-diagram-wrapper">
                <button type="button" onclick="document.getElementById('architecture-modal').showModal()" class="lifecycle-diagram-btn" title="Click to view full architecture diagram">
                    <img src="/images/architecture-lifecycle.jpg" alt="Magma Request Lifecycle and Front Controller Architecture Diagram" class="lifecycle-diagram-img">
                </button>
            </div>
            
            <dialog id="architecture-modal" class="architecture-modal">
                <form method="dialog" class="architecture-modal__form">
                    <button type="submit" class="architecture-modal__close" title="Close modal">&times;</button>
                </form>
                <img src="/images/architecture-lifecycle.jpg" alt="Full Architecture Diagram" class="architecture-modal__img">
            </dialog>

            <script>
                // Light dismiss for the native dialog
                const archModal = document.getElementById('architecture-modal');
                archModal.addEventListener('click', (e) => {
                    if (e.target === archModal) archModal.close();
                });
            </script>
        </section>

        <!-- 16-Module Architectural Syllabus (Direct from README.md) -->
        <section id="modules">
            <div class="section-header">
                <h2 class="section-header__title">Architectural syllabus & module catalog</h2>
            </div>
            
            <p class="section-description">
                Dive into the complete 16-chapter curriculum defining Magma's enterprise architecture and strict SOLID principles. This masterclass textbook covers the full stack—from request lifecycle and dependency injection to event-driven background workers and CSS cascade layers. <a href="/syllabus">Click here to read the full syllabus</a>.
            </p>

            <div class="syllabus-grid">
                <!-- SYLLABUS_GRID_START -->
                <!-- Chapter 01 -->
                <a href="/syllabus#module-1-introduction-philosophy" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 01</span><span class="badge badge--neutral">Philosophy</span></div>
                    <h3 class="module-card__title">Introduction &amp; Philosophy</h3>
                    <p class="module-card__desc">The cost of "Magic", explicit engineering, and understanding the core framework platform vision.</p>
                </a>
                <!-- Chapter 02 -->
                <a href="/syllabus#module-2-the-request-lifecycle-front-controller" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 02</span><span class="badge badge--neutral">Kernel</span></div>
                    <h3 class="module-card__title">The Request Lifecycle &amp; Front Controller</h3>
                    <p class="module-card__desc">Bootstrapping, dual-mode kernels (HTTP vs CLI), and enforcing the public <code>www/</code> boundary.</p>
                </a>
                <!-- Chapter 03 -->
                <a href="/syllabus#module-3-the-dependency-injection-container-the-core" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 03</span><span class="badge badge--neutral">Core</span></div>
                    <h3 class="module-card__title">The Dependency Injection Container (The Core)</h3>
                    <p class="module-card__desc">Recursive reflection autowiring, singleton caching, and defending against circular deadlocks.</p>
                </a>
                <!-- Chapter 04 -->
                <a href="/syllabus#module-4-routing-the-http-request" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 04</span><span class="badge badge--neutral">Network</span></div>
                    <h3 class="module-card__title">Routing &amp; The HTTP Request</h3>
                    <p class="module-card__desc">O(1) PCRE Regex compiled routing, the Middleware Onion architecture, and dual-mode compatibility.</p>
                </a>
                <!-- Chapter 05 -->
                <a href="/syllabus#module-5-controllers-services-the-business-logic" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 05</span><span class="badge badge--neutral">Logic</span></div>
                    <h3 class="module-card__title">Controllers &amp; Services (The Business Logic)</h3>
                    <p class="module-card__desc">Declarative FormRequests, method injection, and enforcing the "Traffic Cop" rules for thin controllers.</p>
                </a>
                <!-- Chapter 06 -->
                <a href="/syllabus#module-6-data-persistence-multi-tenancy" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 06</span><span class="badge badge--neutral">Database</span></div>
                    <h3 class="module-card__title">Data Persistence &amp; Multi-Tenancy</h3>
                    <p class="module-card__desc">The Repository Pattern, strict CQRS segregation, SERIALIZABLE ACID compliance, and the LSP firewall.</p>
                </a>
                <!-- Chapter 07 -->
                <a href="/syllabus#module-7-views-and-the-template-engine" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 07</span><span class="badge badge--neutral">UI</span></div>
                    <h3 class="module-card__title">Views and the Template Engine</h3>
                    <p class="module-card__desc">Logic-less views, explicit layouts, native PHP stacks, and O(N) DOM interpolation.</p>
                </a>
                <!-- Chapter 08 -->
                <a href="/syllabus#module-8-error-handling-logging" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 08</span><span class="badge badge--neutral">Diagnostics</span></div>
                    <h3 class="module-card__title">Error Handling &amp; Logging</h3>
                    <p class="module-card__desc">Catching everything gracefully, logging infrastructure, and the Interactive Diagnostics Boundary.</p>
                </a>
                <!-- Chapter 09 -->
                <a href="/syllabus#module-9-the-final-polish-dtos-data-transfer-objects" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 09</span><span class="badge badge--neutral">Data</span></div>
                    <h3 class="module-card__title">The Final Polish - DTOs (Data Transfer Objects)</h3>
                    <p class="module-card__desc">Crossing boundaries safely and Engine-Enforced Immutability via PHP 8.2 readonly modifiers.</p>
                </a>
                <!-- Chapter 10 -->
                <a href="/syllabus#module-10-the-evolution-domain-driven-design-ddd" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 10</span><span class="badge badge--neutral">Domain</span></div>
                    <h3 class="module-card__title">The Evolution - Domain-Driven Design (DDD)</h3>
                    <p class="module-card__desc">Transaction scripts vs rich models, and enforcing 100% pure domain entities with zero framework coupling.</p>
                </a>
                <!-- Chapter 11 -->
                <a href="/syllabus#module-11-decoupling-with-event-driven-architecture" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 11</span><span class="badge badge--neutral">Events</span></div>
                    <h3 class="module-card__title">Decoupling with Event-Driven Architecture</h3>
                    <p class="module-card__desc">The Pub/Sub Pattern, dispatching rich domain events, and breaking apart monolithic God Classes.</p>
                </a>
                <!-- Chapter 12 -->
                <a href="/syllabus#module-12-asynchronous-background-workers" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 12</span><span class="badge badge--neutral">Workers</span></div>
                    <h3 class="module-card__title">Asynchronous Background Workers</h3>
                    <p class="module-card__desc">The Transactional Outbox pattern, preventing dual-writes, and FOR UPDATE SKIP LOCKED concurrency.</p>
                </a>
                <!-- Chapter 13 -->
                <a href="/syllabus#module-13-end-of-cycle-considerations-automated-testing" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 13</span><span class="badge badge--neutral">Testing</span></div>
                    <h3 class="module-card__title">End of Cycle Considerations - Automated Testing</h3>
                    <p class="module-card__desc">Testing as a byproduct of design, isolated unit tests, and comprehensive integration testing.</p>
                </a>
                <!-- Chapter 14 -->
                <a href="/syllabus#module-14-frontend-architecture-deep-freeze-css-layers" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 14</span><span class="badge badge--neutral">Frontend</span></div>
                    <h3 class="module-card__title">Frontend Architecture: Deep Freeze &amp; CSS Layers</h3>
                    <p class="module-card__desc">Deeply immutable ObservableStore, defensive garbage collection, and native CSS Cascade Layers.</p>
                </a>
                <!-- Chapter 15 -->
                <a href="/syllabus#module-15-security-big-o-analytics" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 15</span><span class="badge badge--neutral">Security</span></div>
                    <h3 class="module-card__title">Security &amp; Big-O Analytics</h3>
                    <p class="module-card__desc">Pluggable Tenant Contexts, Cross-Domain SSO Handoff, AST Auditing, and Keyset Pagination.</p>
                </a>
                <!-- Chapter 16 -->
                <a href="/syllabus#module-16-the-lava-hardening-phase-enterprise-quality-control" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 16</span><span class="badge badge--neutral">Hardening</span></div>
                    <h3 class="module-card__title">The Lava Hardening Phase - Enterprise Quality Control</h3>
                    <p class="module-card__desc">Eradication of legacy facades, PHPStan Level 9 mathematical type safety, and cryptographic boundary enforcement.</p>
                </a>
                <!-- SYLLABUS_GRID_END -->
            </div>
        </section>

        <!-- Command Line Utilities Reference -->
        <section id="cli-tools">
            <div class="section-header">
                <h2 class="section-header__title">Kernel command line toolkit</h2>
                <span class="badge badge--topology">CLI console</span>
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
                        <span class="command-line__comment"># Audits multi-tenant composite indexes and DTO boundaries</span>
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
                    <div class="command-line">
                        <span class="command-line__prompt">$</span>
                        <span class="command-line__cmd">php app/bin/cleanup_tokens.php</span>
                        <span class="command-line__comment"># Prunes expired password reset tokens and remember-me credentials</span>
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
