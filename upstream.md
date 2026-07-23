# Magma Upstream Candidates Review List

This file tracks candidates for upstreaming from the **FussyBaby** application modules back into the core **Magma** framework.

---

## Candidate 1: Polymorphic Event Listener Parser Pattern

### What it is
A core utility pattern (such as a Trait or Base Listener class) that automatically extracts and normalizes the event payload from polymorphic inputs:
1. Synchronous event objects dispatched via `EventDispatcher` (extracting public properties or calling payload getter methods).
2. Asynchronous raw array payloads received directly (e.g., from background queue job workers).

### Why it matters
- **Adherence to Interface Segregation & SOLID:** Allows event listeners in the framework to implement uniform handling logic regardless of the dispatch mechanism (synchronous framework events vs asynchronous queue workers).
- **Reduces Code Duplication:** Solves the problem of writing separate handler structures or manual type checks in every observer that needs to support both memory-resident execution and background queue execution.

### Action
- [ ] Generalize the polymorphic event payload parsing helper into a core Magma utility trait (e.g., `Magma\events\concerns\InterpretsEventPayload`).
- [ ] Implement this trait in the framework's default listener boilerplates.

---

## Candidate 2: Event Dispatcher Memory Leak Protection (`clear()`)

### What it is
A clean-up method (`clear(): void`) declared in `EventDispatcherInterface` and implemented in the concrete `EventDispatcher` that resets the internal listeners registry array.

### Why it matters
- **Prevents Memory Leaks in CLI/Daemons:** In long-running worker processes (such as queue daemons or Cron loops), framework bootstrapper classes and service providers are frequently run or re-registered. Wiping listeners between jobs or cycles prevents infinite memory expansion due to duplicate event registrations.

### Action
- [ ] Add `clear(): void` to `Magma\interfaces\EventDispatcherInterface`.
- [ ] Implement `clear()` in `Magma\events\EventDispatcher`.

---

## Candidate 3: Pessimistic Concurrency Locking Pattern in Multi-Tenant Services

### What it is
A standardized database sequence logic combining a conditional SQL `INSERT ... ON CONFLICT DO NOTHING` followed by a `SELECT ... FOR UPDATE` query wrapped within a `TransactionManager` block.

### Why it matters
- **Strict ACID Guarantees:** Ensures concurrent client requests waiting on shared settings, capacities, or limits (like daily point systems or API rate buckets) do not read stale counters and violate strict allocation rules.
- **PostgreSQL Compatibility:** Overcomes the limitation where `SELECT ... FOR UPDATE` fails to lock non-existent records by guaranteeing row existence first via a low-overhead, conflict-free insert.

### Action
- [ ] Document this pessimistic locking sequence as the standard recommendation in Magma's architectural guidelines for state mutations.
- [ ] Provide boilerplate examples in the framework documentation showing transactional locking of settings models.

---

## Candidate 4: Generic Bulk Append Helper in BaseRepository

### What it is
A generic batch-insert helper method (e.g., `insertBulk(string $table, array $columns, array $rows): void`) implemented directly in the base repository layer to generate and execute a single multi-row SQL insert statement.

### Why it matters
- **Database Performance Optimization:** Batch inserting multiple event-sourced transactions or logs in a single query eliminates query round-trips and reduces lock times compared to sequential loops.
- **Framework DRY & SOLID Principles:** Prevents individual repositories from writing dynamic placeholder logic (`(?, ?, ...), (?, ?, ...)`) repeatedly.

### Action
- [ ] Implement `insertBulk()` in `Magma\models\BaseRepository` using positional PDO parameter binding.

---

## Candidate 5: Kernel Observability & Router Decomposition

### What it is
Architectural decomposition of the Router and implementation of strict observability in the Application Kernel.

### Why it matters
- **Adherence to SOLID:** Radically improves framework adherence to SOLID principles (SRP, DIP, Open/Closed).
- **O(1) Route Caching:** Eliminating the router bottleneck establishes the foundation for O(1) route caching, a critical performance requirement for scaling multi-tenant architectures.
- **Prevents Silent Crashes:** The kernel updates prevent silent application crashes by properly routing exceptions.

