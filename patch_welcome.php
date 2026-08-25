<?php

$welcome = file_get_contents('app/views/welcome.php');

$grid_start = strpos($welcome, '<div class="syllabus-grid">');
$grid_end = strpos($welcome, '</section>', $grid_start);

$cards = <<<HTML
<div class="syllabus-grid">
                <!-- Chapter 01 -->
                <a href="/syllabus#module-1-introduction-philosophy" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 01</span><span class="badge badge--neutral">Philosophy</span></div>
                    <h3 class="module-card__title">Introduction & Philosophy</h3>
                    <p class="module-card__desc">The cost of "Magic", explicit engineering, and understanding the TSP domain platform vision.</p>
                </a>
                <!-- Chapter 02 -->
                <a href="/syllabus#module-2-the-request-lifecycle-front-controller" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 02</span><span class="badge badge--neutral">Kernel</span></div>
                    <h3 class="module-card__title">Request Lifecycle & Front Controller</h3>
                    <p class="module-card__desc">Bootstrapping, dual-mode kernels (HTTP vs CLI), and enforcing the public <code>www/</code> boundary.</p>
                </a>
                <!-- Chapter 03 -->
                <a href="/syllabus#module-3-the-dependency-injection-container-the-core" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 03</span><span class="badge badge--neutral">Core</span></div>
                    <h3 class="module-card__title">Dependency Injection Container</h3>
                    <p class="module-card__desc">Recursive reflection autowiring, singleton caching, and defending against circular deadlocks.</p>
                </a>
                <!-- Chapter 04 -->
                <a href="/syllabus#module-4-routing-the-http-request" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 04</span><span class="badge badge--neutral">Network</span></div>
                    <h3 class="module-card__title">Routing & The HTTP Request</h3>
                    <p class="module-card__desc">O(1) PCRE Regex compiled routing, the Middleware Onion architecture, and dual-mode compatibility.</p>
                </a>
                <!-- Chapter 05 -->
                <a href="/syllabus#module-5-controllers-services-the-business-logic" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 05</span><span class="badge badge--neutral">Logic</span></div>
                    <h3 class="module-card__title">Controllers & Services</h3>
                    <p class="module-card__desc">Declarative FormRequests, method injection, and enforcing the "Traffic Cop" rules for thin controllers.</p>
                </a>
                <!-- Chapter 06 -->
                <a href="/syllabus#module-6-data-persistence-multi-tenancy" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 06</span><span class="badge badge--neutral">Database</span></div>
                    <h3 class="module-card__title">Data Persistence & CQRS</h3>
                    <p class="module-card__desc">The Repository Pattern, strict CQRS segregation, SERIALIZABLE ACID compliance, and the LSP firewall.</p>
                </a>
                <!-- Chapter 07 -->
                <a href="/syllabus#module-7-views-and-the-template-engine" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 07</span><span class="badge badge--neutral">UI</span></div>
                    <h3 class="module-card__title">Views & Template Engine</h3>
                    <p class="module-card__desc">Logic-less views, multi-directory fallback, resolution caching, and O(N) DOM interpolation.</p>
                </a>
                <!-- Chapter 08 -->
                <a href="/syllabus#module-8-error-handling-logging" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 08</span><span class="badge badge--neutral">Diagnostics</span></div>
                    <h3 class="module-card__title">Error Handling & Logging</h3>
                    <p class="module-card__desc">Catching everything gracefully, logging infrastructure, and the Interactive Diagnostics Boundary.</p>
                </a>
                <!-- Chapter 09 -->
                <a href="/syllabus#module-9-the-final-polish-dtos-data-transfer-objects" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 09</span><span class="badge badge--neutral">Data</span></div>
                    <h3 class="module-card__title">DTOs (Data Transfer Objects)</h3>
                    <p class="module-card__desc">Crossing boundaries safely and Engine-Enforced Immutability via PHP 8.2 readonly modifiers.</p>
                </a>
                <!-- Chapter 10 -->
                <a href="/syllabus#module-10-the-evolution-domain-driven-design-ddd" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 10</span><span class="badge badge--neutral">Domain</span></div>
                    <h3 class="module-card__title">Domain-Driven Design (DDD)</h3>
                    <p class="module-card__desc">Transaction scripts vs rich models, and enforcing 100% pure domain entities with zero framework coupling.</p>
                </a>
                <!-- Chapter 11 -->
                <a href="/syllabus#module-11-decoupling-with-event-driven-architecture" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 11</span><span class="badge badge--neutral">Events</span></div>
                    <h3 class="module-card__title">Event-Driven Architecture</h3>
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
                    <h3 class="module-card__title">Automated Testing</h3>
                    <p class="module-card__desc">Testing as a byproduct of design, isolated unit tests, and comprehensive integration testing.</p>
                </a>
                <!-- Chapter 14 -->
                <a href="/syllabus#module-14-frontend-architecture-deep-freeze-css-layers" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 14</span><span class="badge badge--neutral">Frontend</span></div>
                    <h3 class="module-card__title">Frontend Architecture</h3>
                    <p class="module-card__desc">Deeply immutable ObservableStore, defensive garbage collection, and native CSS Cascade Layers.</p>
                </a>
                <!-- Chapter 15 -->
                <a href="/syllabus#module-15-security-big-o-analytics" class="module-card">
                    <div class="module-card__header"><span class="module-card__num">Chapter 15</span><span class="badge badge--neutral">Security</span></div>
                    <h3 class="module-card__title">Security & Big-O Analytics</h3>
                    <p class="module-card__desc">Pluggable Tenant Contexts, Static AST Boundary Auditing, and O(1) B-Tree Keyset Pagination.</p>
                </a>
            </div>
            <div style="margin-top:2rem;text-align:center;">
                <a href="/syllabus" class="btn btn--primary">View Full Masterclass Syllabus</a>
            </div>
        </div>
HTML;

$new_welcome = substr($welcome, 0, $grid_start) . $cards . "\n        " . substr($welcome, $grid_end);
file_put_contents('app/views/welcome.php', $new_welcome);
echo "welcome.php patched.\n";

