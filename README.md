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

**The Engineering Philosophy:** Modern frameworks hide a massive amount of complexity behind "magic" static methods and facades. This project intentionally removes the magic at the Magma level. Every action is explicitly wired together using clean architecture principles:

* **SOLID Principles:** Classes have a Single Responsibility and rely on Dependency Inversion. We favor injecting interfaces rather than instantiating concrete classes.
* **Separation of Concerns (SoC):** Controllers never query the database. Views never handle business logic. Data access is completely isolated in Repositories.
* **Pragmatic Domain-Driven Design (DDD):** We enforce a strict rule: *Behavior belongs with the data*. We utilize "skinny" domain entities that manage their own state and internal logic, while Services act strictly as orchestrators. We build these models iteratively as the domain is discovered.
* **Instructional Docblocks:** Every core file contains a standardized comment block explaining its *Purpose*, *Why this design was chosen*, and specific *Teaching notes*. Method docblocks map the exact *Execution Flow* and the "logic behind the logic."
* **Strict Typing:** All methods utilize strict scalar type hints and return types (PHP 8+), ensuring data predictability and avoiding silent casting errors.

---

## 02. The Request Lifecycle & Front Controller

The application utilizes the **Front Controller** pattern. Every HTTP request made to the server routes through a single entry point: `www/index.php`.

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
* **Strict Dependency Inversion:** We actively decouple foundational HTTP layers from concrete implementations. For example, the `Request` object receives its `Session` dependency via constructor injection (`createFromGlobals(?Session $session = null)`), preventing the hardcoding of `new Session()` and allowing for robust unit testing.
* **Autowiring & Reflection Caching:** The container figures out the dependency graph automatically. To maximize speed, it caches reflection data in memory, avoiding the massive CPU overhead of repeated `ReflectionClass` instantiations.
* **Service Providers:** Complex bindings are grouped into logical providers (e.g., `HttpServiceProvider`, `RepositoryServiceProvider`) to keep the bootstrapping phase clean and modular.

---

## 04. The Pipeline & Middleware Onion Architecture

Before a request reaches a Controller, it must pass through a generic `Pipeline` processor using the **Onion Architecture** pattern (`core/pipeline/Pipeline.php`).

Middleware layers wrap around the application core like layers of an onion. A request travels *inward* through the layers, hits the controller, and the resulting response travels *outward* through the same layers.

**Key Middleware Components:**
* `TenantSecurityMiddleware`: Ensures absolute data isolation for SaaS deployments. Rather than manipulating raw untyped session arrays (which causes brittle code and feature envy), this middleware hydrates a strictly-typed `AuthUser` domain entity from the session. It then securely delegates property access (like `$user->getVendorId()`) to bind the tenant context, rigorously upholding the Law of Demeter.
* `CsrfMiddleware`: Validates CSRF tokens on state-mutating requests (POST/PUT/DELETE) before they reach business logic.
* `RateLimitMiddleware`: Uses atomic, memory-backed Redis commands (`INCR` and `EXPIRE`) to track failed attempts by IP address to prevent brute-force attacks.

---

## 05. Routing & Thin Controllers

The `Router` (`core/routing/Router.php`) maps URL patterns to Controller methods. 

**The "Thin Controller" Pattern & SRP:**
Controllers in this application act purely as traffic cops. We strictly enforce the **Single Responsibility Principle**. For example, rendering the homepage and handling review submissions are split into two completely separate controllers (`HomeController` and `ReviewController`). This prevents God-classes and allows each controller to inject only the services it absolutely needs.

Controllers execute a rigid flow:
1. Receive the HTTP `Request` object. All HTTP context is abstracted behind this object to eliminate untestable global state (no `$_GET` or `$_POST`).
2. Validate input using strongly-typed Data Transfer Objects (DTOs) and `FormRequest` validation logic.
3. Delegate business actions to a specific Domain Service.
4. Return an HTTP `Response` (HTML View, JSON, or Redirect). Using the PRG (Post/Redirect/Get) pattern guarantees users never accidentally double-submit forms on a page refresh.

---

## 06. Data Persistence: The Repository Pattern

Data access is entirely encapsulated using the **Repository Pattern**.

* **BaseRepository:** An abstract class centralizes the injection of the `PDO` instances, eliminating boilerplate.
* **Read/Write Splitting:** The application leverages two distinct PDO connections: `$dbRead` and `$dbWrite`. This allows horizontal scaling (directing heavy `SELECT` queries to read-replicas, while routing `INSERT/UPDATE/DELETE` statements to the master node).
* **Chunked Bulk Inserts:** The repository abstracts chunking and transaction management for massive bulk inserts, protecting against maximum parameter exhaustion errors native to PDO drivers.
* **Strict Isolation:** Repositories isolate all SQL statements. We actively avoid `SELECT *` in favor of explicitly naming required columns to enable database Index-Only scans.