### Action
- [ ] Port the decomposed Router components to Magma core.
- [ ] Update the core kernel configuration to utilize strict observability standards.

---

## Candidate 6: Domain-Pure Authentication Service

### What it is
Removing HTTP transport logic (cookie/header management) from `AuthenticationService` and returning pure `AuthenticationResult` DTOs instead of manipulating the `RequestInterface`.

### Why it matters
- **Transport Agnosticism:** Strictly decouples the domain layer from the HTTP transport layer.
- **Context Reusability:** Allows the core authentication service to be reused in API (JWT) or CLI contexts without transport-level side effects breaking the execution.

### Action
- [ ] Refactor `AuthenticationService` to return `AuthenticationResult` DTOs.
- [ ] Remove `RequestInterface` bindings from the domain authentication services.

---

## Candidate 7: Kernel Isolation / Error Handler Delegation

### What it is
Delegating output buffer cleaning (`ob_get_level`) and core error handling to a swappable `ErrorHandlerInterface` rather than hardcoding it directly into the `Application` kernel.

### Why it matters
- **Async Runtime Compatibility:** Prevents issues with asynchronous PHP runtimes (like Swoole or RoadRunner) where traditional output buffers behave differently.
- **Adaptability:** Makes the Magma kernel highly adaptable to non-standard server APIs.

### Action
- [ ] Inject `ErrorHandlerInterface` into the kernel.
- [ ] Move output buffering and panic logic into the concrete ErrorHandler implementation.

---

## Candidate 8: Pagination DTO & Service Extraction

### What it is
Generalizing the logic to calculate offsets, pages, and handle fallback limits into a reusable `PaginationDTO` and `PaginationService`.

### Why it matters
- **Universal Reusability:** Pagination is a universal requirement; this extracts it from controller bloat.
- **Module Agnosticism:** Becomes a highly reusable framework utility for all future Magma modules (Accounts, Users, Audit Logs) beyond specific use cases like Inventory.

### Action
- [ ] Extract pagination logic into `Magma\dto\PaginationDTO`.
- [ ] Implement calculation and clamping logic in `Magma\services\PaginationService`.

---

## Candidate 9: Route Parameter Reflection Mapping

### What it is
Updating the `Router` to use PHP Reflection for mapping associative route parameters to named controller method arguments, replacing blind `array_values()` unpacking.

### Why it matters
- **Robustness:** Prevents fragile implicit ordering bugs where regex-matched parameter arrays mismatch the specific order of the controller method signatures.

### Action
- [ ] Implement reflection-based parameter mapping in `Router::executeHandler`.

---

## Candidate 10: Controller Dependency Reduction via Strict FormRequests

### What it is
Shifting validation dependencies entirely into `FormRequest` instantiation rather than injecting validator services directly into controllers.

### Why it matters
- **Single Responsibility Principle:** Reduces constructor bloat in core controllers (like `AuthController`), keeping them focused purely as thin HTTP orchestrators rather than validation managers.

### Action
- [ ] Update controller boilerplate to utilize self-validating `FormRequest` injection.

---

## Candidate 11: Database Connection Manager DRY Consolidation

### What it is
Consolidating the lazy-loading instantiation logic for read/write PDO connections into a single, unified private factory method.

### Why it matters
- **DRY Code:** Removes duplicated null-check and creation logic between read and write retrieval methods.
- **Extensibility:** Makes the `DatabaseConnectionManager` easier to extend for future multi-replica database scaling.

### Action
- [ ] Refactor `DatabaseConnectionManager` to use a unified `getConnection(string $type)` private method.

---

## Candidate 12: Kernel Exception Chain Preservation, Exception-Handling Extraction, and OCP Service Configuration

### What it is
A collection of core framework refinement patterns:
1. Exception chain preservation in database connections (passing the original `PDOException` to `RuntimeException`).
2. Extraction of kernel error response and fallback handling in `Application` into a separate single-responsibility helper method (`handleKernelError`).
3. Decoupling hardcoded service values (like account codes in ledger translation) by injecting configuration arrays via constructors.

