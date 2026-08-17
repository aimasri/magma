# Magma Framework: A Masterclass in Enterprise Software Architecture

Welcome to the Magma Framework Masterclass. This textbook is a comprehensive, iteratively compiled record of our deep dives into the explicit, vanilla PHP/JS architecture that powers Magma. It serves as both a philosophical guide and a highly technical syllabus for building robust, scalable, and mathematically sound SaaS systems.

---

## Table of Contents
1. [Module 1: The Cost of "Magic" and Explicit Engineering](#module-1-the-cost-of-magic-and-explicit-engineering)
2. [Module 2: The Request Lifecycle & Dual-Mode Kernels](#module-2-the-request-lifecycle--dual-mode-kernels)
3. [Module 3: Dependency Injection & O(1) Memory Management](#module-3-dependency-injection--o1-memory-management)
4. [Module 4: The Pipeline & Middleware Onion Architecture](#module-4-the-pipeline--middleware-onion-architecture)
5. [Module 5: PCRE Routing, FormRequests & Thin Controllers](#module-5-pcre-routing-formrequests--thin-controllers)
6. [Module 6: CQRS Persistence & SERIALIZABLE ACID Compliance](#module-6-cqrs-persistence--serializable-acid-compliance)
7. [Module 7: Pure Domain Driven Design & Engine-Enforced Immutability](#module-7-pure-domain-driven-design--engine-enforced-immutability)
8. [Module 8: The Decoupled Template Engine & O(N) Interpolation](#module-8-the-decoupled-template-engine--on-interpolation)
9. [Module 9: Frontend Architecture: Deep Freeze & CSS Cascade Layers](#module-9-frontend-architecture-deep-freeze--css-cascade-layers)
10. [Module 10: Transactional Outbox & Event-Driven Concurrency](#module-10-transactional-outbox--event-driven-concurrency)
11. [Module 11: Multi-Tenant Security & Static AST Auditing](#module-11-multi-tenant-security--static-ast-auditing)
12. [Module 12: Big-O Optimizations & Developer Diagnostics](#module-12-big-o-optimizations--developer-diagnostics)

---

## Module 1: The Cost of "Magic" and Explicit Engineering

### The Subject & Intent
In modern web development, "magic" refers to framework features that achieve complex results with deceptively simple syntax (e.g., Facades, implicit model bindings, Active Record magic methods). While these tools optimize for rapid prototyping, they introduce dangerous opacity into enterprise systems. In Magma, our intent is the exact opposite: **Radical Explicitness.**

### Analyzing the Principles
The core architectural battle is **Explicit Wiring vs. Implicit Global State**. When a controller calls a static Facade (`Auth::user()`), it becomes permanently, rigidly coupled to a global state machine. It lies about its dependencies. 

By enforcing strict Dependency Injection (DI) and utilizing the SOLID principles (specifically the Dependency Inversion Principle), Magma guarantees that every class explicitly declares what it needs to function via its constructor. This achieves perfect **Inversion of Control (IoC)**, ensuring that components can be tested in total isolation using mocks, without booting a massive underlying framework.

### Framework Comparison
In a "magic" framework, an Active Record entity combines domain logic and database mechanics (`$user->save()`). In Magma, we enforce absolute Separation of Concerns. Entities manage internal invariants, Repositories handle SQL, and Controllers route traffic.

---

## Module 2: The Request Lifecycle & Dual-Mode Kernels

### The Front Controller Pattern
Every HTTP request directed at a Magma application flows through a single entry point: `www/index.php`. This securely seals the web root—all framework logic resides outside the document root, making it impossible for a misconfigured Nginx/Apache server to accidentally serve raw PHP source code.

### Dual-Mode Execution
Magma implements a **Dual-Mode Kernel**. 
- `Application::run()` provides the standard HTTP exit, echoing output to the browser.
- `Application::handle(RequestInterface $request): Response` dispatches requests through the router without outputting headers or echoing markup. This enables headless functional testing, CLI request simulation, and integration with asynchronous worker loops (like Swoole or RoadRunner) without fatal `headers_already_sent` errors.

---

## Module 3: Dependency Injection & O(1) Memory Management

### Recursive Reflection Autowiring
The `Container` is the heart of Magma. It inspects constructor parameter types via PHP's `ReflectionClass` and resolves the dependency graph recursively. 

### Defending Against Memory Leaks
In long-running daemon architectures, in-memory reflection caches can grow infinitely, causing Out-Of-Memory (OOM) crashes. Magma's container is protected by a strict **1000-item Least-Recently-Used (LRU) Cache**.

**The Algorithmic Optimization:**
Standard array shifting (`array_shift()`) in PHP triggers an O(N) re-indexing of the associative array in memory. Under heavy load, this CPU penalty is catastrophic. Magma achieves O(1) LRU eviction by targeting the first key directly:
```php
unset($this->reflectionCache[array_key_first($this->reflectionCache)]);
```
This guarantees constant-time memory safety regardless of the container's load.

### Breaking Circular Dependency Deadlocks
Enterprise DI containers are prone to circular dependency deadlocks (e.g., A needs B, B needs A). Magma resolves this at the foundational level by utilizing nullable setter/optional injection for core providers. For example, `AbstractQueryRepository` accepts a nullable `?TenantContext`. This allows the repository that actually queries the database to *find* the tenant to instantiate without instantly demanding the tenant context it is supposed to fulfill.

---

## Module 4: The Pipeline & Middleware Onion Architecture

### The Onion Architecture
Before a request hits a controller, it passes through a `Pipeline` implementing the Onion Architecture. Middleware layers wrap the application core. A request goes inward through the layers (e.g., Tenant Context, CSRF, Rate Limiting), hits the controller, and the response travels outward (e.g., attaching Security Headers).

### Dual-Mode Middleware Compatibility
Magma natively executes standard closures, object middlewares, and strict PSR-15 Middlewares. Our `TenantSecurityMiddleware` actively hydrates an `AuthUser` object and binds it to the global context before the controller is even instantiated, guaranteeing absolute data isolation across the entire request lifecycle.

---

## Module 5: PCRE Routing, FormRequests & Thin Controllers

### O(1) Regex Routing
Looping through an array of regex patterns to find a matching route is an O(N) operation. Magma's `RouteCompiler` compiles all dynamic routes into a single, chunked regular expression tree. This achieves $O(1)$ routing performance—matching the 1,000th route takes the exact same time as matching the 1st. 

### Declarative FormRequest Auto-Wiring
Controllers must remain "thin". Magma's router automatically detects if an action method type-hints a `FormRequest` subclass. The `RouteParameterResolver` automatically instantiates the request and executes its declarative validation rules *before* the controller logic is ever invoked, preventing dirty data from penetrating the boundary.

---

## Module 6: CQRS Persistence & SERIALIZABLE ACID Compliance

### Strict CQRS Segregation
Magma implements Command Query Responsibility Segregation (CQRS) at the connection level. `AbstractQueryRepository` instances are injected with a read-replica PDO connection (`$dbRead`), while `AbstractCommandRepository` instances receive the write-master (`$dbWrite`).

### Extreme ACID Compliance
PostgreSQL defaults to `READ COMMITTED` isolation, which is vulnerable to phantom reads under extreme concurrency. Magma's `DatabaseTransactionManager` forces the connection into `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE`. 

Crucially, when a transaction begins, the manager intercepts the read-replica connection and routes all active queries to the write-master. This completely eliminates replication-lag bugs and mathematically guarantees data consistency. Furthermore, it utilizes `SAVEPOINT trans_{N}` commands to safely support infinitely nested sub-transactions.

### The Liskov Substitution Principle (LSP) Firewall
In CQRS, base command repositories often provide helper functions for database mutations. If an abstract base class declares `protected function update(string $table, array $data)`, any concrete domain subclass trying to implement a domain interface with `public function update(int $id, array $data)` will crash PHP with a signature mismatch. Magma solves this by strictly segregating internal framework methods (`executeUpdate`, `executeDelete`) from common CRUD terminologies, maintaining a perfect LSP firewall.

---

## Module 7: Pure Domain Driven Design & Engine-Enforced Immutability

### 100% Pure Domain Entities
Behavior belongs with data. However, many architectures corrupt their domain models by passing HTTP-specific DTOs into them. Magma enforces **100% Pure Domain Entities**. Entities like `Review` are completely agnostic of application-layer DTOs; they only accept raw scalars in their constructors. This guarantees absolute boundary segregation between the HTTP layer and the Business Logic layer.

### Engine-Enforced Immutability
All Data Transfer Objects (DTOs) utilize PHP 8.2's native `readonly class` modifiers. Once instantiated, the properties are locked down perfectly at the engine layer. This prevents rogue scripts or dynamic property injections from mutating state mid-flight, eliminating an entire class of side-effect bugs.

---

## Module 8: The Decoupled Template Engine & O(N) Interpolation

### Logic-Less Views
The `TemplateEngine` securely renders HTML while keeping business logic out of the templates. It utilizes decoupled `ViewLoaderInterface` namespaces and strictly prevents scope pollution by passing variables in an isolated `$data` array.

### Multi-Directory Fallback & Resolution Caching
Large SaaS applications store views across multiple directory structures (`views/layouts`, `views/partials`). Magma's `TemplateEngine` intelligently falls back across these directories to resolve layouts. To prevent O(N) `file_exists()` bottlenecks under heavy load, the resolution paths are cached in-memory, ensuring the disk is only queried once per layout per request lifecycle.

### Big-O DOM Interpolation Optimization
When parsing nested templates or loops, standard DOM interpolation suffers from O(N*M) Big-O time complexity as the engine redundantly scans child nodes repeatedly. Magma's JavaScript `TemplateEngine` temporarily detaches nested `[data-loop]` nodes via comment placeholders before evaluating outer directives. This flattens the execution curve to O(N), allowing massive, data-heavy UIs to render instantly.

---

## Module 9: Frontend Architecture: Deep Freeze & CSS Cascade Layers

### Deeply Immutable Reactive State Store
The client-side Vanilla ES6 architecture is as robust as the backend. The `ObservableStore.js` implements the Observer Pattern with automatic subscription lifecycle teardown. Crucially, it employs a recursive `_deepFreeze()` algorithm that physically locks deeply nested state objects from rogue frontend mutations, enforcing unidirectional data flow.

### Defensive Garbage Collection
In dynamic single-page applications, DOM elements are frequently destroyed. Standard event listeners create "zombie" memory leaks by holding references to deleted nodes. Magma's global event delegators utilize defensive `isConnected` checks to gracefully unbind themselves if their target component is ripped from the DOM dynamically.

### CSS Cascade Layers
Specificity wars and `!important` tags destroy maintainability. Magma enforces native CSS Cascade Layers (`@layer reset, tokens, components, utilities, states;`). This permanently structures CSS precedence regardless of file inclusion order.

---

## Module 10: Transactional Outbox & Event-Driven Concurrency

### The Transactional Outbox Pattern
Synchronous background tasks kill web performance. Magma records domain events atomically within the database transaction using an `OutboxJobRepository`. 

### FOR UPDATE SKIP LOCKED
A continuous background daemon (`bin/outbox_publisher.php`) polls this table. To prevent multiple parallel workers from processing the same event and causing a race condition, Magma relies on PostgreSQL's native `FOR UPDATE SKIP LOCKED` locking primitive. This guarantees exactly-once delivery with zero lock-contention CPU churn.

---

## Module 11: Multi-Tenant Security & Static AST Auditing

### Pluggable Tenant Scoping
In a SaaS environment, data bleeding between clients is fatal. `TenantContext` actively scopes all queries and requests.

### Static AST Boundary Auditing
Magma provides a static analysis linter (`bin/audit_schema.php`) that parses the Abstract Syntax Tree (AST) of the codebase. It actively verifies that multi-tenant foreign keys are correctly indexed and statically prohibits direct superglobal usage (`$_POST`, `$_GET`) inside business services, failing the CI/CD pipeline if boundaries are breached.

---

## Module 12: Big-O Optimizations & Developer Diagnostics

### Constant-Time B-Tree Keyset Pagination
Standard SQL `OFFSET` degrades linearly (O(N)), causing database lockups on deep pages. Magma utilizes Keyset seeking (`WHERE id > :cursor_last_id`), leveraging B-Tree indexes for instantaneous O(1) performance regardless of dataset size.

### Memory-Streaming Generators
Repositories returning large collections do not load arrays into memory. They stream records using PHP generators (`yield`). This keeps RAM consumption flat even when exporting 100,000 rows.

### Interactive Diagnostics Boundary
When `APP_DEBUG=true`, errors are rendered via a beautiful, interactive stack trace diagnostic tool. When `APP_DEBUG=false` in production, output buffers are scrubbed and a secure 500 error page is shown with zero system path disclosure, guaranteeing security through obscurity.

---

### Conclusion
By intentionally discarding "magic" and embracing explicit engineering, strict typing, algorithmic optimizations, and mathematical ACID safety, the Magma Framework stands as a definitive blueprint for enterprise software architecture.
