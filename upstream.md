# Magma Upstream Candidates Review List

_Clean list for new candidates._

## Async AJAX Support for MagmaCombobox Component
**What it is:** The `MagmaCombobox` (Vanilla JS) component was updated to native
ly support debounced asynchronous data fetching (`ajaxUrl`), custom data extract
ion (`extractText`, `extractValue`), and custom rich HTML rendering (`renderOpti
on`).
**Why it matters:** Evolving `MagmaCombobox` to handle dynamic asynchronous data
 fetching enables us to cleanly replace all bespoke inline `fetch` autocomplete
implementations with a robust, DRY component across the entire application suite
. This adheres to SOLID principles and keeps our core views extremely clean.

## Dynamic Data Propagation Support for MagmaCombobox Component
**What it is:** Allowing `MagmaCombobox` to accept dynamic or wildcard attribute
 propagation (e.g., `data-propagate="allergens,unit,ingredients"`).
**Why it matters:** Currently, extending the combobox payload requires manually
updating the Javascript class and backend queries for each new data attribute (l
ike we had to do today for `data-ingredients`). Making this configuration-driven
 ensures better SOLID practices and data portability for future catalog enhancem
ents.

## StorageInterface and FileStorageService abstraction
**What it is:** A `StorageInterface` abstraction for file system operations (Bas
e64 & multipart form payloads).
**Why it matters:** Removes direct `move_uploaded_file()` and `mkdir()` usage an
d hardcoded file paths from controllers. This satisfies the Dependency Inversion
 Principle, vastly improves unit testability by enabling disk mocking, and paves
 the way for cloud-native setups (e.g., AWS S3).

## ES6 Module Architecture for JavaScript Assets
**What it is:** Transition monolithic frontend JavaScript files into modern, dec
oupled ES6 modules.
**Why it matters:** Radically shrinks Javascript payload complexity and makes UI
 components highly reusable and testable.

## MenuPropagationService Domain Logic Extraction
**What it is:** Extracting complex cross-venue menu propagation logic out of the
 standard Repository into a dedicated Service.
**Why it matters:** Keeps raw SQL repositories decoupled from business logic (ad
hering to SOLID principles), making propagation paths explicit and testable.


## Universal StorageInterface injection in Domain Services
**What it is:** Universal `StorageInterface` injection for file uploads in Domai
n Services.
**Why it matters:** Controllers were tightly coupled to raw `move_uploaded_file`
 and `mkdir` calls. Abstracting this behind a `StorageInterface` that is injecte
d into Domain Services ensures that file handling is completely abstracted (e.g.
, allowing an easy switch to S3 later) and keeps controllers thin.

## Multi-Tenancy TenantContext Security Abstraction
**What it is:** Multi-Tenancy `TenantContext` Security abstraction.
**Why it matters:** Hardcoding vendor IDs or extracting them repeatedly from ses
sions within controllers/repositories violates Single Responsibility and makes t
he app brittle. Moving to a dedicated `TenantContext` promotes a robust, secure,
 and easily testable multi-tenant architecture.

## Request::build() Static Builder
**What it is:** The `Request::build()` static builder.
**Why it matters:** Offloading JSON decoding and HTTP verb spoofing from the `Re
quest` constructor to a dedicated builder method adheres to the Single Responsib
ility Principle. It creates a more robust dependency injection pathway that is e
asier to mock in unit tests and safely encapsulates global `php://input` streams
.

## CQRS Read-Model Optimization for Repositories
**What it is:** CQRS Read-Model Optimization for Repositories.
**Why it matters:** Large CTEs and cost calculations in Repositories (like `Reci
peRepository`) block standard domain operations and cause N+1 performance bottle
necks. Upstreaming a standardized Read-Model or Query-Object pattern into the Ma
gma core will allow modules to separate complex dashboard queries from standard
CRUD operations.

## CQRS Repository Splitting Pattern
**What it is:** CQRS Repository Splitting Pattern
**Why it matters:** Splitting complex read models from write models prevents repository bloat and allows read queries to be heavily optimized (e.g., using CTEs or materialized views) without breaking write logic.
**Action:** Add this to the Magma review list.

## View Modularization & Standardized CQRS Segregation
**What it is:** View modularization strategy using TemplateEngine partials, and standardized CQRS segregation rules for repositories.
**Why it matters:** Enforces SOLID principles to keep views and repositories maintainable and testable over the lifetime of the framework.
**Action:** Add this to the Magma review list.