### Why it matters
- **Exception Context Retention:** Preserves the underlying exception (such as DB-specific errors) across the boundary of user-facing runtime exceptions.
- **Adherence to SOLID (SRP/OCP):** Consolidates application execution boundaries to prevent the Front Controller from growing with duplicate fallback logic, and eliminates magic values within services to allow modular chart-of-accounts extensions.

### Action
- [ ] Implement `$previous` exception tracing standard across all core Magma database components.
- [ ] Port the private `handleKernelError` extraction pattern into `Magma\Application`.
- [ ] Provide configurable settings arrays for all ledger and processing services in the framework boilerplate.

---

## Candidate 13: Database Connection Manager Side-Effect Removal

### What it is
`magma/database/DatabaseConnectionManager.php`: Refactored connection resolution to remove pass-by-reference side effects.

### Why it matters
- **Adherence to SOLID & Clean Code:** Avoids side-effects and implicit state updates via pass-by-reference variables in private helpers.

### Action
- [x] Refactored connection resolution to remove pass-by-reference side effects.

---

## Candidate 14: Pagination Service Catalog Extraction

### What it is
`magma/services/PaginationService.php`: Added `getCatalogViewData` to support cleaner SRP implementation in downstream controllers.

### Why it matters
- **Single Responsibility Principle:** Isolates data orchestration and view preparation from HTTP controller actions, allowing them to remain thin.

### Action
- [x] Added getCatalogViewData to support cleaner SRP implementation in downstream controllers.

---

## Candidate 15: Dynamic Type Resolution in Router

### What it is
The shift from hardcoded HTTP Request checks to dynamic `is_a()` resolution for parameter mapping.

### Why it matters
- **Open/Closed Principle:** Ensures the route parameter mapper does not depend on specific HTTP Request implementation types, allowing downstream applications to pass custom request decorators/extensions seamlessly.

### Action
- [x] Refactored parameter mapping in Router to use dynamic `is_a()` resolution.

---

## Candidate 16: Transactional Outbox Payload Factories

### What it is
The pattern of extracting queue payload generation into private factory methods within domain services to enforce DRY and single-source-of-truth job signatures.

### Why it matters
- **DRY & Maintainability:** Prevents duplication of JSON serialization schema definitions across individual and bulk record operations, reducing the risk of schema drifts when updating job structures.

### Action
- [x] Refactored InventoryService payload generation into private helper method.

---

## Candidate 17: Extensible Non-Ingredient Costing Strategy Framework

### What it is
The non-ingredient recipe costing Strategy pattern (`UtilityCostStrategyInterface` and `UtilityCostCalculator`).

### Why it matters
- **Open/Closed Principle:** Allows the introduction of new overhead/costing strategies (e.g. shipping/freight strategies, packaging run times, gas consumption) without altering existing costing pipelines.
- **Extensibility:** Enables runtime configuration switches of costing calculations on a per-recipe basis.

### Action
- [x] Implemented Strategy pattern interface, standard/advanced strategies, and calculator context for recipe utility calculations.

---

## Candidate 18: Decoupled Module Container Checks & Database-Enforced Singletons

### What it is
A decoupled check utilizing both the DI Container and subscription tables to dynamically verify if elective modules are active before coupling database entities, alongside a PostgreSQL partial unique index (`WHERE (is_signature = TRUE)`) to enforce single-active-setting constraints.

### Why it matters
- **Modular Coupling Protection:** Permits complete decoupling of modules by verifying if their interfaces exist in the DI container before allowing database relations to be established.
- **Database-Level Integrity Enforced:** Enforces the singleton status of critical fields (like only one signature category per vendor) at the database layer, preventing race conditions or logic errors from polluting tenant data.

### Action
- [x] Implemented container-based interface resolution checks and a partial unique index in the Menu module database schema.

---

