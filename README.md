# Magma Framework: The Educational Architecture Core

> **💎 PRISTINE CHECKPOINT (MAIN BRANCH)**  
> The most pristine, mathematically pure version of the Magma core architecture development is permanently captured at commit `f4278368dc48bfa9801fe804b149201cfff6a871` (ID: `f427836`). If you ever need to return to the flawless core before experimental features are added, checkout this reference!

> **🌋 LAVA CHECKPOINT (TESTING INFRASTRUCTURE)**  
> The exhaustive Lava testing infrastructure (MockClocks, Agnostic Factories, CI/CD pipelines, AST Static Analysis, and Headless HTTP Testing) alongside its strict Git Merge Protections is securely captured at commit `e42cd5135345b9fe2480de0b1cc90208d5719a94` (ID: `e42cd51`).  
> **Final Test Results on this Commit:** 7/7 Database & HTTP Integration Tests passed (100%), 20 assertions validated perfectly in <150ms. Zero DB pollution across all rollbacks.  

> **🔥 Recent Core Hardening (The Lava Phase & Concurrency Audits)**  
> The framework has recently undergone a rigorous architectural and security hardening phase (originally developed on the `lava` branch and now merged directly into `main`). Key upgrades include:
> - **100% PHPStan Level 9 Compliance:** All types are mathematically proven, explicit array shapes are enforced, and ambiguous `mixed` types have been systematically eliminated across all 200+ framework files.
> - **Zero Legacy Artifacts:** Complete removal of all backward-compatibility facades, legacy middleware, and primitive routing tuples. The framework strictly enforces modern object-oriented interfaces.
> - **Concurrency & Race Condition Eradication:** A multi-pass concurrency audit was conducted, eradicating TOCTOU projection bugs, fixing Redis `BLPOP` daemon deadlocks, isolating Postgres transactions from network I/O, preventing cross-tenant rate limit starvation, stopping dual-write anti-patterns, and implementing atomic locks to prevent Cache Stampedes.
> - **Automated Boundary Enforcement:** AST-level validation guarantees zero direct superglobal breaches and explicit `tenant_id` database scoping.
> - **Cryptographic Hardening:** Upgraded core authentication to utilize Argon2id hashing with transparent on-the-fly rehashing, alongside strict `Permissions-Policy` security headers.
> - **Zero Runtime Dependencies:** The production runtime remains 100% dependency-free, with all static analysis and testing tools securely relegated to `require-dev`.


Welcome to the Magma Framework source code. This repository is intentionally designed as an **instructional, enterprise-hardened codebase**. It demonstrates how to build a robust, scalable, and mathematically sound web application using modern PHP 8.2+ and Vanilla ES6 JavaScript **without relying on heavy, black-box frameworks** like Laravel or Symfony.

Following a rigorous multi-pass architectural hardening and upstream consolidation phase, this codebase represents the pinnacle of clean architecture, strict SOLID principles, and defensive programming for high-concurrency, multi-tenant SaaS environments.

By exploring this codebase, you will learn the fundamental architectural patterns that power modern enterprise web systems, with thorough, docblock-level explanations of *how* and *why* every component is built. This README serves as the technical reference for the framework's features. For the definitive, 15-module step-by-step Masterclass on how this architecture is constructed, please refer to the `textbook.md` file (or view the `/syllabus` route in the browser).

---