## Widget-based Dashboard Engine
**What it is:** Widget-based Dashboard Engine
**Why it matters:** Eliminates 'God Classes' like `DashboardAggregatorService` that require 10+ repository injections by allowing independent widgets (e.g., `InventoryWidget`, `SchedulingWidget`) to register themselves and resolve their own dependencies. This significantly improves scalability, modularity, and testability across the framework.
**Action:** Add this to the Magma review list.

## Provider-Level Dashboard Widget Registration
**What it is:** A central `DashboardWidgetRegistry` that enables modules to register their own UI widgets dynamically during application bootstrap via Service Providers.
**Why it matters:** It completely decouples the dashboard UI orchestration from the underlying domain modules. This enables true multi-tenancy and tiered subscriptions because modules (and their widgets) can be dynamically loaded or omitted based on a tenant's subscription tier without breaking the core `HttpServiceProvider` or `DashboardAggregatorService`.
**Action:** Add this to the Magma review list for incorporation into the core framework module system.

## Tenant Context & Dashboard Registration
**What it is:** The `DashboardWidgetRegistry` and `VendorContext` services.
**Why it matters:** Multi-tenant resolution and dynamic dashboard widget registration are core infrastructural requirements for virtually any modular SaaS application. Upstreaming these prevents downstream modules from having to re-implement standard tenant contexts.
**Action:** Add to the Magma review list.

## Dashboard Widget Plugin Architecture
**What it is:** Dashboard Widget Strategy via `DashboardWidgetInterface.php` and Soft Dependency Registration.
**Why it matters:** The interface allows dynamic registration of dashboard analytics without modifying core controllers. Wrapping `DashboardWidgetRegistry` usage in a `try/catch` allows modules to boot cleanly in non-UI environments (CLI, API, workers) where dashboard dependencies aren't loaded, heavily improving IoC resilience.
**Action:** Add to the Magma review list.

## Generic Caching Decorators
**What it is:** Redis-backed implementations like `CachedBrandRepository`, `CachedProductTypeRepository`, and `CachedAllergenRepository`.
**Why it matters:** Implementing a generic `CachedRepositoryDecorator` base class within the core framework could standardize caching for all simple dictionary/taxonomy tables across all modules, drastically reducing boilerplate and ensuring consistent TTL enforcement while cleanly adhering to the Open/Closed Principle.
**Action:** Add to the Magma review list.

## CQRS Repository Architecture
**What it is:** strict separation via Command, Query, and Analytics interfaces.
**Why it matters:** Strictly separating read-only queries, state-modifying commands, and aggregate analytics fulfills SOLID principles and prevents repository "God Classes". This structural pattern can serve as a robust baseline for all future Magma data layers.
**Action:** Add to the Magma review list.

## Progressive JS Refactoring Pattern
**What it is:** Class-based UI Controllers and Global `window` Adapters (e.g., in `admin-staff.js`).
**Why it matters:** Allows modules to incrementally refactor away from messy inline scripts into clean, testable JavaScript classes without requiring an immediate, complete rewrite of all legacy view files. Highly reusable for modernization efforts.
**Action:** Add to the Magma review list.

## View Model / Presenter enforcement for complex views (e.g., StaffPresenter)
**What it is:** View Model / Presenter enforcement for complex views (e.g., `StaffPresenter`).
**Why it matters:** Enforces the MVC pattern by strictly banning inline business logic and function declarations within view templates, significantly improving testability of view state.
**Action:** Add this to the Magma review list.

## Domain Service Injection for Background Workers
**What it is:** Extraction of CQRS logic (such as calculating the Weighted Average Cost) into pure, isolated Domain Services (e.g., `InventoryValuationService`) rather than executing domain math inside the Repository layer or inside Background Jobs directly.
**Why it matters:** It ensures maximum code reusability across web controllers, background workers, and API layers, while keeping the persistence repositories ignorant of complex business rules, making the entire platform highly testable (pure unit tests without database mocks).
**Action:** Add this pattern (Service Injection into Worker Jobs) to the Magma review list as a standard requirement for future background jobs handling domain-heavy tasks.