## Candidate 19: Centralized DRY UI Component Architecture

### What it is
A centralized `components.css` DRY UI architectural pattern with unified card, table, badge, and button components.

### Why it matters
- **Maintainability & DRY:** Centralizing these components drastically improves maintainability. Rather than duplicating UI CSS across unique features (e.g., separate classes for dashboard tables vs inventory tables), global components guarantee consistent aesthetic standards platform-wide.
- **Performance:** Reduces stylesheet file sizes and frontend load times.

### Action
- [ ] Refactor Magma administrative boilerplates to utilize a singular components CSS hub rather than page-specific component duplication.

---

## Candidate 20: Transaction-Based Multi-Row Sequence Reordering & Dynamic CSRF AJAX Synchronization

### What it is
A dual framework-level enhancement pattern:
1. A transaction-based multi-row sequence sorting algorithm (`updateCategoriesOrder`) implemented at the repository layer.
2. A client-side to server-side CSRF token synchronization handler that returns the newly rotated session CSRF token in dynamic AJAX JSON responses and updates all page token inputs.

### Why it matters
- **Transaction-Based Sequence Integrity (SOLID):** Standardizes sequence reordering for database rows without risking partial update failures or race conditions, keeping the logic encapsulated at the repository layer.
- **Robust CSRF Security without Session Expiry:** Solves the common friction point where active CSRF token rotation on state-mutating requests (POST/PUT/DELETE) breaks subsequent asynchronous actions on the page. Returning and syncing the fresh token dynamically ensures all future forms remain valid without forcing page reloads.

### Action
- [x] Implemented transaction-based sorting and AJAX CSRF token synchronization in the Menu management category reordering module.

---

## Candidate 21: Compiled Combined Regex for Method Checks in Router

### What it is
A routing engine performance optimization that compiles all dynamic routes of an alternate HTTP method into a singular combined regular expression using the PCRE `(?J)` (allow duplicate subpattern names) option.

### Why it matters
- **Performance / Time Complexity:** Reduces the time complexity of the 405 Method Not Allowed check from $O(N)$ sequential regex executions to a single $O(1)$ preg_match evaluation per alternate HTTP method.
- **Security / DoS Prevention:** Under high-concurrency environments, sequentially evaluating hundreds of regexes on invalid or malicious requests causes CPU spikes (Regex Denial of Service / ReDoS). A single combined pattern mitigates this vulnerability entirely.

### Action
- [x] Implemented pre-compilation and caching of combined HTTP method patterns in `Magma\routing\Router`.

---

## Candidate 22: Redis Queue Pipelining & Bulk Pushing

### What it is
The addition of a `pushBulk(string $queue, array $payloads): void` method to the `QueueInterface` and its concrete `RedisQueue` implementation using the phpredis pipeline feature (`$redis->multi(\Redis::PIPELINE)`).

### Why it matters
- **Network I/O Performance:** Pipelining multiple commands to Redis enables writing all jobs in a single TCP packet and reading the replies together, drastically reducing network round-trip overhead.
- **Atomic Batch Projections:** Enables high-concurrency transactional systems to queue multiple projection updates (like inventory ledger changes or audit logs) in a single atomic dispatch operation.

### Action
- [x] Added `pushBulk` to `Magma\queue\QueueInterface` and implemented pipelining in `Magma\queue\RedisQueue`.

---

## Candidate 23: Dashboard Aggregation Service Extraction

### What it is
Extraction of complex dashboard aggregation (fetching stats, schedules, low stock items, top recipes, and active staff) into a dedicated `DashboardAggregatorService`.

### Why it matters
- **Single Responsibility Principle:** Prevents the "God Class" anti-pattern in primary entry point controllers (`VendorAdminController`). It delegates the orchestration of multiple distinct module repositories into a unified data structure, keeping the HTTP controller extremely thin.

### Action
- [ ] Document the `AggregatorService` pattern as the framework standard for building complex read-only dashboards without violating controller SRP.

---

## Candidate 24: SubscriptionMiddleware (Role/Tier-based Tenant Feature Gating)