---

## 07. Domain Logic, Services & CQRS

The application's core business rules are structured using Pragmatic Domain-Driven Design (DDD), deliberately splitting logic between **Domain Entities** and **Services**.

**Domain Entities ("Skinny Entities"):**
Behavior belongs with the data. We encapsulate data into strictly-typed Domain Entities (e.g., `Review`, `AuthUser`). These entities own their internal state, manage default values, and perform their own data sanitization. They never execute SQL.

**Services ("Thin Orchestrators"):**
Services (like `InventorySyncService` or `PasswordResetService`) act purely as orchestrators. They inject domain repositories strictly via Interfaces (Dependency Inversion Principle) and execute workflow logic. They instantiate Domain Entities and pass them to Repositories or Event Queues, keeping the service logic focused purely on business rules rather than data manipulation.

**Command Query Responsibility Segregation (CQRS):**
For highly complex domain areas like Inventory Management, the architecture is split into separate read and write models:
* **The Write Model (Event Ledger):** All stock movements are recorded as immutable events. We never update a single "total" row directly (Event Sourcing).
* **The Read Model (Materialized View):** Background jobs calculate the aggregate totals from the ledger and save them to a hyper-fast cached table so the frontend can query real-time stock at O(1) speed.

---

## 08. The Decoupled Template Engine

The frontend is rendered using a custom `TemplateEngine` (`core/view/TemplateEngine.php`). 

* **Dependency Inversion:** The engine relies on a `ViewLoaderInterface` to manage filesystem operations, decoupling the engine from the physical disk.
* **In-Memory Path Caching:** To prevent severe I/O degradation during large loops (e.g. rendering 500 product cards via partials), the engine caches path existence checks in memory, hitting the disk exactly once per unique view file.
* **XSS Prevention:** Views utilize explicit `htmlspecialchars()` encoding (or the `$data['engine']->escape()` helper) to sanitize all user-generated content before rendering it into the DOM.

---

## 09. Security, DTOs, & Error Handling

* **Data Transfer Objects (DTOs):** Data flowing between boundaries is packaged into strongly-typed DTOs (e.g., `ReviewDTO`). These classes use PHP 8.1 `readonly` properties to ensure immutability, providing strict contracts and IDE autocomplete.
* **Global Exception Catching:** The `Application` kernel wraps execution in a `try/catch` block. If a fatal error occurs, an `ErrorHandler` intercepts it, cleans the output buffer (preventing half-rendered pages), logs the trace, and displays a user-friendly 500 error page.

---

## 10. Asynchronous Processing & Event-Driven Queues

To provide instant HTTP response times and prevent blocking requests, heavy architectural tasks (such as global database synchronizations) are offloaded to background queues.

* **Preventing N+1 Blockages:** Code like `InventorySyncService` avoids synchronous iterations over large database sets by instead pushing discrete `SyncVendorInventoryJob` jobs to a `QueueInterface`.
* **Queue Infrastructure:** The application utilizes a lightweight, dependency-free queue built natively on Redis Lists (`RPUSH` and `BLPOP`).
* **The Command/Job Pattern:** Jobs injected into the queue implement a strict `JobInterface` and accept their dependencies (like Repositories) via constructor injection, allowing the background worker to resolve and execute them flawlessly via the DI container.
* **Worker Daemon:** A standalone CLI script (`bin/worker.php`) runs infinitely in the background. It polls the queue, dynamically resolves the requested handler class, and executes it. Adding new background jobs requires zero modification to the worker itself (Open/Closed Principle).

---

## 11. High-Performance Optimization Techniques

Magma is designed to remain highly responsive under heavy load. Advanced optimization techniques include:

* **Lazy JSON Payload Parsing:** The `Request` object defers the deserialization of `application/json` payloads until the data is explicitly accessed.
* **Keyset (Cursor-Based) Pagination:** Deep pagination using `OFFSET` causes linear CPU degradation in SQL databases. We use Keyset Pagination (`WHERE id > :cursor_id ORDER BY id ASC LIMIT X`) to leverage B-Tree indexes.
* **PHP Generators (`yield`):** Repositories returning multiple records use the `yield` keyword instead of `fetchAll()`. This streams records one-by-one, maintaining a near-zero memory footprint.

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