## Multi-Tenancy Security Middleware
**What it is:** Multi-Tenancy Security Middleware.
**Why it matters:** Controllers like `StaffMemberController` currently must manually request `$tenantId = $this->tenantContext->getVendorId();`. If a developer forgets this (or hardcodes `1`), data leaks across tenants. The framework should implement a global middleware that implicitly binds the `tenantId` to the `Request` object or Repository context, completely removing the developer's ability to accidentally hardcode it.
**Action:** Add this to the Magma review list.

> [!MAGMA UPSTREAM CANDIDATE]
> **What it is:** Universal Timezone Resolver Service.
> **Why it matters:** Centralizes geolocation/timezone logic instead of hardcoding text checks like `str_contains('new york')` inside controllers. Allows for future API integrations (like Google Maps API) without changing controller logic.
> **Action:** Add this to the Magma review list.

## Base JSON API Throwable Exception Handling
**What it is:** Update all base JSON API controllers to catch `\Throwable` instead of `\Exception`.
**Why it matters:** PHP 7/8 introduced `\Error` for fatal runtime issues (like `TypeError` or `DivisionByZeroError`). Catching only `\Exception` allows these errors to bypass JSON formatting and return HTML stack traces, breaking frontend XHR parsers silently.
**Action:** Add this to the Magma review list.

## ES Module DOMContentLoaded Race Condition Fix
**What it is:** ES Module `DOMContentLoaded` Race Condition Fix
**Why it matters:** Modules loaded with `<script type="module">` defer execution, meaning `DOMContentLoaded` has often already fired by the time the script runs. Using a standard `document.readyState === 'loading'` check prevents silent failures of initialization scripts across the framework.
**Action:** Add this resilient init pattern to the Magma review list for all standard JS module entry points.

## CSRF Token Rotation Pause for AJAX/API Requests
**What it is:** Disabled CSRF token rotation for AJAX/API requests in `CsrfMiddleware`.
**Why it matters:** Prevents rapid token exhaustion and false-positive `403 Forbidden` errors when users interact with dynamic, debounced forms (like live pricing calculators) that fire multiple background requests rapidly, pushing the DOM's token out of the grace period window.
**Action:** Add this to the Magma review list.

## Global Event Delegation & Decoupled Form State Extraction
**What it is:** Global Event Delegation Architecture (`MenuListeners.js`) and Decoupled Form State Extraction (`MenuItemState.js`).
**Why it matters:** Replacing inline `onclick` and `onchange` HTML handlers with centralized event listeners bounded to class selectors (`.js-xxx`) drastically cleans up the HTML partials. Moving state extraction into a dedicated class allows for easy unit testing of forms without needing an active DOM.
**Action:** Add this to the Magma review list. It needs to be abstracted and added to Magma as a core frontend service with a generic name (e.g., `EventDelegationService` and `FormStateExtractionService`).

## Strict Typed DTO Access Pattern
**What it is:** Strict typed DTO access pattern.
**Why it matters:** Dropping `\ArrayAccess` overhead improves memory and CPU efficiency, while pushing developers towards strongly typed, object-oriented patterns with autocompletion support.
**Action:** Add this strictly-typed DTO pattern without ArrayAccess as a standard to the Magma review list.

## Unified JS Pub/Sub Form State Architecture
**What it is:** Transitioning form state management to a unified JS `Pub/Sub` architecture.
**Why it matters:** It standardizes how complex forms trigger chained DOM updates without relying on tightly-coupled event listeners scattered throughout the controllers, making form logic highly predictable and testable.
**Action:** Add this to the Magma review list.

## Enriched Multi-Line Combobox Lookup & Selected Card View
**What it is:** Multi-line enriched Combobox Lookup with `data-label` input decoupling, unit-cost badge integration, and rich 2-line selected card rendering in `MagmaCombobox`.
**Why it matters:** Allows chosen items in autocomplete comboboxes to render as structured 2-line cards (Title + Unit Price badge on row 1, specs on row 2) matching the exact appearance of dropdown options, while seamlessly transitioning back to text input when clicked to edit.
**Action:** Add this to the Magma review list.

## Reflection-based Auto-wiring DI Container
**What it is:** Reflection-based Auto-wiring DI Container
**Why it matters:** Eliminates manual binding errors in Service Providers (like the recent 500 Hiccup) by automatically resolving constructor dependencies via reflection type-hints.
**Action:** Add this to the Magma review list.
