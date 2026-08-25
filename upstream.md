# Magma Upstream Candidates Consolidated

## BeautyVault & TempBookingApp Candidates

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Added `add(string $key, mixed $value, null|int|DateInterval $ttl = null): bool` method to the PSR-16 `CacheInterface` and its concrete drivers (`RedisCache`, `ArrayCache`).
> **Why it matters:** Provides natively exposed atomic concurrency locking (`SETNX`), which is essential for distributed applications avoiding race conditions (like double bookings or job reservations) without relying on expensive PostgreSQL row-level locks.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Added an asynchronous login endpoint (`ajaxLogin`) tailored for SPA flows.
> **Why it matters:** Provides an alternative to the default redirect-heavy `LoginController`, enabling applications to authenticate users mid-funnel without destroying frontend ephemeral state.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Elimination of Global Static Cache state from the Container and severe reflection optimization in the Router. Enforcement of a `TenantAwareUserInterface`.
> **Why it matters:** Hardcoded dependencies and global static properties cause state-leakage in daemonized environments, and heavy Reflection object creation cripples RPS. The Tenant interface solidifies contract-driven design for multi-tenancy.
> **Action:** Add this to the Magma review list.

### Phase 7
- **DI ErrorHandling**: Refactored `ErrorHandler` to use dependency-injected instances of `JsonErrorPresenterInterface` and `DebugErrorPresenterInterface`, removing static method calls and tight coupling to global state.
- **Pipeline Contract**: Fixed `Magma\pipeline\Pipeline::getSlice` to properly respect custom method names specified by `->via()` instead of strictly enforcing `->process()` on `MiddlewareInterface` instances.
- **Queue Serialization Safety**: Added `JSON_THROW_ON_ERROR` to `RedisQueue` when encoding payloads.
- **DTO Validation via FormRequests**:
  - Replaced manual array access and parameter fetching in `BookingController` endpoints with fully typed FormRequests (`HoldSlotRequest`, `LoginRequest`).
  - Adjusted `SettingsController` to cleanly build `HolidayDTO` via `SaveHolidayRequest->toDTO()`.
- **Pagination Encapsulation**: Refactored pagination logic in `AdminBookingController` (and `AdminBookingQueryInterface`) to use `PaginationDTO` rather than manual offset calculations, bringing it in line with domain standard.
- **Frontend DRY & Data Privacy**: 
  - Extracted Auth-related CSS classes out of global `app.css` into `components/auth.css`.
  - Refactored `filterListByDate` God function in `admin-bookings.js` into a more structured `CalendarView` object.
  - Eliminated the global `window.INITIAL_USER` injection on the `welcome.php` template in favor of an `<script type="application/json">` data island.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Strongly-Typed FormRequest to DTO mapping.