## Table of Contents
0. [The Masterclass Textbook (Syllabus)](#00-the-masterclass-textbook-syllabus)
1. [Introduction & Architectural Philosophy](#01-introduction--architectural-philosophy)
2. [The Request Lifecycle & Front Controller](#02-the-request-lifecycle--front-controller)
3. [The Dependency Injection Container](#03-the-dependency-injection-container)
4. [The Pipeline & Middleware Onion Architecture](#04-the-pipeline--middleware-onion-architecture)
5. [PCRE Routing & Thin Controllers](#05-pcre-routing--thin-controllers)
6. [Data Persistence: CQRS & The Repository Pattern](#06-data-persistence-cqrs--the-repository-pattern)
7. [Domain Logic, State Machines & Strategy Patterns](#07-domain-logic-state-machines--strategy-patterns)
8. [The Decoupled Template Engine & Presenter Layer](#08-the-decoupled-template-engine--presenter-layer)
9. [Frontend Architecture: Modular Vanilla ES6 & CSS Cascade Layers](#09-frontend-architecture-modular-vanilla-es6--css-cascade-layers)
10. [Transactional Outbox & Event-Driven Processing](#10-transactional-outbox--event-driven-processing)
11. [Multi-Tenant Security, Storage & AST Boundary Auditing](#11-multi-tenant-security-storage--ast-boundary-auditing)
12. [High-Performance Optimizations & Production Diagnostics](#12-high-performance-optimizations--production-diagnostics)
13. [Kernel CLI Toolkit Reference](#13-kernel-cli-toolkit-reference)

---

## 00. The Masterclass Textbook (Syllabus)

While this README serves as a high-level feature overview, the true educational core of Magma is the **Masterclass Textbook**. 

Located in `textbook.md` (and beautifully rendered on the `/syllabus` route of the application), this 1,800+ line document is a comprehensive, 15-module course that teaches you exactly *why* and *how* to build this architecture from scratch. It covers everything from Dependency Injection and Dual-Mode Kernels, to O(1) Regex Routing, CQRS Persistence, and Static AST Auditing. 

**It is highly recommended you read the textbook first to understand the philosophy behind the code.**

---

## 01. Introduction & Architectural Philosophy

**The Magma Framework:** Magma is an explicit, enterprise-grade application framework engineered from the ground up to be highly adaptable, supporting both massive, multi-tenant cloud environments and isolated, high-performance applications without black-box magic or framework lock-in.

**The Engineering Philosophy:** Modern web frameworks frequently conceal immense architectural complexity behind "magic" static facades and heavy runtime dependencies. Magma intentionally eliminates black-box magic:

* **SOLID Principles:** Every class adheres strictly to the Single Responsibility Principle (SRP) and Dependency Inversion Principle (DIP). Classes declare explicit interface contracts and receive dependencies via constructor injection.
* **Separation of Concerns (SoC):** Controllers never query SQL; views never perform domain calculations; repositories isolate data access mechanics completely.
* **Pragmatic Domain-Driven Design (DDD):** *Behavior belongs with data*. We utilize "skinny" domain entities that manage their own state invariants and internal sanitization, while Services act strictly as thin workflow orchestrators.
* **CQRS Boundary Segregation:** Database access is segregated at the connection and repository boundary: read replicas (`$dbRead`) handle querying and keyset pagination, while write masters (`$dbWrite`) execute state mutations within managed transactions.
* **Standardized Instructional Docblocks:** Every core file contains a standardized docblock explaining its *Title*, *Purpose*, *Why this design was chosen*, and specific *Teaching notes*. Methods describe their step-by-step *Execution Flow* and architectural reasoning.
* **Strict Scalar Typing:** All methods enforce scalar type declarations and explicit return types (`declare(strict_types=1)` in PHP), ensuring runtime data predictability and eliminating silent type-coercion bugs.

---

## 02. The Request Lifecycle & Front Controller

The application implements the **Front Controller** pattern. Every HTTP request made to the server is funneled through a single entry point: `www/index.php`.

```
                  ┌────────────────────────────────────────────────────────┐
                  │                 HTTP Request (Client)                  │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │          Front Controller (www/index.php)              │
                  │   - PSR-4 Autoloading & Config::initialize()           │
                  │   - Service Container & Provider Registration          │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │               Application::handle() Kernel             │
                  │   - Output Buffering Isolation (ob_start)              │
                  │   - Content-Negotiated Error Handling Boundary         │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │              Middleware Pipeline (Onion)               │
                  │   - TenantSecurityMiddleware (Multi-Tenant Isolation)  │
                  │   - CsrfMiddleware (State Mutation Token Guard)        │
                  │   - RateLimitMiddleware (Atomic Redis Guard)           │
                  │   - SecurityHeadersMiddleware (CSP & HSTS)             │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │               Router & RouteDispatcher                 │
                  │   - FastRoute PCRE Regex Match / routes.cache.php      │
                  │   - Reflection Action Parameter Auto-Wiring            │
                  │   - FormRequest Declarative Validation Injection       │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │                Thin Controller Action                  │
                  │   - Delegates to Domain Services & CQRS Repositories   │
                  │   - Returns Response (HTML View, JSON, Redirect)       │
                  └────────────────────────────────────────────────────────┘
```

### Architectural Key Points:
* **Sealed Web Root:** The `www/` directory is the *only* directory exposed to the web server. All framework logic, domain engines, views, and configuration files reside safely outside the document root in `magma/` and `app/`.
* **Dual-Mode Kernel (`Application::handle` vs `Application::run`):** `Application::handle(RequestInterface $request): Response` dispatches requests through the middleware onion and router without outputting headers or echoing markup. This enables headless functional testing, CLI request simulation, and async worker loops, while `Application::run()` provides the standard HTTP exit.

---

## 03. The Dependency Injection Container

At the foundation of Magma is `magma/container/Container.php`. Rather than instantiating classes with scattered `new ClassName()` calls (which creates tight coupling and impedes automated testing), classes declare their dependencies in constructors.

### Key Educational Concepts:
* **Reflection Autowiring with In-Memory Caching:** The container inspects constructor parameter types via PHP's `ReflectionClass` and resolves the dependency graph recursively. Resolved reflection instances are cached in memory to eliminate repeated reflection overhead.
* **Autoloader Delegation:** `Container::has($id)` delegates to `class_exists($id, true)` and `interface_exists($id, true)`, ensuring lazy-loaded PSR-4 classes are resolved seamlessly without pre-registration boilerplate.
* **Dynamic Instantiation (`makeWithArgs`):** The `Container::makeWithArgs(string $class, array $args): object` method allows runtime arguments (such as entity IDs or runtime flags) to be combined dynamically with container-resolved service dependencies.
* **Nullable Provider Dependencies:** Mitigates circular DI resolution deadlocks (where a provider needs a query repository, which in turn needs the provider to boot) by utilizing optional/nullable `?TenantContext` setter injection.
* **O(1) LRU Eviction caching:** Long-running event loops (like Swoole or RoadRunner) are protected against memory leaks via a strict 1000-item Least-Recently-Used (LRU) limit on `self::$classExistsCache`, executing in O(1) time complexity using `array_key_first` instead of O(N) `array_shift()`.
* **Modular Service Providers:** Core service registrations are organized into modular providers (`CoreServiceProvider`, `DatabaseServiceProvider`, `RoutingServiceProvider`, `RepositoryServiceProvider`, `DomainServiceProvider`, `HttpServiceProvider`, `EventServiceProvider`).

---

## 04. The Pipeline & Middleware Onion Architecture

Before an incoming request reaches a Controller, it passes through the `Pipeline` processor (`magma/pipeline/Pipeline.php`) implementing the **Onion Architecture**.

### Key Pipeline Components:
* **Dual-Mode Middleware Compatibility:** The `Pipeline` natively executes standard closures (`$pipe($passable, $next)`), object middlewares (`process`, `handle`, `__invoke`), and PSR-15 middlewares via [`Psr15Adapter.php`](./magma/middleware/Psr15Adapter.php).
* **`TenantSecurityMiddleware`:** Ensures absolute multi-tenant data isolation. Retrieves the authenticated `AuthUser` domain entity and binds the active `TenantContext` to the HTTP Request and database connection context.
* **`CsrfMiddleware`:** Generates and validates cryptographically secure CSRF tokens for state-mutating requests (`POST`, `PUT`, `DELETE`). Pauses token rotation during rapid AJAX interactions to prevent false-positive 403 collisions.
* **`SecurityHeadersMiddleware`:** Enforces strict HTTP security headers (HSTS, X-Content-Type-Options, X-Frame-Options) and configurable Content Security Policies (CSP) supporting external font registries and script nonces.

---

## 05. PCRE Routing & Thin Controllers

The `Router` (`magma/routing/Router.php`) compiles and maps URI patterns to Controller actions.

### Key Architectural Concepts:
* **Immutable Route Value Objects:** Numeric route tuple arrays are replaced with strongly-typed, immutable [`Route.php`](./magma/routing/Route.php) and [`RouteDefinition.php`](./magma/routing/RouteDefinition.php) Value Objects (`getMethod()`, `getUri()`, `getHandler()`, `getMiddleware()`, `getName()`).
* **FastRoute-Style PCRE Compiler:** [`RouteCompiler.php`](./magma/routing/RouteCompiler.php) compiles dynamic route parameter patterns (`/users/{id:\d+}`) into chunked regular expression trees, enabling $O(1)$ route resolution.
* **OPcache Manifest Pre-Compilation (`bin/cache_routes.php`):** Compiles all application route definitions into an OPcache-cached PHP manifest (`magma/config/routes.cache.php`), eliminating regex compilation overhead on production requests.
* **Method Injection & Zero-God-Class Controllers:** Magma strictly avoids "God Classes" like a bloated `BaseController`. Controllers declare dependencies directly on their route methods via Method Injection. The `RouteParameterResolver` uses reflection to dynamically wire instances from the container straight into the action.
* **Automated `FormRequest` Validation & PRG Redirects:** If a route action type-hints a `ValidatableRequestInterface` (e.g. `LoginRequest`), the resolver intercepts it and executes `validate()`. If validation fails, it catches the exception, flashes errors to the session, and throws an `HttpResponseException` to seamlessly redirect back to the form (Post-Redirect-Get pattern) without ever reaching the controller.
* **`HtmlResponseBuilder` Encapsulation:** Controllers no longer manage CSRF states or template engines. They simply method-inject `HtmlResponseBuilderInterface $html` to render views, keeping the presentation boundary ultra-thin.
* **Bidirectional Named Routes:** [`UrlGenerator::route(string $name, array $params)`](./magma/routing/UrlGenerator.php) provides type-safe reverse URL generation across views and controllers.
* **Thin Controller Pattern:** Controllers act strictly as traffic directors. They collect request context, validate DTOs, invoke Domain Services, and return an HTTP `Response` (HTML view, JSON envelope, or Redirect).

---

## 06. Data Persistence: CQRS & The Repository Pattern

Data access is entirely encapsulated using the **Repository Pattern** and **CQRS Boundary Segregation**.

```
                  ┌────────────────────────────────────────────────────────┐
                  │                 Application Service Layer              │
                  └───────────────┬────────────────────────┬───────────────┘
                                  │                        │
                 (Read Operations)│                        │(Write Operations)
                                  ▼                        ▼
                  ┌────────────────────────┐      ┌────────────────────────┐
                  │ AbstractQueryRepository│      │AbstractCommandRepos... │
                  │  - Uses $dbRead (Repl) │      │  - Uses $dbWrite (Mstr)│
                  │  - Keyset Seek (Cursor)│      │  - Managed Transactions│
                  │  - Recursive CTE Tree  │      │  - RETURNING id Insert │
                  └────────────────────────┘      └────────────────────────┘
```

### Key Persistence Patterns:
* **Segregated Base Repositories:** [`AbstractQueryRepository.php`](./magma/models/AbstractQueryRepository.php) injects the read-replica PDO connection (`$dbRead`), while [`AbstractCommandRepository.php`](./magma/models/AbstractCommandRepository.php) injects the write-master PDO connection (`$dbWrite`).
* **SERIALIZABLE Isolation & Race-Condition Prevention:** The `DatabaseTransactionManager` explicitly forces PostgreSQL into `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE` upon beginning a root transaction. It also intercepts read-replica queries during an active transaction and routes them to the write master, eliminating "phantom reads" and replication-lag dirty data.
* **PostgreSQL Native Identifier Quoting & Identity:** All SQL queries use PostgreSQL-standard double quotes (`"`). The [`PostgreSqlInsertBuilder.php`](./magma/database/PostgreSqlInsertBuilder.php) appends native `RETURNING id` clauses to atomically extract inserted primary keys in a single roundtrip.
* **Savepoint Nested Transactions:** [`DatabaseTransactionManager.php`](./magma/database/DatabaseTransactionManager.php) tracks transaction depth and issues `SAVEPOINT trans_{N}`, `RELEASE SAVEPOINT`, and `ROLLBACK TO SAVEPOINT trans_{N}` commands, preventing PostgreSQL aborted transaction block failures during nested operations.
* **Constant-Time B-Tree Keyset Pagination:** [`MultiTenantKeysetQueryBuilder.php`](./magma/database/MultiTenantKeysetQueryBuilder.php) and [`AbstractKeysetRepository.php`](./magma/models/AbstractKeysetRepository.php) replace expensive SQL `OFFSET` scans with indexed cursor seek conditions (`WHERE id > :cursor_last_id`).
* **LSP Firewall:** Strict segregation of internal framework mutating methods (`executeUpdate`, `executeDelete`) from common domain interface terminologies (`update`, `delete`) to prevent fatal Liskov Substitution Principle signature collisions.
* **Batch Recursive CTE Optimizer:** [`CteQueryBuilder.php`](./magma/database/CteQueryBuilder.php) and [`BatchHierarchicalLoader.php`](./magma/database/BatchHierarchicalLoader.php) transform single-root recursive queries into batched multi-root CTEs (`WHERE root_id IN (?)`), eliminating N+1 query storms across hierarchical tree models.
* **In-Memory Eager Relationship Batch Loader:** [`EagerRelationBatchLoader.php`](./magma/database/EagerRelationBatchLoader.php) maps 1-to-many child collections across parent entity arrays in memory in $O(1)$ time.
* **CLI Schema Migrations:** [`SchemaInitializer.php`](./magma/database/SchemaInitializer.php) and [`bin/migrate.php`](./bin/migrate.php) discover and execute versioned SQL migrations outside the HTTP request lifecycle.

---

## 07. Domain Logic, State Machines & Strategy Patterns

Core business rules are structured using **Pragmatic Domain-Driven Design (DDD)**:

* **100% Pure Domain Entities:** Encapsulate domain data and self-contained invariants (e.g. `AuthUser`, `Review`). Domain entities are completely agnostic of application-layer DTOs; they only accept raw scalars in their constructors to guarantee absolute boundary segregation.
* **Engine-Enforced Immutability:** All Data Transfer Objects (DTOs) utilize PHP 8.2's native `readonly class` modifiers to lock down state perfectly at the engine layer, preventing dynamic property injection.
* **Thin Orchestrating Services:** Domain Services inject repositories strictly through Interfaces and coordinate multi-step workflows, transaction boundaries, and event publishing.
* **Finite State Machine Engine:** [`StateMachine.php`](./magma/domain/StateMachine.php) and [`AbstractStateTransition.php`](./magma/domain/AbstractStateTransition.php) enforce uppercase state normalization, allowed transition path graphs, and terminal state invariants.
* **Polymorphic Strategy Registry:** [`StrategyRegistry.php`](./magma/services/StrategyRegistry.php) implements a container-aware registry resolving dynamic domain algorithms (pricing, margin scoring, taxation) by key with runtime validation.
* **Deterministic Time Abstraction:** [`ClockInterface.php`](./magma/contracts/ClockInterface.php) and `SystemClock.php` replace hidden global state (`time()`, `NOW()`) across all core services, enabling flawless, time-travel unit testing for token expiration and session invalidation.

---

## 08. The Decoupled Template Engine & Presenter Layer

The presentation layer is rendered using a decoupled, secure `TemplateEngine` (`magma/view/TemplateEngine.php`):

* **Namespaced Template Loading:** [`LocalFileViewLoader.php`](./magma/view/LocalFileViewLoader.php) implements `ViewLoaderInterface` and supports namespaced template paths (e.g. `Services::index`, `Menu::item_card`, `App::500`) with in-memory path existence caching.
* **Multi-Directory Fallback & Resolution Caching:** Intelligently searches across `views/layouts` and `views/partials` with an in-memory `$resolvedLayoutCache` to eliminate O(N) `$disk->file_exists()` bottleneck checks per request.
* **Decoupled Layouts and Partials:** Dedicated `layoutPath` and `partialsPath` properties prevent layout recursion and isolate partial rendering.
* **View Composers & Presenters:** [`ViewComposerRegistry.php`](./magma/views/ViewComposerRegistry.php), [`FormViewComposer.php`](./magma/view/FormViewComposer.php), and [`AbstractPresenter.php`](./magma/presenters/AbstractPresenter.php) eliminate business logic from views and support unified "Create" vs "Edit" view modes.
* **Deterministic Asset Versioning:** [`AssetVersionManager.php`](./magma/assets/AssetVersionManager.php) and [`ViewHelper::asset()`](./magma/views/ViewHelper.php) generate cache-busting URLs using content hashing or release tags.
* **Output Buffer Isolation:** Nested output buffering (`ob_start` / `ob_get_clean`) guarantees that if a template fails midway through execution, corrupted partial markup is swallowed and never leaked to the client.

---

## 09. Frontend Architecture: Modular Vanilla ES6 & CSS Cascade Layers

The client-side architecture follows the same decoupling principles as the backend, eschewing monolithic JavaScript frameworks in favor of clean, optimized Vanilla ES6:

* **Deeply Immutable Reactive State Store:** [`ObservableStore.js`](./www/js/ObservableStore.js) implements the Observer Pattern with automatic subscription lifecycle teardown and a recursive `_deepFreeze()` algorithm that secures deeply nested objects from rogue mutations.
* **Garbage-Collected Event Bus:** Global document listeners feature defensive `isConnected` checks to gracefully unbind themselves if a component is ripped from the DOM dynamically, preventing zombie memory leaks.
* **Declarative Action Routing:** [`MagmaActionDispatcher.js`](./www/js/MagmaActionDispatcher.js) and [`EventDelegator.js`](./www/js/EventDelegator.js) dispatch `data-action="entity:action"` attributes to ES6 controller handlers.
* **O(N) Template Interpolation:** [`TemplateEngine.js`](./www/js/TemplateEngine.js) clones native HTML5 `<template>` fragments securely. It achieves O(N) Big-O time complexity by temporarily detaching nested `[data-loop]` nodes via comment placeholders before evaluating outer directives, preventing O(N*M) redundant DOM scans.
* **DOM Sanitizer & WYSIWYG Editor:** [`DomSanitizer.js`](./www/js/DomSanitizer.js) enforces tag/attribute allowlists for rich-text inputs, paired with zero-dependency [`MagmaEditor.js`](./www/js/MagmaEditor.js).
* **Enhanced Combobox:** [`MagmaCombobox.js`](./www/js/MagmaCombobox.js) supports debounced asynchronous queries, wildcard attribute propagation, and multi-line selected card rendering.
* **CSS Cascade Layers:** [`utilities.css`](./www/css/components/utilities.css) and [`app.css`](./www/css/app.css) enforce native CSS Cascade Layers (`@layer reset, tokens, components, utilities, states;`), permanently eliminating `!important` specificity collisions.

---

## 10. Transactional Outbox & Event-Driven Processing

To ensure high availability and prevent blocking synchronous HTTP requests during heavy background tasks, Magma implements the **Transactional Outbox Pattern**:

* **PostgreSQL Concurrent Outbox:** [`OutboxJobRepository.php`](./magma/database/OutboxJobRepository.php) records domain events atomically within the database transaction and fetches pending jobs using PostgreSQL's native `FOR UPDATE SKIP LOCKED` locking primitive.
* **Atomic Event Dispatching:** Event dispatching is encapsulated *inside* the `DatabaseTransactionManager` boundary (e.g. `RegistrationService`), guaranteeing that side-effects are committed to the outbox synchronously. If a cache drops or the worker daemon dies, zero data is lost.
* **CLI Outbox Publisher Daemon:** [`bin/outbox_publisher.php`](./bin/outbox_publisher.php) polls and publishes outbox events to queues without statement churn or race conditions.
* **Idempotent Projection Guards:** [`IdempotentProjectionGuard.php`](./magma/queue/IdempotentProjectionGuard.php) and [`AbstractProjectionWorker.php`](./magma/database/AbstractProjectionWorker.php) prevent race conditions and duplicate executions on read-model projection caches.
* **Decoupled Worker Jobs:** [`AbstractDomainWorkerJob.php`](./magma/queue/AbstractDomainWorkerJob.php) standardizes Domain Service dependency injection into background queue workers.

---

## 11. Multi-Tenant Security, Storage & AST Boundary Auditing

* **Pluggable Tenant Context:** [`TenantContext.php`](./magma/security/TenantContext.php) and [`TenantContextProviderInterface.php`](./magma/security/TenantContextProviderInterface.php) enforce tenant and venue boundaries across the request lifecycle.
* **Static AST Boundary Auditor:** [`TenantSecurityAuditor.php`](./magma/validation/TenantSecurityAuditor.php) and [`bin/audit_schema.php`](./bin/audit_schema.php) perform static analysis to verify tenant foreign keys, composite indexes, and prohibit direct superglobal usage (`$_POST`, `$_GET`) in business services.
* **Encapsulated Storage Layer:** [`StorageInterface.php`](./magma/infrastructure/storage/StorageInterface.php), [`LocalStorageService.php`](./magma/infrastructure/storage/LocalStorageService.php), and [`S3StorageService.php`](./magma/infrastructure/storage/S3StorageService.php) enforce binary `finfo` MIME validation, extension allowlists, and cryptographic filename randomization.
* **Media Processing Service:** [`ImageProcessingService.php`](./magma/services/ImageProcessingService.php) utilizes native PHP `ext-gd` for square-center cropping and automatic WebP conversion.

### Subscription-Based Module Toggles

Magma handles elective, subscription-based modules through a strict, four-pillar standard:

* **Middleware Entitlement Gate:** Block unsubscribed access at the HTTP layer via the Onion pipeline.
* **UI Feature Flags:** Conditionally render navigation and buttons based on injected subscription state in the TemplateEngine.
* **Graceful Core Fallbacks:** Core modules must bypass missing dependencies seamlessly (e.g., Scheduling reverting to default capacity if Staffing is inactive).
* **Unified Multi-Tenant Schema:** All module tables live in the shared PostgreSQL database with a strict `tenant_id`, explicitly forbidding dynamic table creation per tenant.

---

## 12. High-Performance Optimizations & Production Diagnostics

* **Content-Negotiated Error Boundary:** [`ErrorHandler.php`](./magma/error/ErrorHandler.php) captures uncaught `\Throwable` instances. For API/AJAX requests, it delegates to [`JsonErrorPresenter.php`](./magma/error/JsonErrorPresenter.php) to return structured JSON envelopes (400, 401, 403, 404, 422, 500).
* **Interactive Developer Diagnostics:** When `APP_DEBUG=true`, [`DebugErrorPresenter.php`](./magma/error/DebugErrorPresenter.php) extracts and renders live source code context (±8 lines), interactive stack trace frames with argument dumps, request context, and PHP memory metrics. In production (`APP_DEBUG=false`), it renders a clean, user-friendly 500 error page with zero system path disclosure.
* **Pre-Kernel Boot Safety:** The absolute outermost entry points (`www/index.php` and `bin/worker.php`) are wrapped in explicit `try/catch` boundaries. If `.env` or container dependency resolution fails before the framework fully boots, the system emits a clean 500 error instead of leaking stack traces and DB credentials.
* **Absolute Exception Boundaries:** `AbstractQueryRepository` and `AbstractCommandRepository` explicitly catch `PDOException` natively at the execution layer, translating them into `DatabaseException`. This mathematically guarantees raw database credentials or SQL syntax errors never bleed into the domain or HTTP application layers.
* **Strict Storage Exceptions:** `S3StorageService` and `LocalStorageService` explicitly throw `StorageException` on I/O failures (e.g., AWS DNS timeout or disk permission denial) rather than returning silent boolean `false` values, guaranteeing zero unobserved data loss.
* **Self-Healing Background Workers:** The `QueueWorkerDaemon` utilizes robust `try/catch` and `sleep(5)` backoff mechanisms when polling Redis. If the infrastructure drops, it logs to the PSR-3 `NativeLogger` and gracefully waits for cluster recovery, rather than fatally crashing.
* **Transaction Rollback Safety:** `DatabaseTransactionManager` encapsulates its `begin()` and `rollBack()` calls securely, logging connection resets dynamically and safely aborting nested workflows without corrupting the PostgreSQL connection pool.
* **Resilient Cache Deserialization:** [`RedisCache.php`](./magma/cache/RedisCache.php) and cached repository decorators gracefully trap and evict corrupted or outdated serialized payloads, falling back to fresh database queries without throwing fatal `TypeErrors`.
* **Memory-Streaming Generators (`yield`):** Repositories returning large collections stream records using PHP generators (`yield`), keeping memory consumption constant regardless of dataset size.

---

## 13. Kernel CLI Toolkit Reference

Magma provides a set of standalone CLI utilities designed for deployment pipelines, cron schedulers, and background daemons:

| Command | Script File | Description |
|---|---|---|
| **Database Migrations** | `php bin/migrate.php` | Discovers and executes pending versioned PostgreSQL migrations. |
| **Route Pre-Compiler** | `php bin/cache_routes.php` | Compiles all route definitions into `magma/config/routes.cache.php`. |
| **Security & Boundary Audit** | `php bin/audit_schema.php` | Audits multi-tenant composite indexes and DTO boundary adherence. |
| **Outbox Publisher Daemon** | `php bin/outbox_publisher.php` | Polls PostgreSQL transactional outbox table via `FOR UPDATE SKIP LOCKED`. |
| **Redis Queue Worker** | `php bin/worker.php` | Runs the continuous background Redis list queue worker daemon. |
| **Token Cleanup** | `php app/bin/cleanup_tokens.php` | Prunes expired password reset tokens and remember-me credentials. |

---

## 14. The Geological Architecture Evolution

Magma’s ecosystem is structured through a geological metaphor, illustrating how code evolves from foundational principles to hardened infrastructure, and eventually into specialized modules and external services.

* **Magma:** The pure, unseen foundational core logic. This is the inner mantle—Vanilla PHP, strictly adhering to CQRS and SOLID, devoid of external dependencies. It is raw, theoretical, and powerful.
* **Lava:** Magma + Infrastructure (PHPStan, PHPUnit, CI/CD). It is the hardened testing environment. **WARNING: LAVA MUST NEVER BE MERGED INTO MAIN.** To enforce this, the `.git/hooks/pre-merge-commit` local script and `.github/workflows/enforce-main-purity.yml` physically block testing infrastructure from being merged upstream. Lava provides Database Integration test cases, custom AST static analysis to ban superglobals, deterministic Time Mocking (`MockClock`), Agnostic Test Factories, and Headless HTTP testing. Downstream apps build on top of Lava.
* **Basalt & Obsidian:** Opinionated business logic modules built on top. Like cooled, structured rock formations, these represent the tangible application layers where domain-specific logic resides.
* **Granite:** Headless, ultra-secure, backend-only microservices (B2B API data brokering). Dense, impermeable, and designed for heavy lifting, Granite layers handle secure system-to-system communications without UI overhead.
* **Pumice:** Secondary ecosystem tools like a Redis caching layer. Lightweight, porous, and fast, Pumice accelerates the system by reducing friction and latency.
* **Tephra:** Event broadcasting and webhooks. Like volcanic ash carried by the wind, Tephra represents asynchronous events and webhooks distributed across the ecosystem to keep external systems eventually consistent.

---

## License & Educational Usage
The Magma Framework is developed as an instructional enterprise-grade reference architecture. Built with strict adherence to SOLID, clean architecture, and modern PHP 8.2+ standards.