### What it is
The implementation of `SubscriptionMiddleware` that abstracts tenant feature flags into standard HTTP pipeline guards.

### Why it matters
- **Clean Architecture & Separation of Concerns:** Decouples complex conditional access checks from the core Business Logic layers. This gives Magma a plug-and-play gating mechanism for future enterprise features without needing to modify existing controllers.

### Action
- [ ] Add `SubscriptionMiddleware` to the core Magma middleware pipeline defaults.

---

## Candidate 25: Content-Type Scoped Cache-Control Middleware (`NoCacheMiddleware`)

### What it is
A core framework middleware that selectively applies HTTP `Cache-Control` and `Pragma` headers to restrict client-side caching of dynamic views (specifically targeting `text/html` and `application/json` payloads).

### Why it matters
- **Prevents Stale Browser States:** Solves the problem where dynamic page loads (such as dashboards, tables, and admin pages) redirect after state mutations (POST-redirect-GET pattern) but show stale data due to client-side browser caching.
- **Enhanced Data Security:** Prevents browsers from caching dynamic views in their back/forward history cache, preventing access to sensitive customer or employee information (such as bank details or NI numbers) after logging out.
- **Production-Grade Scope Protection:** By scoping headers dynamically to HTML/JSON content types, it guarantees that file streams, document downloads (e.g. PDFs, CSVs), or cached static resources served via the framework are not affected.

### Action
- [ ] Add the `getHeaders(): array` method to the core `Response` object.
- [ ] Implement `Magma\middleware\NoCacheMiddleware` in the core framework middleware library.
- [ ] Register it as a default global middleware.

---

## 8. Granular Application Service Orchestration and Controller Split Pattern

### What it is
A core framework architectural pattern that breaks down "God Controllers" into modular Single Responsibility Principle (SRP) controllers (e.g. `StaffRoleController`, `StaffMemberController`), and extracts cross-domain data coordination into Application Services (e.g. `RecipeService`). Furthermore, it mandates Data Transfer Objects (DTOs) and ViewModels for strict typing.

### Why it matters
- **Granular Access Control:** As Magma expands for enterprise and multi-tenant subscription tiers, having discrete controllers allows middleware to selectively permit or deny access to extremely specific module subsections, rather than blanket-allowing an entire "God" module.
- **Data Integrity and Scope Safety:** Enforcing DTOs ensures incoming untyped JSON/POST variables are converted into strictly typed boundaries, permanently eradicating "missing key" bugs (like silent nullification of variables). ViewModels decouple database array structures from HTML templates.
- **Separation of Concerns:** Repositories only fetch data relating to their explicit database tables. Any logic requiring fetching from multiple modules is orchestrated in the application service layer, preventing Feature Envy.

### Action
- [ ] Incorporate DTOs, ViewModels, and Application Services as formal concepts in the Magma architecture documentation.
- [ ] Migrate existing monolithic controllers in remaining core modules to this split pattern.

---

## Candidate 26: Multi-Venue & Sub-Location Context Resolver Middleware (`VenueContextResolver`)

### What it is
A core framework middleware and service (`VenueContextResolver` and `VenueContextInterface`) that dynamically inspects incoming requests (via route parameters, HTTP headers, or session states) to scope database operations and view states to a specific venue/branch under a multi-tenant vendor.

### Why it matters
- **Hierarchical Multi-Tenancy:** Enables multi-tenant SaaS applications (like Urban Sugar) to support nested multi-venue hierarchies (`Vendor` $\rightarrow$ `Venues` $\rightarrow$ `Domain Data`) without duplicating master data.
- **Strict Data Scoping:** Guarantees that location-sensitive datasets (inventory balances, local shift rosters, kitchen production orders) are automatically scoped to the active venue context while allowing master catalogs and global profiles to remain vendor-wide.

### Action
- [ ] Add `VenueContextResolver` middleware to the Magma framework pipeline defaults.
- [ ] Define `VenueContextInterface` in `Magma\interfaces\VenueContextInterface`.