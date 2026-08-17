# Magma Framework Upstream Candidates Register

This document serves as the centralized architectural register for the **Magma Framework** kernel. It tracks core framework evolutions, reusable architectural abstractions, security hardening measures, and high-performance infrastructure patterns.

> **Architectural Boundary Principle:**
> Magma core is strictly **domain-agnostic**. Business models, industry-specific compliance rules (e.g. food allergen regulations), financial accounting ledgers, and third-party geocoding heuristics are strictly maintained within downstream application modules (`FussyBaby`, `BeautyVault`), ensuring Magma remains a pristine, high-performance modular kernel.

---

## 1. Core Framework Register Index (Kernel Primitives)

| ID | Candidate Name | Architectural Classification | Key Target / Implementation |
|---|---|---|---|
| **01** | [Segregated CQRS Repositories](#01-segregated-cqrs-repositories) | Architecture / CQRS Persistence | `Magma\models\AbstractQueryRepository`, `AbstractCommandRepository` |
| **02** | [PostgreSQL Atomic Primary Key Resolution (`RETURNING id`)](#02-postgresql-atomic-primary-key-resolution-returning-id) | Database Infrastructure / PostgreSQL Dialect Engine | `Magma\database\PostgreSqlInsertBuilder` |
| **03** | [PostgreSQL Savepoint Transaction Manager](#03-postgresql-savepoint-transaction-manager) | Database Infrastructure / Transaction Management | `Magma\database\DatabaseTransactionManager` |
| **04** | [Hierarchical Recursive CTE Optimizer](#04-hierarchical-recursive-cte-optimizer) | Database & ORM / Data Access Layer | `Magma\database\CteQueryBuilder`, `BatchHierarchicalLoader` |
| **05** | [Universal Multi-Tenant Keyset Paginator & Protocol-Agnostic DTO](#05-universal-multi-tenant-keyset-paginator--protocol-agnostic-dto) | Database & ORM / High-Performance Data Access | `Magma\database\MultiTenantKeysetQueryBuilder`, `PaginationService` |
| **06** | [CQRS Eager Relation Batch Loader](#06-cqrs-eager-relation-batch-loader) | Database & ORM / High-Performance Data Access | `Magma\database\EagerRelationBatchLoader` |
| **07** | [CQRS CommandBus Interface & Standardized Caching Abstraction](#07-cqrs-commandbus-interface--standardized-caching-abstraction) | Architecture / CQRS & Caching | `Magma\interfaces\cqrs\CommandBusInterface`, `CachedRepositoryDecorator` |
| **08** | [CQRS Command Interface Expansion for Seeders](#08-cqrs-command-interface-expansion-for-seeders) | Architecture / CQRS & CLI Tooling | `Magma\interfaces\cqrs\UserCommandInterface` |
| **09** | [Decoupled CLI Database Migration Runner](#09-decoupled-cli-database-migration-runner) | Database Infrastructure / CLI Tooling | `Magma\cli\commands\MigrateCommand`, `SchemaInitializer`, `bin/migrate.php` |
| **10** | [Reflection Container Auto-Wiring & Dynamic Instantiation (`makeWithArgs`)](#10-reflection-container-auto-wiring--dynamic-instantiation-makewithargs) | Architecture / Dependency Injection | `Magma\container\Container` |
| **11** | [Container Autoloader Delegation in `Container::has()`](#11-container-autoloader-delegation-in-containerhas) | Architecture / Dependency Injection | `Magma\container\Container::has` |
| **12** | [Strongly-Typed Routing Manifest Engine & `Route` Value Object](#12-strongly-typed-routing-manifest-engine--route-value-object) | Routing & HTTP / Core Routing Engine | `Magma\routing\Route`, `RouteDefinition`, `UrlGenerator`, `bin/cache_routes.php` |
| **13** | [Router Action DI Auto-Wiring & Automated FormRequest Injection](#13-router-action-di-auto-wiring--automated-formrequest-injection) | Routing & HTTP / Request Validation | `Magma\routing\Router::executeHandler`, `FormRequest` |
| **14** | [`Request::build()` Static Builder & Decoupled Session Abstraction](#14-requestbuild-static-builder--decoupled-session-abstraction) | HTTP & Middleware / Request Lifecycle & State | `Magma\http\Request`, `SessionInterface` |
| **15** | [Dual-Mode Pipeline Dispatcher & PSR-15 Middleware Adapter](#15-dual-mode-pipeline-dispatcher--psr-15-middleware-adapter) | HTTP & Middleware / Pipeline Architecture | `Magma\pipeline\Pipeline`, `Magma\middleware\Psr15Adapter` |
| **16** | [Content-Negotiated Error Handler & Security Middleware Boundaries](#16-content-negotiated-error-handler--security-middleware-boundaries) | HTTP & Middleware / Error Handling & Security | `Magma\error\ErrorHandler`, `AuthMiddleware`, `CsrfMiddleware` |
| **17** | [Frontend CSRF Token Synchronization & AJAX Rotation Optimization](#17-frontend-csrf-token-synchronization--ajax-rotation-optimization) | HTTP & Security / CSRF Protection | `Magma\controllers\BaseController`, `CsrfMiddleware` |
| **18** | [Configurable Content Security Policy (CSP) for External Fonts & Assets](#18-configurable-content-security-policy-csp-for-external-fonts--assets) | HTTP & Security / Security Headers | `Magma\middleware\SecurityHeadersMiddleware` |
| **19** | [Multi-Tenant Context Abstraction & Scoping Middleware](#19-multi-tenant-context-abstraction--scoping-middleware) | Security & Multi-Tenancy / Tenant Isolation | `Magma\security\TenantContext`, `TenantSecurityMiddleware` |
| **20** | [Multi-Tenant Schema Auditor & Strictly-Typed DTO Validator](#20-multi-tenant-schema-auditor--strictly-typed-dto-validator) | Security & Multi-Tenancy / Static Analysis & Tooling | `Magma\validation\TenantSecurityAuditor`, `bin/audit_schema.php` |
| **21** | [Decoupled TemplateEngine Layouts and Partials](#21-decoupled-templateengine-layouts-and-partials) | View Engine / View Hierarchy | `Magma\view\TemplateEngine`, `CoreServiceProvider` |
| **22** | [Modular View Namespaces (`ViewLoaderInterface::addNamespace`)](#22-modular-view-namespaces-viewloaderinterfaceaddnamespace) | View Engine / Modular Monolith | `Magma\view\ViewLoaderInterface`, `LocalFileViewLoader` |
| **23** | [Decoupled View Composer Registry](#23-decoupled-view-composer-registry) | View Engine & Architecture / Modular Monolith Isolation | `Magma\views\ViewComposerRegistry` |
| **24** | [Form View Composer & ViewModel / Presenter Pattern](#24-form-view-composer--viewmodel--presenter-pattern) | View Engine & Architecture / DRY ViewModels | `Magma\view\FormViewComposer`, `AbstractFormViewModel`, Presenters |
| **25** | [Widget-Based Modular Dashboard Architecture & Soft Dependency Registration](#25-widget-based-modular-dashboard-architecture--soft-dependency-registration) | Architecture / Modular Dashboard Engine | `Magma\dashboard\DashboardWidgetInterface`, `DashboardWidgetRegistry` |
| **26** | [Tokenized Secure Storage Service & `StorageInterface` Abstraction](#26-tokenized-secure-storage-service--storageinterface-abstraction) | Security & Infrastructure / File Storage | `Magma\infrastructure\storage\StorageInterface`, `LocalStorageService`, `S3StorageService` |
| **27** | [Reusable Image Processing Service](#27-reusable-image-processing-service) | Infrastructure / Media Services | `Magma\services\ImageProcessingService` |
| **28** | [Deterministic Asset Version Manager](#28-deterministic-asset-version-manager) | HTTP & Frontend / Performance & Caching | `Magma\assets\AssetVersionManager`, `ViewHelper::asset()` |
| **29** | [PostgreSQL Concurrent Outbox Engine (`SKIP LOCKED`)](#29-postgresql-concurrent-outbox-engine-skip-locked) | Queue & Asynchronous Processing / Distributed Systems | `Magma\database\OutboxJobRepositoryInterface`, `OutboxJobRepository`, `bin/outbox_publisher.php` |
| **30** | [Idempotent Outbox Event Projection Guard](#30-idempotent-outbox-event-projection-guard) | Queue & Asynchronous Processing / CQRS Integrity | `Magma\queue\IdempotentProjectionGuard`, `AbstractProjectionWorker` |
| **31** | [Strongly-Typed Domain Event System (`DomainEventInterface` & `EventPayloadInterface`)](#31-strongly-typed-domain-event-system-domaineventinterface--eventpayloadinterface) | Architecture / Domain-Driven Design / Domain Events | `Magma\events\DomainEventInterface`, `EventPayloadInterface` |
| **32** | [Domain Service Injection for Background Worker Jobs](#32-domain-service-injection-for-background-worker-jobs) | Queue & Background Processing / Architecture | `Magma\queue\AbstractDomainWorkerJob` |
| **33** | [Pluggable Domain Strategy Registry (`StrategyRegistry<T>`)](#33-pluggable-domain-strategy-registry-strategyregistryt) | Architecture / Design Patterns / Domain Services | `Magma\contracts\StrategyInterface`, `StrategyRegistry` |
| **34** | [Universal Finite State Transition Engine](#34-universal-finite-state-transition-engine) | Architecture / Domain-Driven Design / State Machines | `Magma\domain\AbstractStateTransition`, `StateMachine` |
| **35** | [ObservableStore & Native CSS Cascade Layer Component System](#35-observablestore--native-css-cascade-layer-component-system) | Frontend Architecture / Reactive State & Design System | `Magma\assets\js\ObservableStore.js`, `Magma\assets\css\base.css` |
| **36** | [Client EventBus & Modal Idempotency Binding Manager](#36-client-eventbus--modal-idempotency-binding-manager) | Frontend Architecture / Reactive State & Memory Management | `Magma\assets\js\EventBus.js`, `IdempotentBindingRegistry.js` |
| **37** | [Declarative Event Delegator (`MagmaActionDispatcher`)](#37-declarative-event-delegator-magmaactiondispatcher) | Frontend Architecture / Modular JavaScript | `Magma\assets\js\MagmaActionDispatcher.js`, `EventDelegator.js` |
| **38** | [Client-Side Safe Template Engine (`<template>` Driven)](#38-client-side-safe-template-engine-template-driven) | Frontend Architecture / Security & View Rendering | `Magma\assets\js\TemplateEngine.js` |
| **39** | [Strict DOM Clipboard Sanitizer](#39-strict-dom-clipboard-sanitizer) | Security & Frontend / DOM Sanitization | `Magma\assets\js\DomSanitizer.js`, `HtmlEditor.js` |
| **40** | [Lightweight Zero-Dependency Vanilla ES6 WYSIWYG Editor (`MagmaEditor`)](#40-lightweight-zero-dependency-vanilla-es6-wysiwyg-editor-magmaeditor) | Frontend Architecture / UI Components | `Magma\assets\js\MagmaEditor.js` |
| **41** | [Asynchronous, Dynamic & Rich Multiline Combobox (`MagmaCombobox`)](#41-asynchronous-dynamic--rich-multiline-combobox-magmacombobox) | Frontend Architecture / UI Components | `Magma\assets\js\MagmaCombobox.js`, `combobox.css` |
| **42** | [Unified PSR-16 Style CacheInterface, Redis Adapter & Deserialization Fallback](#42-unified-psr-16-style-cacheinterface-redis-adapter--deserialization-fallback) | Caching & Performance / Infrastructure | `Magma\interfaces\CacheInterface`, `RedisCache`, `CachedRepositoryDecorator` |

---

## 2. Segregated Application & Domain Candidates (Maintained in Downstream Modules)

The following candidates were evaluated and identified as **application-specific domain logic** rather than generic framework kernel primitives. They are maintained in downstream applications:

| Candidate | Domain Domain & Location | Architectural Rationale for Core Exclusion |
|---|---|---|
| **UK/EU Statutory Allergen Compliance Engine** | `FussyBaby` (`modules/Recipes/domain/allergens/`) | Natasha's Law and the 14 FIC statutory allergens are specific to food/beverage SaaS. Placing them in Magma core pollutes the framework namespace for non-food applications (e.g. `BeautyVault`). |
| **Double-Entry Financial Ledger Engine** | `FussyBaby` / `BeautyVault` (`modules/Billing/`) | Financial debit/credit ledgers are business accounting models, not web framework MVC primitives. Applications needing accounting implement their own domain ledgers using Magma's Transaction Manager. |
| **Culinary Unit Conversion & Margin Rules Engine** | `FussyBaby` (`modules/Recipes/domain/rules/`) | Unit conversions (`g` to `kg`, `ml` to `L`) and food cost multipliers (CoGS) belong strictly to recipe management. |
| **Heuristic Geocoding & Postal Timezone Resolver** | `FussyBaby` / `BeautyVault` (`modules/Venues/`) | Hardcoding country-specific postal regexes or bounding boxes couples the framework to specific geographic data providers. Downstream venue modules integrate geolocation services directly. |
| **Generic `BaseController::executeWithCatch`** | *Rejected (Anti-Pattern)* | Swallowing exceptions inside generic controller wrappers obscures stack traces and bypasses Magma's centralized `ErrorHandler` and Middleware pipeline. Controllers let exceptions bubble or catch domain-specific errors explicitly. |
| **In-Memory `ArraySessionDriver`** | *Rejected (Testing Concern)* | Adding an in-memory session driver to production HTTP core adds unnecessary complexity. Unit tests mock session interfaces directly or execute stateless HTTP tests. |

---

## 3. Detailed Framework Candidate Register

### 01. Segregated CQRS Repositories
- **Classification:** `[Architecture / CQRS Persistence]`
- **What it is:** Segregated base repository abstractions (`AbstractQueryRepository` vs `AbstractCommandRepository`) strictly enforcing CQRS at the database connection boundary ($dbRead PDO replica vs $dbWrite PDO master).
- **Why it matters:** Enforces the Interface Segregation Principle (ISP). Guarantees that read-only HTTP GET requests never eagerly open write connection handles, optimizing connection pools and preventing repository "God Classes".
- **Target:** `Magma\models\AbstractQueryRepository` and `Magma\models\AbstractCommandRepository`.

### 02. PostgreSQL Atomic Primary Key Resolution (`RETURNING id`)
- **Classification:** `[Database Infrastructure / PostgreSQL Dialect Engine]`
- **What it is:** Dedicated insert query builder (`PostgreSqlInsertBuilder`) standardizing `INSERT INTO ... RETURNING id` with `$stmt->fetchColumn()`.
- **Why it matters:** In PostgreSQL via PDO, `lastInsertId()` without an explicit sequence returns `'0'` or `false`. Utilizing native `RETURNING id` guarantees atomic, driver-safe primary key retrieval.
- **Target:** `Magma\database\PostgreSqlInsertBuilder`.

### 03. PostgreSQL Savepoint Transaction Manager
- **Classification:** `[Database Infrastructure / Transaction Management]`
- **What it is:** Enterprise transaction coordinator implementing savepoint nesting (`SAVEPOINT trans_N`, `RELEASE SAVEPOINT`, `ROLLBACK TO SAVEPOINT trans_N`).
- **Why it matters:** Prevents inner service exceptions in PostgreSQL from transitioning the connection into an unrecoverable aborted transaction block state (`current transaction is aborted, commands ignored until end of transaction block`).
- **Target:** `Magma\database\DatabaseTransactionManager`.

### 04. Hierarchical Recursive CTE Optimizer
- **Classification:** `[Database & ORM / Data Access Layer]`
- **What it is:** Recursive CTE generator and batch loader (`CteQueryBuilder`, `BatchHierarchicalLoader`) converting single-root queries into batched multi-root CTEs (`WHERE root_id IN (:placeholders)`).
- **Why it matters:** Eliminates $O(N)$ N+1 query storms on hierarchical entity trees (BOMs, categories, modifier groups).
- **Target:** `Magma\database\CteQueryBuilder` and `Magma\database\BatchHierarchicalLoader`.

### 05. Universal Multi-Tenant Keyset Paginator & Protocol-Agnostic DTO
- **Classification:** `[Database & ORM / High-Performance Data Access]`
- **What it is:** Keyset query builder (`MultiTenantKeysetQueryBuilder`) supporting constant $O(1)$ cursor seeking (`WHERE id > :last_id LIMIT :limit`) combined with automatic `tenant_id`/`venue_id` scoping and decoupled `PaginationDTO`.
- **Why it matters:** Replaces slow `OFFSET / LIMIT` scans and decouples domain read services from HTTP primitives.
- **Target:** `Magma\database\MultiTenantKeysetQueryBuilder` and `Magma\models\AbstractKeysetRepository`.

### 06. CQRS Eager Relation Batch Loader
- **Classification:** `[Database & ORM / High-Performance Data Access]`
- **What it is:** In-memory batch relation loader (`EagerRelationBatchLoader`) resolving 1-to-many child relations across parent collections via `WHERE parent_id IN (?)`.
- **Why it matters:** Eliminates N+1 query storms in catalog feeds without requiring heavy Active Record ORMs.
- **Target:** `Magma\database\EagerRelationBatchLoader`.

### 07. CQRS CommandBus Interface & Standardized Caching Abstraction
- **Classification:** `[Architecture / CQRS & Caching]`
- **What it is:** Formal `CommandBusInterface` contract coupled with `CachedRepositoryDecorator::remember()` coordinating TTL and serialization using closures.
- **Why it matters:** Standardizes caching strategies across all Repositories and enforces strict CQRS boundaries.
- **Target:** `Magma\interfaces\cqrs\CommandBusInterface` and `Magma\database\CachedRepositoryDecorator`.

### 08. CQRS Command Interface Expansion for Seeders
- **Classification:** `[Architecture / CQRS & CLI Tooling]`
- **What it is:** Expansion of `UserCommandInterface` and `UserCommandRepository` (`updateRole()`, `provisionAdminUser()`) for CLI seeding scripts.
- **Why it matters:** Eliminates untestable, raw PDO queries in initial database seeders.
- **Target:** `Magma\interfaces\cqrs\UserCommandInterface` and `Magma\repositories\UserCommandRepository`.

### 09. Decoupled CLI Database Migration Runner
- **Classification:** `[Database Infrastructure / CLI Tooling]`
- **What it is:** Standalone CLI migration tool (`bin/migrate.php`, `SchemaInitializer`) auto-discovering module DDL schemas and running transactional migrations outside web requests.
- **Why it matters:** Completely eliminates runtime `CREATE TABLE IF NOT EXISTS` checks and file permission scans from `bootstrap.php`.
- **Target:** `Magma\database\SchemaInitializer` and `bin/migrate.php`.

### 10. Reflection Container Auto-Wiring & Dynamic Instantiation (`makeWithArgs`)
- **Classification:** `[Architecture / Dependency Injection]`
- **What it is:** Reflection-based constructor auto-wiring in `Container` coupled with `makeWithArgs(string $class, array $args)` combining resolved DI services with runtime arguments.
- **Why it matters:** Eliminates manual service bindings and offloads reflection complexity from peripheral resolvers.
- **Target:** `Magma\container\Container`.

### 11. Container Autoloader Delegation in `Container::has()`
- **Classification:** `[Architecture / Dependency Injection]`
- **What it is:** Updated `Container::has()` to invoke `class_exists($id, true)` and `interface_exists($id, true)` with autoloader delegation enabled.
- **Why it matters:** Prevents false negatives for uninstantiated PSR-4 autoloadable classes.
- **Target:** `Magma\container\Container::has`.

### 12. Strongly-Typed Routing Manifest Engine & `Route` Value Object
- **Classification:** `[Routing & HTTP / Core Routing Engine]`
- **What it is:** Immutable typed `Route` and `RouteDefinition` Value Objects, FastRoute-style PCRE compiler, named route URL generator (`UrlGenerator::route()`), and pre-compilation manifest CLI (`bin/cache_routes.php`).
- **Why it matters:** Eliminates primitive tuple array offsets (`$route[0..6]`) and maximizes IDE analysis and OPcache performance.
- **Target:** `Magma\routing\Route`, `RouteDefinition`, `UrlGenerator`, `bin/cache_routes.php`.

### 13. Router Action DI Auto-Wiring & Automated FormRequest Injection
- **Classification:** `[Routing & HTTP / Request Validation]`
- **What it is:** Dynamic reflection resolution of controller action arguments in `Router::executeHandler`, automatically instantiating and triggering `FormRequest::validate()` prior to controller execution.
- **Why it matters:** Drastically trims controller boilerplate and guarantees controllers only receive pre-validated, sanitized DTOs.
- **Target:** `Magma\routing\Router::executeHandler` and `Magma\validation\FormRequest`.

### 14. `Request::build()` Static Builder & Decoupled Session Abstraction
- **Classification:** `[HTTP & Middleware / Request Lifecycle & State]`
- **What it is:** Static factory builder `Request::build()` encapsulating JSON parsing and verb spoofing, combined with `SessionInterface` abstraction.
- **Why it matters:** Adheres to SRP and decouples session state from HTTP request payloads.
- **Target:** `Magma\http\Request` and `Magma\http\SessionInterface`.

### 15. Dual-Mode Pipeline Dispatcher & PSR-15 Middleware Adapter
- **Classification:** `[HTTP & Middleware / Pipeline Architecture]`
- **What it is:** Dual-mode pipeline dispatcher handling both PSR-15 Middleware instances and PHP closures/callables (`$pipe($passable, $next)`).
- **Why it matters:** Eliminates runtime type errors and enables standard PSR-15 middleware packages to run seamlessly in Magma.
- **Target:** `Magma\pipeline\Pipeline` and `Magma\middleware\Psr15Adapter`.

### 16. Content-Negotiated Error Handler & Security Middleware Boundaries
- **Classification:** `[HTTP & Middleware / Error Handling & Security]`
- **What it is:** Content negotiation in `ErrorHandler`, `AuthMiddleware`, and `CsrfMiddleware` inspecting `expectsJson()` to return structured 400/401/403/500 JSON payloads (catching `\Throwable`) while rendering HTML for browser navigation.
- **Why it matters:** Guarantees strict API contract adherence and stops HTML error stack traces from crashing JSON fetch clients.
- **Target:** `Magma\error\ErrorHandler`, `JsonErrorPresenter`, `AuthMiddleware`, `CsrfMiddleware`.

### 17. Frontend CSRF Token Synchronization & AJAX Rotation Optimization
- **Classification:** `[HTTP & Security / CSRF Protection]`
- **What it is:** Exposing raw `$data['csrfToken']` in `BaseController::render()` for `<meta name="csrf-token">` tags, combined with disabling aggressive token rotation on AJAX requests in `CsrfMiddleware`.
- **Why it matters:** Eliminates false-positive 403 Forbidden errors during debounced form interactions.
- **Target:** `Magma\controllers\BaseController` and `Magma\middleware\CsrfMiddleware`.

### 18. Configurable Content Security Policy (CSP) for External Fonts & Assets
- **Classification:** `[HTTP & Security / Security Headers]`
- **What it is:** Configurable CSP header allowlists in `SecurityHeadersMiddleware` (supporting Google Fonts `fonts.googleapis.com` / `fonts.gstatic.com`).
- **Why it matters:** Allows modern typography while maintaining strict script and object security.
- **Target:** `Magma\middleware\SecurityHeadersMiddleware`.

### 19. Multi-Tenant Context Abstraction & Scoping Middleware
- **Classification:** `[Security & Multi-Tenancy / Tenant Isolation]`
- **What it is:** Pluggable `TenantContext` abstraction and `TenantSecurityMiddleware` automatically scoping tenant IDs to requests and repositories.
- **Why it matters:** Removes human error from tenant scoping, preventing cross-tenant data leaks.
- **Target:** `Magma\security\TenantContext` and `Magma\middleware\TenantSecurityMiddleware`.

### 20. Multi-Tenant Schema Auditor & Strictly-Typed DTO Validator
- **Classification:** `[Security & Multi-Tenancy / Static Analysis & Tooling]`
- **What it is:** Pre-flight linting and schema auditing tool (`TenantSecurityAuditor`, `bin/audit_schema.php`) verifying `tenant_id` foreign keys, multi-tenant indexing, and prohibiting direct superglobal usage (`$_POST`, `$_GET`).
- **Why it matters:** Guarantees multi-tenant SaaS data isolation and enforces clean boundaries.
- **Target:** `Magma\validation\TenantSecurityAuditor` and `bin/audit_schema.php`.

### 21. Decoupled TemplateEngine Layouts and Partials
- **Classification:** `[View Engine / View Hierarchy]`
- **What it is:** Added dedicated `partialsPath` property and constructor configuration to `TemplateEngine`, separating layout shells from reusable UI component partials.
- **Why it matters:** Prevents directory resolution collisions and cleanly organizes UI components.
- **Target:** `Magma\view\TemplateEngine`.

### 22. Modular View Namespaces (`ViewLoaderInterface::addNamespace`)
- **Classification:** `[View Engine / Modular Monolith]`
- **What it is:** Support for namespaced template paths (e.g. `Services::index`, `Menu::card`) via `ViewLoaderInterface` and `LocalFileViewLoader`.
- **Why it matters:** Eliminates relative directory traversal hacks (`../../modules/Services/views/`).
- **Target:** `Magma\view\ViewLoaderInterface` and `Magma\view\LocalFileViewLoader`.

### 23. Decoupled View Composer Registry
- **Classification:** `[View Engine & Architecture / Modular Monolith Isolation]`
- **What it is:** Central `ViewComposerRegistry` allowing modules to register view variables, sidebar navigation items (with priority weights), and header widgets.
- **Why it matters:** Enables domain modules to inject UI variables without editing core layout files.
- **Target:** `Magma\views\ViewComposerRegistry`.

### 24. Form View Composer & ViewModel / Presenter Pattern
- **Classification:** `[View Engine & Architecture / DRY ViewModels]`
- **What it is:** `FormViewComposer`, `AbstractFormViewModel`, and `AbstractPresenter` allowing identical form partials to render in Create and Edit modes and stripping business formatting logic from templates.
- **Why it matters:** Eliminates hundreds of lines of duplicate form markup and enforces the MVC pattern.
- **Target:** `Magma\view\FormViewComposer`, `AbstractFormViewModel`, `AbstractPresenter`.

### 25. Widget-Based Modular Dashboard Architecture & Soft Dependency Registration
- **Classification:** `[Architecture / Modular Dashboard Engine]`
- **What it is:** Extensible dashboard widget engine (`DashboardWidgetInterface`, `DashboardWidgetRegistry`) with soft-dependency protection (`try/catch`).
- **Why it matters:** Eliminates monolithic "God Classes" and allows modules to boot cleanly in non-UI environments (CLI, workers).
- **Target:** `Magma\dashboard\DashboardWidgetInterface` and `DashboardWidgetRegistry`.

### 26. Tokenized Secure Storage Service & `StorageInterface` Abstraction
- **Classification:** `[Security & Infrastructure / File Storage]`
- **What it is:** Storage abstraction (`StorageInterface`, `LocalStorageService`, `S3StorageService`) with native PHP `finfo` binary MIME verification, extension allowlists, randomized cryptographic token naming (`bin2hex(random_bytes(16))`), and disk mocking for testing.
- **Why it matters:** Removes direct `move_uploaded_file()` calls, prevents arbitrary upload exploits, and allows effortless cloud migration.
- **Target:** `Magma\infrastructure\storage\StorageInterface`, `LocalStorageService`, `S3StorageService`.

### 27. Reusable Image Processing Service
- **Classification:** `[Infrastructure / Media Services]`
- **What it is:** Centralized image manipulation service (`ImageProcessingService`) using native PHP `ext-gd` for center-square cropping, proportional resampling, and WebP compression, decoupled via `StorageInterface`.
- **Why it matters:** Eliminates heavy NPM/Composer packages for simple media handling while remaining cloud-compatible.
- **Target:** `Magma\services\ImageProcessingService`.

### 28. Deterministic Asset Version Manager
- **Classification:** `[HTTP & Frontend / Performance & Caching]`
- **What it is:** Asset version manager (`AssetVersionManager`, `ViewHelper::asset()`) resolving cache-busted URLs via content hashes or release tags (`APP_ASSET_VERSION`) instead of volatile dynamic timestamps.
- **Why it matters:** Maximizes HTTP browser caching while ensuring instant cache invalidation upon deployments.
- **Target:** `Magma\assets\AssetVersionManager` and `Magma\views\ViewHelper::asset()`.

### 29. PostgreSQL Concurrent Outbox Engine (`SKIP LOCKED`)
- **Classification:** `[Queue & Asynchronous Processing / Distributed Systems]`
- **What it is:** Database-backed transactional outbox (`OutboxJobRepository`, `bin/outbox_publisher.php`) implementing `fetchAndLockPending()` with PostgreSQL's `FOR UPDATE SKIP LOCKED` and batch deletion.
- **Why it matters:** Enables horizontally scalable, multi-worker outbox publishers without race conditions, duplicates, or statement churn.
- **Target:** `Magma\database\OutboxJobRepository` and `bin/outbox_publisher.php`.

### 30. Idempotent Outbox Event Projection Guard
- **Classification:** `[Queue & Asynchronous Processing / CQRS Integrity]`
- **What it is:** Transactional guard (`IdempotentProjectionGuard`, `AbstractProjectionWorker`) preventing hybrid workflows from combining async outbox jobs with immediate synchronous mutations on the same projection cache.
- **Why it matters:** Prevents double-counting bugs and race conditions in event-driven systems.
- **Target:** `Magma\queue\IdempotentProjectionGuard` and `Magma\database\AbstractProjectionWorker`.

### 31. Strongly-Typed Domain Event System (`DomainEventInterface` & `EventPayloadInterface`)
- **Classification:** `[Architecture / Domain-Driven Design / Domain Events]`
- **What it is:** Domain event contracts (`DomainEventInterface`, `EventPayloadInterface`) ensuring typed payloads, tenant identifiers, and UTC timestamps.
- **Why it matters:** Eliminates fragile reflection duck-typing across event dispatchers and queue workers.
- **Target:** `Magma\events\DomainEventInterface`, `EventPayloadInterface`, and `EventDispatcher`.

### 32. Domain Service Injection for Background Worker Jobs
- **Classification:** `[Queue & Background Processing / Architecture]`
- **What it is:** Base background job class (`AbstractDomainWorkerJob`) ensuring workers delegate calculations to pure Domain Services rather than embedding business logic in jobs or repositories.
- **Why it matters:** Maximizes code reusability across web controllers and background workers.
- **Target:** `Magma\queue\AbstractDomainWorkerJob`.

### 33. Pluggable Domain Strategy Registry (`StrategyRegistry<T>`)
- **Classification:** `[Architecture / Design Patterns / Domain Services]`
- **What it is:** Container-aware generic strategy registry (`StrategyRegistry<T>`) resolving dynamic domain algorithms by key name with runtime validation.
- **Why it matters:** Eliminates monolithic `switch` statements and adheres strictly to the Open/Closed Principle (OCP).
- **Target:** `Magma\contracts\StrategyInterface` and `Magma\services\StrategyRegistry`.

### 34. Universal Finite State Transition Engine
- **Classification:** `[Architecture / Domain-Driven Design / State Machines]`
- **What it is:** Finite state machine transition engine and value object (`AbstractStateTransition`, `StateMachine`) enforcing case-insensitive string normalization, allowed transition graphs, and terminal state invariants.
- **Why it matters:** Eliminates enum case mismatch bugs and centralizes lifecycle validation.
- **Target:** `Magma\domain\AbstractStateTransition` and `Magma\domain\StateMachine`.

### 35. ObservableStore & Native CSS Cascade Layer Component System
- **Classification:** `[Frontend Architecture / Reactive State & Design System]`
- **What it is:** Vanilla ES6 reactive client-side store implementing Observer pattern with `destroy()` teardown, coupled with native CSS Cascade Layers (`@layer reset, tokens, components, utilities, states`).
- **Why it matters:** Standardizes frontend state management, stops `!important` specificity wars, and eliminates memory leaks from unmanaged event listeners.
- **Target:** `Magma\assets\js\ObservableStore.js` and `Magma\assets\css\base.css`.

### 36. Client EventBus & Modal Idempotency Binding Manager
- **Classification:** `[Frontend Architecture / Reactive State & Memory Management]`
- **What it is:** Vanilla ES6 Pub/Sub library (`EventBus.js`) combined with a DOM binding registry (`IdempotentBindingRegistry.js`) utilizing `WeakMap` and `AbortController` signals.
- **Why it matters:** Eliminates severe memory leaks and runaway event storms on dynamic modals.
- **Target:** `Magma\assets\js\EventBus.js` and `Magma\assets\js\IdempotentBindingRegistry.js`.

### 37. Declarative Event Delegator (`MagmaActionDispatcher`)
- **Classification:** `[Frontend Architecture / Modular JavaScript]`
- **What it is:** Declarative event routing utility (`MagmaActionDispatcher.js`, `EventDelegator.js`) dispatching `data-action="entity:action"` attributes to registered ES6 controller handlers.
- **Why it matters:** Eliminates inline `onclick` attributes and global `window.*` assignments.
- **Target:** `Magma\assets\js\MagmaActionDispatcher.js` and `Magma\assets\js\EventDelegator.js`.

### 38. Client-Side Safe Template Engine (`<template>` Driven)
- **Classification:** `[Frontend Architecture / Security & View Rendering]`
- **What it is:** Strict `<template>` cloning engine with data binding attributes (`data-bind-text`, `data-bind-attr-*`, `data-if`, `data-loop`).
- **Why it matters:** Permanently eliminates `innerHTML` XSS injection vectors and complies with strict CSP policies.
- **Target:** `Magma\assets\js\TemplateEngine.js`.

### 39. Strict DOM Clipboard Sanitizer
- **Classification:** `[Security & Frontend / DOM Sanitization]`
- **What it is:** Recursive DOM clipboard sanitization module (`DomSanitizer.js`) with strict tag allowlists, attribute stripping, and safe URL protocol enforcement.
- **Why it matters:** Prevents stored XSS attacks when copying rich text into contenteditable fields.
- **Target:** `Magma\assets\js\DomSanitizer.js`.

### 40. Lightweight Zero-Dependency Vanilla ES6 WYSIWYG Editor (`MagmaEditor`)
- **Classification:** `[Frontend Architecture / UI Components]`
- **What it is:** Zero-dependency Vanilla ES6 WYSIWYG editor class (`MagmaEditor.js`) interfacing with native browser commands and integrating `DomSanitizer`.
- **Why it matters:** Eliminates bloated third-party NPM editors when only basic text formatting is required.
- **Target:** `Magma\assets\js\MagmaEditor.js`.

### 41. Asynchronous, Dynamic & Rich Multiline Combobox (`MagmaCombobox`)
- **Classification:** `[Frontend Architecture / UI Components]`
- **What it is:** Autocomplete combobox (`MagmaCombobox.js`) supporting debounced async fetching (`ajaxUrl`), wildcard attribute propagation (`data-propagate`), and 2-line selected card rendering with instant edit transitions.
- **Why it matters:** Replaces bespoke autocomplete scripts with a robust, DRY component.
- **Target:** `Magma\assets\js\MagmaCombobox.js` and `combobox.css`.

### 42. Unified PSR-16 Style CacheInterface, Redis Adapter & Deserialization Fallback
- **Classification:** `[Caching & Performance / Infrastructure]`
- **What it is:** Standardized PSR-16 style `CacheInterface`, `RedisCache`, `ArrayCache`, and `CachedRepositoryDecorator` with automatic try/catch fallback on corrupted/stale `unserialize()` payloads.
- **Why it matters:** Allows swapping cache backends seamlessly and prevents fatal 500 type errors on corrupted Redis caches.
- **Target:** `Magma\interfaces\CacheInterface`, `RedisCache`, `ArrayCache`, `CachedRepositoryDecorator`.