> **Why it matters:** Controllers were acting as an anti-corruption layer for the Command Bus, manually coercing `$_POST` array variables to scalar types. By formalizing a `toDTO()` contract on `FormRequest`, we ensure input boundaries safely transition into domain layers.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Added `ArrayRule` to the Magma Core Validator rule registry (`Magma\validation\rules\ArrayRule`).
> **Why it matters:** Enables declarative array validation (`'items' => 'required|array'`) for nested JSON payloads, lists, and multi-select form structures across all modules without custom boilerplates.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Fixed numeric type-coercion vulnerability in `MaxRule` and `MinRule` validation rules.
> **Why it matters:** Numeric strings (such as phone numbers, postal codes, and identifiers) were previously falling through string-length checks into raw integer magnitude comparisons (`is_numeric($val) && $val > $max`), rejecting valid strings as oversized numbers.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Added `SqlExpression` class and enforced strict identifier validation in `QueryBuilder` and `InsertBuilder`.
> **Why it matters:** Prevents syntax errors and mitigates SQL injection risks by ensuring that identifiers like table and column names conform to strict character patterns while still allowing raw expressions safely when explicitly needed.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Created `MigrationService` to manage execution and recording of database migrations transactionally.
> **Why it matters:** Decouples raw database state manipulation logic from the CLI runner, ensuring that migrations run within an isolated robust service in both CLI and test environments.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Enhanced `Container` with explicit `singleton()` and `instance()` methods for DI caching.
> **Why it matters:** Resolves dependencies cleanly by ensuring instances like singletons are cached and retrieved correctly, while `instance()` allows tests or application bootstrapping to explicitly inject mock or overridden instances into the container.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Drain-and-Exit queue worker execution model for cron invocations alongside adaptive idle sleep for daemon modes in `bin/worker.php`.
> **Why it matters:** Eliminates serverless/REST Redis quota exhaustion and shared-hosting CPU lockups caused by tight idle polling loops.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Driver-level branch in `RedisQueue::pop()` to switch between non-blocking `LPOP` (when timeout <= 0) and blocking `BLPOP` (when timeout > 0).
> **Why it matters:** Prevents process hangs in batch queue drainers caused by Redis `BLPOP key 0` infinite blocking semantics.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Configurable queue throttling (`--throttle`, `QUEUE_THROTTLE_SECONDS`) and multi-attempt retry loop (`pushRaw`, `--max-retries`) with dead-letter queue escalation.
> **Why it matters:** Protects against SMTP provider rate-limit rejections (such as Mailtrap's 1 email/sec limit) and prevents email loss during temporary downstream transport failures by keeping jobs in the queue for retry attempts before DLQ escalation.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** PostgreSQL partial unique indexing pattern (`CREATE UNIQUE INDEX ... WHERE status != 'cancelled'`) on scheduled domain entities instead of rigid table-level unique constraints.
> **Why it matters:** Allows cancelled appointments/orders to be retained in the append-only ledger for audit logs while freeing up their timestamps for customer re-booking without violating SQL unique constraints.
> **Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Transport-level automatic rate-limit backoff and session retry in `SmtpMailTransport`.
> **Why it matters:** Intercepts SMTP `550 5.7.0 Too many emails per second` and `421` rate-limit rejections at the transport layer and transparently pauses/retries, delivering the email cleanly without causing queue job failures or log spam.
> **Action:** Add this to the Magma review list.

---

## FussyBaby Candidates

## Candidate 1: OutboxJobRepository Bulk Insert (`recordBulk`)
**What it is:** Added a `recordBulk(array $jobs)` method to `Magma\database\OutboxJobRepositoryInterface` and its concrete implementation.
**Why it matters:** Enables bulk inserting of outbox jobs in a single parameterised query. This drastically reduces database statement churn and network round-trips for high-throughput operations (like receiving large inventory shipments or importing products) that dispatch many background jobs simultaneously within the same transaction.
**Action:** Add to Magma review list.

## Candidate 2: UploadedFileInterface & GD Exception Handling
**What it is:** Abstraction of the HTTP file upload payloads via `UploadedFileInterface` and strict runtime exception handling within `ext-gd` image processing.
**Why it matters:** It enforces strict SOLID boundaries, prevents arbitrary global `$_FILES` structures from leaking deeply into domain services, and provides resilient error tracing for native PHP image manipulations.
**Action:** Update Magma HTTP and Services core logic.

## Candidate 3: Stricter CQRS Interface Scaffolding
**What it is:** Stricter enforcement of CQRS interfaces and Rich Domain Model encapsulation in the core framework scaffolding.
**Why it matters:** Prevents queries from polluting write repositories and stops application services from absorbing business logic that belongs in the domain entities, ensuring long-term maintainability and easier unit testing.
**Action:** Update the framework's baseline domain generators and repository templates.

## Candidate 4: Strict DTO Boundaries
**What it is:** Universal enforcement of DTO contracts for Data Aggregation and Request Payloads.
**Why it matters:** Eliminates the use of raw `$_POST` arrays and `array_merge()` in business logic, guaranteeing type safety and protecting the persistence layers from untyped HTTP input.
**Action:** Add to Magma review list.

## Candidate 5: CQRS & Immutability Standard
**What it is:** Strict CQRS Repository interfaces mapping and `readonly` domain configuration abstractions.
**Why it matters:** Enforces compile-time immutable configuration throughout tenant states and completely segregates database locks from read-only pools, massively preventing production replica lag anomalies.
**Action:** Add to Magma review list.

## Candidate 6: Outbox, Stateless Middleware & A11y
**What it is:** Fixing DI validation side-effects, implementing stateless middleware, migrating to a true Transactional Outbox pattern, securing DOM templates against XSS, and ARIA components.
**Why it matters:** Resolves fatal dual-write data inconsistencies, eliminates framework-level race conditions under high concurrency, prevents latent cross-site scripting vulnerabilities, and ensures WCAG compliance.
**Action:** Add to Magma review list.

## Candidate 7: Anti-Corruption Layers & UI Abstraction
**What it is:** Strict Anti-Corruption Layer (ACL) interfaces for cross-module interactions and a standardized Vanilla JS MVC/MVVM pattern.
**Why it matters:** Prevents silent coupling between independent bounded contexts, fixes broken event-driven dispatching, and prevents God Objects from accumulating hardcoded inline CSS in the frontend.
**Action:** Add to Magma review list.

## Candidate 8: Native Test Runner Harness & Isolated Testing Infrastructure
**What it is:** Built-in PSR-4 test autoloader mapping (`Tests\`), runtime test configuration mutators (`Config::set`/`Config::reset`), and a base `TestCase` offering isolated DI container bootstrapping (`createContainer()`).
**Why it matters:** Standardizes automated unit, integration, and feature test workflows natively without external Composer dependencies or global state contamination.
**Action:** Add to Magma review list.

## Candidate 9: CQRS Base Repositories Database Connection Handles
**What it is:** Explicit `protected PDO $dbRead` and `protected PDO $dbWrite` properties on `AbstractQueryRepository` and `AbstractCommandRepository` initialized via `DatabaseConnectionManager`.
**Why it matters:** Prevents null-pointer exceptions in child repositories that query read/write connections directly while strictly upholding CQRS connection segregation.
**Action:** Add to Magma review list.

## Candidate 10: Resilient Multi-Context Clipboard Copy Utility in Debug Presenters
**What it is:** Universal clipboard copy helper supporting secure contexts (`navigator.clipboard.writeText`) with fallback to hidden DOM textarea `document.execCommand('copy')` and prompt dialogs for non-HTTPS network IPs (LAN, Tailscale).
**Why it matters:** Guarantees diagnostic stack trace and JSON copying works reliably when testing applications on mobile devices and remote development environments over unencrypted HTTP network interfaces.
**Action:** Add to Magma review list.

## Candidate 11: Multi-App / Multi-Tenant Namespace Isolation in Cache Decorators & Resilient Widget Dispatch
**What it is:** Automatic application and tenant key prefixes in Redis repository decorators (e.g. `CachedVendorQueryRepository`) and polymorphic dispatch in `DashboardWidgetRegistry` supporting both unified `getData()`/`getTemplate()` contracts and legacy `render()` payloads.
**Why it matters:** Prevents cross-project cache collisions when multiple local applications share the same Redis instance, and protects the dashboard view layer against silent widget failures.
**Action:** Add to Magma review list.

## Candidate 12: Declarative Middleware Dependency Resolution with Parameter Configuration Contract
**What it is:** Automated DI container resolution for parameterized middlewares in `MiddlewareResolver` that preserves constructor dependency injection while invoking `configure(...$params)` for route arguments.
**Why it matters:** Eliminates constructor argument mapping collisions when parameterized middlewares (like `RoleMiddleware`) require injected services as well as dynamic route-level parameters.
**Action:** Add to Magma review list.

## Candidate 15: Standardized ContextFacade for Controllers
**What it is:** Introduce standardized `ContextFacade` grouping for controllers.
**Why it matters:** Controllers naturally accumulate common HTTP-scoped dependencies (Request, Session, ResponseFactory, TenantContext, etc.). Injecting 6 distinct HTTP dependencies into every controller violates the SRP threshold. A Context Facade consolidates these foundational dependencies into a single injectable object.
**Action:** Add to Magma review list.

## Candidate 13: Anti-Corruption Layer (ACL) Adapters and Session Hydration Parity in Authentication Service
**What it is:** Consistent session initialization (`$this->login($authUser)`) upon successful credential verification in `AuthenticationService::attempt()` and formalized Anti-Corruption Layer (ACL) contracts across bounded contexts (`InventoryAclInterface`, `VenueCatalogServiceInterface`, `VendorSettingsAclInterface`, `VenueProviderInterface`, `InventoryPricingProviderInterface`).
**Why it matters:** Ensures authenticated session state is established immediately upon web login, preventing downstream authorization guard redirects, while maintaining strict SOLID boundaries between loosely coupled modular sub-domains.
**Action:** Add to Magma review list.

## Candidate 14: DI-compliant Response Factory for BaseController
**What it is:** Introduce a `ResponseFactoryInterface` into the core framework `BaseController` layer to standardize dependency-injected response creation.
**Why it matters:** Controllers currently instantiate responses using `new Response()` natively or violate DIP by returning raw arrays. A DI-compliant Response Factory standardizes outgoing formatting and resolves `new` keyword instantiations.
**Action:** Add to Magma review list.

## Candidate 16: Active Context Resolution via Providers
**What it is:** Moving active context resolution logic (e.g., determining `is_main` venue) out of database repositories (subqueries) and up into Application Services via Context Provider Interfaces.
**Why it matters:** Ensures the repository layer remains purely infrastructural, making queries more performant (no nested subqueries) and making the domain boundary completely decoupled from other modules.
**Action:** Add to Magma review list.
