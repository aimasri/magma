# Magma Framework: The Educational Architecture Core

Welcome to the Magma Framework source code. This repository is intentionally designed as an **instructional codebase**. It demonstrates how to build a robust, scalable, and secure web application using vanilla PHP **without relying on heavy, black-box frameworks** like Laravel or Symfony. 

By exploring this codebase, you will learn the fundamental architectural patterns that power all modern web frameworks, with thorough, docblock-level explanations of *how* and *why* the components interact. This README serves as the ultimate syllabus and overview for understanding the framework's design.

## Table of Contents
1. [Introduction & Philosophy](#01-introduction--philosophy)
2. [The Request Lifecycle & Front Controller](#02-the-request-lifecycle--front-controller)
3. [The Dependency Injection Container](#03-the-dependency-injection-container)
4. [The Pipeline & Middleware Onion Architecture](#04-the-pipeline--middleware-onion-architecture)
5. [Routing & Thin Controllers](#05-routing--thin-controllers)
6. [Data Persistence: The Repository Pattern](#06-data-persistence-the-repository-pattern)
7. [Domain Logic, Services & CQRS](#07-domain-logic-services--cqrs)
8. [The Decoupled Template Engine](#08-the-decoupled-template-engine)
9. [Security, DTOs, & Error Handling](#09-security-dtos--error-handling)
10. [Asynchronous Processing & Event-Driven Queues](#10-asynchronous-processing--event-driven-queues)
11. [High-Performance Optimization Techniques](#11-high-performance-optimization-techniques)
12. [Advanced Production Considerations & Roadmap](#12-advanced-production-considerations--roadmap)

---

## 01. Introduction & Philosophy

**TSP, The Sandbox Platform:** TSP is a generic software platform based in the cloud, specializing in various domain entities. The architecture is designed from the ground up to be highly adaptable, built to seamlessly support both massive, multi-tenant environments and isolated, single-tenant applications depending on the specific project requirements.

**The Core Framework Ecosystem (Magma, Lava, Basalt):** TSP is powered by a foundational, pure PHP framework currently known as **Magma**. Magma represents the pure, "no magic" core codebase. As the platform evolves—if fundamental structural changes are ever required (such as introducing monolithic functions or "magic" classes)—the framework will branch into new, distinct evolutionary states such as **Lava** or **Basalt**, rather than bloating Magma with feature sets. Secondary ecosystem tools remain agnostic and compatible across all states.

**The Engineering Philosophy:** Modern frameworks hide a massive amount of complexity behind "magic" static methods and facades. This project intentionally removes the magic at the Magma level. Every action is explicitly wired together using clean architecture principles:

* **SOLID Principles:** Classes have a Single Responsibility and rely on Dependency Inversion. We favor injecting interfaces rather than instantiating concrete classes.
* **Separation of Concerns (SoC):** Controllers never query the database. Views never handle business logic. Data access is completely isolated in Repositories.
* **Pragmatic Domain-Driven Design (DDD):** We enforce a strict rule: *Behavior belongs with the data*. We utilize "skinny" domain entities that manage their own state and internal logic, while Services act strictly as orchestrators. We build these models iteratively as the domain is discovered.
* **Instructional Docblocks:** Every core file contains a standardized comment block explaining its *Purpose*, *Why this design was chosen*, and specific *Teaching notes*. Method docblocks map the exact *Execution Flow*.
* **Strict Typing:** All methods utilize strict scalar type hints and return types (PHP 8+), ensuring data predictability and avoiding silent casting errors.

---

## 02. The Request Lifecycle & Front Controller

The application utilizes the **Front Controller** pattern. Every HTTP request made to the server (whether it's an API call, an image load, or a page visit) routes through a single entry point: `www/index.php`.

1. **Bootstrapping:** `index.php` initializes the environment, sets up PSR-4 autoloading via the `bootstrap.php` script, and loads environment variables.
2. **Containerization:** The `Container` is built, mapping core interfaces to their concrete implementations.
3. **Execution:** The `Application->run()` method is called. It resolves the incoming HTTP request, passes it through the Middleware pipeline, and hands it to the Router.

**Why this design?**
By funneling all requests through one file, we guarantee that critical security checks (like session initialization and CSRF validation) can never be accidentally bypassed. The `www/` directory is the only folder exposed to the web server; all application logic sits safely outside the document root in `magma/`.

---

## 03. The Dependency Injection Container

At the heart of the framework is `core/container/Container.php`. 

Instead of using `new ClassName()` scattered across the codebase (which creates tight coupling and makes unit testing impossible), classes declare their dependencies in their constructor. The Container uses PHP's **Reflection API** to inspect the constructor, automatically instantiate any required dependencies, and inject them recursively.

**Key Educational Concepts:**
* **Autowiring:** You rarely need to manually bind classes; the container figures out the dependency graph automatically. If Class A needs Class B, and Class B needs Class C, the container builds C, injects it into B, and injects B into A.
* **Service Providers:** Complex bindings are grouped into logical providers (e.g., `HttpServiceProvider`, `RepositoryServiceProvider`) to keep the bootstrapping phase clean and modular.
* **Inversion of Control:** By injecting dependencies, it becomes trivial to swap out implementations (e.g., swapping a `MemoryCache` for a `RedisCache`) without rewriting the underlying business logic.
* **Configuration Isolation:** Even global configuration settings are wrapped in a `ConfigInterface` and injected. Domain services never interact with global static state or `.env` files directly, guaranteeing testability across different environments.

---

## 04. The Pipeline & Middleware Onion Architecture

Before a request reaches a Controller, it must pass through a generic `Pipeline` processor using the **Onion Architecture** pattern (`core/pipeline/Pipeline.php`).

Middleware layers wrap around the application core like layers of an onion. A request travels *inward* through the layers, hits the controller, and the resulting response travels *outward* through the same layers.

**Key Middleware Components:**
* `MiddlewareResolver`: A dedicated factory that translates string identifiers into instantiated `MiddlewareInterface` objects.
* `SessionTimeoutMiddleware`: Enforces timeouts on stale sessions to protect user accounts.
* `CsrfMiddleware`: Validates CSRF tokens on state-mutating requests (POST/PUT/DELETE) before they reach business logic.
* `RateLimitMiddleware`: Uses atomic, memory-backed Redis commands (`INCR` and `EXPIRE`) to track failed attempts by IP address to prevent brute-force attacks.

**Why this design?**
It strictly decouples cross-cutting concerns from Controllers. A Controller shouldn't need to know how to validate a CSRF token or check rate limits; it should just trust that the request wouldn't have reached it if the security constraints were violated.

---

## 05. Routing & Thin Controllers

The `Router` (`core/routing/Router.php`) maps URL patterns to Controller methods. 

**Static OPcache Routing:** 
To maximize performance, routes are defined in a serializable array format and compiled into a static `routes.cache.php` file using a build script (`bin/cache_routes.php`). This allows PHP's OPcache to load the entire routing table directly from shared memory, bypassing the CPU overhead of evaluating closures and compiling regex strings on every request.

**The "Thin Controller" Pattern:**
Controllers in this application act purely as traffic cops. They:
1. Receive the HTTP `Request` object. **Superglobal Encapsulation:** Controllers never access superglobals (`$_GET`, `$_POST`, `$_SERVER`) directly. All HTTP context, including secure connection detection, is abstracted behind this object to eliminate untestable global state.
2. Validate input using strongly-typed Data Transfer Objects (DTOs) and `FormRequest` validation logic.
3. Delegate business actions to a specific Domain Service.
4. Return an HTTP `Response` (HTML View, JSON, or Redirect).

You will *never* see SQL or complex validation loops inside a controller. If a controller method exceeds 20-30 lines, it is likely violating the Single Responsibility Principle.

---

## 06. Data Persistence: The Repository Pattern

Data access is entirely encapsulated using the **Repository Pattern**.

* **BaseRepository:** An abstract class centralizes the injection of the `PDO` instances, eliminating boilerplate.
* **Read/Write Splitting:** The application leverages two distinct PDO connections: `$dbRead` and `$dbWrite`. This allows horizontal scaling (directing heavy `SELECT` queries to read-replicas, while routing `INSERT/UPDATE/DELETE` statements to the master node).
* **Strict Isolation:** Repositories isolate all SQL statements. If the database schema changes, you only update the repository. We actively avoid `SELECT *` in favor of explicitly naming required columns to enable database Index-Only scans.
* **Data Mappers:** Repositories focus strictly on data access. Transforming raw SQL rows into domain objects or arrays is explicitly delegated to separate Mapper classes (like `VendorMapper`).

**Transactions:** Complex operations spanning multiple tables are wrapped in an explicit `TransactionManagerInterface` (`transactional(callable $callback)`) to guarantee ACID compliance.

---

## 07. Domain Logic, Services & CQRS

The application's core business rules are structured using Pragmatic Domain-Driven Design (DDD), deliberately splitting logic between **Domain Entities** and **Services**.

**Domain Entities ("Skinny Entities"):**
Behavior belongs with the data. Rather than passing loose associative arrays around ("Transaction Script" pattern), we encapsulate data into strictly-typed Domain Entities (e.g., `Review`, `UserRegistration`, `PasswordResetToken`, `InventoryMovement`, `AuthUser`). These entities own their internal state, manage default values, and perform their own data sanitization and cryptography (like hashing passwords or generating secure tokens). They never execute SQL.

**Services ("Thin Orchestrators"):**
When an action requires coordinating multiple entities, repositories, or external systems, it belongs in a **Service**. Services (like `RegistrationService` or `PasswordResetService`) act purely as orchestrators. They instantiate the necessary Domain Entities and pass them to the Repositories or event queues, keeping the service logic remarkably thin and focused on workflow rather than data manipulation.

**Command Query Responsibility Segregation (CQRS):**
For highly complex domain areas like Inventory Management, the architecture is split into separate read and write models:
* **The Write Model (Event Ledger):** All stock movements are recorded as immutable events in an `inventory_transactions` table. We never update a single "total" row directly (Event Sourcing).
* **The Read Model (Materialized View):** Background jobs calculate the aggregate totals from the ledger and save them to a hyper-fast cached table (`vendor_inventory`) so the frontend can query real-time stock at O(1) speed.

---

## 08. The Decoupled Template Engine

The frontend is rendered using a custom `TemplateEngine` (`core/view/TemplateEngine.php`). 

* **Dependency Inversion & File I/O:** The engine relies on a `ViewLoaderInterface` to manage filesystem operations. This decouples the engine from the physical disk, simplifying unit testing.
* **Strict Scope Decoupling:** The engine avoids internal scope pollution (`extract()`); variables are strictly accessed via the `$data['var']` array. Security components (like CSRF tokens) are explicitly injected into this `$data` context via composition (`$data['engine']`), completely decoupling the engine from the global HTTP state.
* **XSS Prevention:** Views utilize explicit `htmlspecialchars()` encoding (or the `$data['engine']->escape()` helper) to sanitize all user-generated content before rendering it into the DOM.

---

## 09. Security, DTOs, & Error Handling

* **Data Transfer Objects (DTOs):** Data flowing between boundaries is packaged into strongly-typed DTOs (e.g., `ReviewDTO`). These classes use PHP 8.1 `readonly` properties to ensure immutability, providing strict contracts and IDE autocomplete.
* **Global Exception Catching:** The `Application` kernel wraps execution in a `try/catch` block. If a fatal error occurs, an `ErrorHandler` intercepts it, cleans the output buffer (preventing half-rendered pages), logs the trace, and displays a user-friendly 500 error page.
* **Form Requests:** Form validation is centralized via `FormRequest` classes, which automatically trap bad data, flash old input to the session, and seamlessly redirect the user back to the form with comprehensive error messages.

---

## 10. Asynchronous Processing & Event-Driven Queues

To provide instant HTTP response times to users, heavy tasks (like sending emails or rebuilding CQRS materialized views) are offloaded to a background queue.

* **Queue Infrastructure:** The application utilizes a lightweight, dependency-free queue built natively on Redis Lists (`RPUSH` and `BLPOP`).
* **The Polymorphic Strategy Pattern:** When the web server pushes a job, it serializes the job's payload alongside the fully-qualified class name of the handler (e.g., `core\jobs\SendPasswordResetEmailJob`).
* **Payload Standardization:** To eliminate magic strings and hidden schema drifts, all queue-pushing services utilize standardized constants from `JobInterface` (`HANDLER_KEY`, `PAYLOAD_KEY`) to ensure a rigid contract between producers and the worker daemon.
* **Worker Daemon:** A standalone CLI script (`bin/worker.php`) runs infinitely in the background. It polls the queue, dynamically resolves the requested handler class from the Dependency Injection container, and executes it. Adding new background jobs requires zero modification to the worker itself (Open/Closed Principle).

---

## 11. High-Performance Optimization Techniques

Magma is designed to remain highly responsive under heavy load. Advanced optimization techniques include:

* **Keyset (Cursor-Based) Pagination:** Deep pagination using `OFFSET` causes linear CPU degradation in SQL databases. We use Keyset Pagination (`WHERE id > :cursor_id ORDER BY id ASC LIMIT X`) to leverage B-Tree indexes, guaranteeing O(1) fetch times regardless of how deep the user paginates.
* **PHP Generators (`yield`):** Repositories returning multiple records use the `yield` keyword instead of `fetchAll()`. This streams records one-by-one, maintaining a near-zero memory footprint.
* **Table Partitioning:** Heavy append-only ledgers use PostgreSQL Declarative Partitioning by date, ensuring that insert speeds do not degrade as the table grows to millions of rows.

---

## 12. Advanced Production Considerations & Roadmap

> **Note:** The following tasks represent the final layer of polish required before an enterprise-grade application goes to production. While not actively developed within the core educational framework scope, they are critical considerations for real-world deployments.

- [ ] **[Medium]** **CDN & Asset Minification:** 
  * *Purpose:* Serve static CSS/JS through Cloudflare/Fastly.
  * *Intent:* Offload bandwidth costs and improve global load times by placing assets physically closer to end users.
  * *Proposed Strategy:* Implement an asset build step (e.g., Vite/Webpack) to minify files and point the resulting asset URLs to a CDN provider.

- [ ] **[Medium]** **Containerization & Observability:** 
  * *Purpose:* Implement Docker, Monolog (PSR-3), and Application Performance Monitoring (APM).
  * *Intent:* Enable reliable, automated deployments and provide engineers with the telemetry needed to debug distributed production issues.
  * *Proposed Strategy:* Create a `Dockerfile` and `docker-compose.yml` to define the PHP-FPM, Nginx, Redis, and PostgreSQL services as reproducible infrastructure.
