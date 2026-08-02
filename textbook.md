# Magma Framework: A Masterclass in Software Architecture

Welcome to the Magma Framework Masterclass. This textbook is an iteratively compiled record of our deep dives into the explicit, vanilla PHP architecture that powers Magma.

---

## Table of Contents
1. [Chapter 1.1: The Cost of "Magic" in Modern Frameworks](#chapter-11-the-cost-of-magic-in-modern-frameworks)

---

### Chapter 1.1: The Cost of "Magic" in Modern Frameworks

#### The Subject & Intent
In modern web development, "magic" refers to framework features that achieve complex results with deceptively simple syntax, often hiding the underlying mechanics from the developer. Think of Laravel’s Facades (e.g., `Cache::get('key')`) or Ruby on Rails’ Active Record callbacks. 

While magic allows for rapid prototyping and feels incredibly intuitive for beginners, it introduces a dangerous level of opacity into an enterprise system. When things go wrong in a "magical" framework—when a query is unexpectedly executed twice, or a global state mutation causes a race condition—debugging becomes a nightmare because the execution path is hidden behind dynamic method resolution and proxy classes. 

In Magma, our intent is the exact opposite: **Radical Explicitness.** We prioritize readability and predictable execution over syntactic sugar.

#### Analyzing the Principles
The core architectural principle at play here is **Explicit Wiring vs. Implicit Global State**. 

When a framework relies heavily on static Facades, it is essentially creating globally mutable state. If your `HomeController` calls `Auth::user()`, your controller is now permanently, rigidly coupled to the framework's authentication package. It cannot be easily tested in isolation because it secretly reaches out to the global ether to fetch data.

> **Historical Context:** 
> The allure of "magic" heavily gained traction in the mid-2000s with the rise of Ruby on Rails. "Convention over Configuration" was a revelation, drastically reducing the boilerplate XML configurations seen in Java frameworks (like early Spring). However, as these monolithic applications aged, the software engineering community realized that implicitly connecting components made codebases notoriously difficult to refactor and scale. This led to a resurgence of explicitly wired, Dependency Injection-focused architectures in the 2010s.

#### The "Why" & Framework Comparison
Let's explicitly compare how a popular "magic" framework (like Laravel) handles a simple task versus how we handle it in Magma.

**The "Magic" Approach (Laravel):**
```php
public function store()
{
    // Where did Request come from? It's a static facade pulling from global state.
    $title = Request::input('title'); 
    
    // DB is also a facade. The controller is completely coupled to the database.
    DB::table('posts')->insert(['title' => $title]); 
}
```
*Why this is problematic at scale:* If you want to write a unit test for this method, you have to boot up the *entire framework* to mock the `Request` and `DB` facades. The controller is lying about its dependencies; looking at its constructor, you wouldn't know it needs a database connection at all.

**The "Explicit" Approach (Magma):**
```php
class PostController 
{
    private Request $request;
    private PostRepositoryInterface $repository;

    // The controller honestly declares exactly what it needs to function.
    public function __construct(Request $request, PostRepositoryInterface $repository) 
    {
        $this->request = $request;
        $this->repository = $repository;
    }

    public function store(): Response 
    {
        $title = $this->request->input('title');
        $this->repository->create(['title' => $title]);
        
        return new Response(200);
    }
}
```
*Why this is superior for our architecture:* 
By forcing the dependencies through the constructor, we achieve **Inversion of Control**. The controller doesn't know *how* the Request was built, nor does it know if the `PostRepositoryInterface` is saving to MySQL, Redis, or a plain array in memory. It just orchestrates. This makes unit testing incredibly trivial (we just pass in fake versions of those classes) and guarantees that our codebase is immune to hidden side effects.

#### Common Questions and Answers
**Q: How does injecting the repository directly affect our ability to write automated tests compared to the Facade approach?**
**A:** Isolation! As you correctly pointed out, by injecting an interface, we can test *only* the controller. We can pass in a "mock" repository (a fake class that just pretends to save to a database) so we aren't accidentally testing the database connection or waiting for actual SQL queries to run during our test suite.

**Q: What is the primary benefit that keeps frameworks like Laravel using facades so heavily, if it's considered "silly" or harmful to testing?**
**A:** It often seems silly from a strict architectural standpoint, but the primary benefit is **Developer Velocity and Ergonomics**. Facades mean a developer doesn't have to write constructors, understand Dependency Injection, or manage complex container bindings. They can just type `Cache::get()` anywhere in the application and it instantly works. It optimizes for the *speed of writing code* (which is highly profitable for startups trying to launch an MVP quickly), whereas explicit architecture optimizes for the *ease of reading and maintaining code* years down the line.

---

### Chapter 1.2: SOLID Principles & Dependency Inversion in Practice

#### The Subject & Intent
SOLID is an acronym for five design principles intended to make software designs more understandable, flexible, and maintainable. In Magma, the most critical of these is the **Dependency Inversion Principle (DIP)**. DIP states that high-level modules should not depend on low-level modules; both should depend on abstractions (interfaces).

Our intent is to eradicate the `new` keyword from our business logic. Whenever you type `new ClassName()`, you are permanently welding your code to that specific implementation.

#### Analyzing the Principles
By relying on Dependency Inversion, we allow our Dependency Injection (DI) Container to wire the application together at runtime. 

> **Historical Context:**
> Robert C. Martin (Uncle Bob) formalized the SOLID principles in the early 2000s. Before Dependency Inversion was widely adopted, enterprise software (like early C++ or Java applications) suffered from extreme rigidity. A change in a low-level database driver would cascade up, forcing developers to rewrite the high-level UI controllers. DIP severed this link.

#### The "Why" & Framework Comparison
In a typical magic framework, you might see a controller instantiating a mailer directly: `$mailer = new SmtpMailer();`. In Magma, our controllers expect a `MailTransportInterface`. 
This allows us to bind `NativeMailTransport` in production, but bind a `MockMailTransport` during testing. We never touch the controller's code when switching environments.

---

### Chapter 1.3: Separation of Concerns (SoC) & Strict Typing

#### The Subject & Intent
Separation of Concerns (SoC) is the practice of dividing a computer program into distinct sections, where each section addresses a separate concern. In our architecture, this means drawing rigid boundaries: Controllers handle HTTP, Repositories handle SQL, and Views handle HTML.

Strict Typing (introduced robustly in PHP 7 and perfected in PHP 8) is our architectural safety net. We explicitly define the types of every argument and return value.

#### Analyzing the Principles
By enforcing SoC, we prevent "God Classes." A controller that reads from the `$_GET` superglobal, executes a PDO query, and echoes HTML is impossible to maintain. 
By enforcing Strict Typing (`declare(strict_types=1);`), we prevent PHP from silently coercing data (like turning the string `"5 apples"` into the integer `5`), which is a massive source of silent bugs in legacy PHP.

#### The "Why" & Framework Comparison
Many older frameworks rely on loose associative arrays passing through the system (the "Transaction Script" pattern). Magma explicitly rejects this. We use strict scalar types and Data Transfer Objects (DTOs) so that our IDEs can autocomplete and our static analysis tools (like PHPStan) can catch errors before the code is even run.

---

### Chapter 1.4: Pragmatic Domain-Driven Design (DDD)

#### The Subject & Intent
Domain-Driven Design (DDD) dictates that the structure and language of your code should match the business domain. The core rule we adopt is: **Behavior belongs with the data.**

#### Analyzing the Principles
We utilize **Skinny Entities** and **Thin Orchestrators** (Services).
An entity like `AuthUser` isn't just an array of data; it's an object that knows how to validate its own state or extract its `vendor_id`. However, it doesn't know how to save itself to the database. That orchestration is delegated to a Service.

#### The "Why" & Framework Comparison
Active Record (used by Laravel's Eloquent or Ruby on Rails) merges the entity and the database connection into a single class (e.g., `$user->save()`). While incredibly convenient, it fundamentally violates the Single Responsibility Principle. The User object now has to know about database connection pools and SQL syntax. In Magma, our entities are plain PHP objects, ensuring they remain blazingly fast and completely decoupled from infrastructure.

---

## Module 2: The Request Lifecycle & Front Controller

### Chapter 2.1: The Front Controller Pattern

#### The Subject & Intent
Every HTTP request directed at a Magma application—whether for a web page, an API endpoint, or an asset—flows through a single entry point: `www/index.php`. This is the **Front Controller Pattern**.

#### Analyzing the Principles
By having a single point of entry, we establish a centralized location for bootstrapping. We can initialize our autoloader, load environment variables, and instantiate our core Application kernel exactly once. 

> **Historical Context:**
> In the late 90s (the CGI era), applications often had separate entry points for every page (e.g., `login.php`, `profile.php`, `cart.php`). If a developer forgot to include `check_auth.php` at the top of a new file, the page was entirely unsecured. The Front Controller pattern eradicated this class of vulnerability by guaranteeing a unified execution pipeline.

#### The "Why" & Framework Comparison
Modern frameworks all use Front Controllers, but Magma takes security a step further. We strictly isolate the `www/` directory as the only directory exposed to the web server (Nginx/Apache). All proprietary application logic sits safely one level above the document root in the `magma/` folder, making it impossible for a misconfigured server to accidentally serve raw PHP source code to an attacker.

---

## Module 3: The Dependency Injection Container

### Chapter 3.1: The Core of Autowiring

#### The Subject & Intent
At the heart of Magma is the `Container` (`core/container/Container.php`). Its intent is to automatically resolve and instantiate classes along with their entire dependency tree using PHP's **Reflection API**.

#### Analyzing the Principles
This is the ultimate expression of **Inversion of Control (IoC)**. Instead of a class requesting its dependencies (by instantiating them), the framework *injects* them into the constructor from the outside. 
Furthermore, we group related bindings into **Service Providers** (like `HttpServiceProvider`), which cleanly module-izes our bootstrapping phase.

#### The "Why" & Framework Comparison
In massive monolithic frameworks like Symfony, the container is often compiled into a static PHP file during a build step for maximum performance. Magma takes a different approach: we use runtime Reflection but aggressively cache the reflection data in memory. This gives us the extreme flexibility of runtime resolution without the O(N) CPU penalty on subsequent lookups. We also explicitly employ memory leak protection, refusing to cache negative lookups (non-existent classes), which prevents OOM attacks from malicious inputs.

---

## Module 4: The Pipeline & Middleware Onion Architecture

### Chapter 4.1: The Onion Architecture

#### The Subject & Intent
Before a request hits a controller, it passes through the `Pipeline` (`core/pipeline/Pipeline.php`). Middleware layers wrap the application core like layers of an onion. A request goes inward through the layers, hits the controller, and the response travels outward.

#### Analyzing the Principles
This represents the **Decorator/Chain of Responsibility Pattern**. Each middleware handles one highly specific cross-cutting concern: CSRF validation (`CsrfMiddleware`), rate limiting (`RateLimitMiddleware`), or tenant isolation (`TenantSecurityMiddleware`).

> **Historical Context:**
> Middleware as an "onion" was heavily popularized by Python's WSGI and Ruby's Rack before becoming the de-facto standard in modern PHP (PSR-15). It elegantly solved the problem of controllers being bloated with authentication checks and header manipulations.

#### The "Why" & Framework Comparison
Many frameworks allow middleware to be defined dynamically at the controller level or even inside route closures. Magma registers them in a strict pipeline sequence. Our `TenantSecurityMiddleware` actively hydrates an `AuthUser` object and binds it to the global context before the controller is even instantiated, guaranteeing absolute data isolation across the entire request lifecycle. A controller *cannot* execute if the security context fails.

---

## Module 5: Routing & Thin Controllers

### Chapter 5.1: O(1) Routing and the Traffic Cop

#### The Subject & Intent
The `Router` maps URL patterns to Controllers. Our controllers are strictly "Thin Controllers"—they act only as traffic cops coordinating requests and responses.

#### Analyzing the Principles
We strictly enforce the **Single Responsibility Principle (SRP)**. If a controller method exceeds 20-30 lines, it is violating SRP. Furthermore, controllers NEVER access superglobals (`$_GET`, `$_POST`) directly; they rely on an encapsulated `Request` object.

#### The "Why" & Framework Comparison
Many frameworks loop through an array of regex patterns to find a matching route (an O(N) operation). Magma compiles all dynamic routes into a single, massive regular expression using PCRE `(*MARK:name)` verbs. This achieves O(1) routing performance—matching the 1,000th route takes the exact same time as matching the 1st. 

Additionally, we enforce the PRG (Post/Redirect/Get) pattern. After processing a form, the controller issues a `RedirectResponse`. This prevents users from duplicating submissions if they hit the "refresh" button on their browser.

---

## Module 6: Data Persistence: The Repository Pattern

### Chapter 6.1: Encapsulating the Database

#### The Subject & Intent
The **Repository Pattern** acts as a bridge between the domain and data mapping layers, acting like an in-memory collection of domain objects. In Magma, no SQL exists outside of the `magma/models` repositories.

#### Analyzing the Principles
This adheres to **Separation of Concerns**. We implement Read/Write splitting directly at the BaseRepository layer. By routing `SELECT` queries to a read-replica PDO instance and `INSERT/UPDATE` queries to the master instance, we build in horizontal scalability from day one.

#### The "Why" & Framework Comparison
ORMs (Object-Relational Mappers) like Laravel's Eloquent or Doctrine are notorious for producing N+1 query bugs, where looping over objects triggers hundreds of hidden SQL queries. By forcing the use of explicit Repositories and Data Mappers, Magma ensures that the developer must explicitly define their SQL joins. We avoid `SELECT *` in favor of precise column selection, enabling lightning-fast Index-Only scans at the database level.

---

## Module 7: Domain Logic, Services & CQRS

### Chapter 7.1: The Write Model and The Read Model

#### The Subject & Intent
In complex domains (like inventory or finance), calculating totals on the fly is too slow. We use **Command Query Responsibility Segregation (CQRS)** to separate the act of writing data from the act of reading data.

#### Analyzing the Principles
When an order is placed, we record a ledger entry (Event Sourcing) into `inventory_transactions`. This is our **Write Model**. 
We never read directly from this table for the frontend. Instead, background jobs crunch those numbers and save the aggregate totals into a `vendor_inventory` table. This is our **Read Model** (or Materialized View).

#### The "Why" & Framework Comparison
A traditional CRUD framework would try to `UPDATE inventory SET stock = stock - 1 WHERE item_id = 5`. Under high concurrency, this causes database row locks and deadlocks. By inserting an immutable ledger record instead, we achieve infinite write-scalability. The read model is then eventually consistent but can be queried at O(1) speed.

---

## Module 8: The Decoupled Template Engine

### Chapter 8.1: Logic-less Views

#### The Subject & Intent
The Magma `TemplateEngine` (`core/view/TemplateEngine.php`) is built to securely render HTML while keeping PHP logic strictly out of the templates.

#### Analyzing the Principles
By injecting a `ViewLoaderInterface`, the engine adheres to **Dependency Inversion**. It can load views from the local disk or an external bucket. Furthermore, the engine strictly prevents scope pollution by passing variables in an isolated `$data` array rather than using `extract()`.

#### The "Why" & Framework Comparison
Template engines like Blade or Twig compile into PHP strings. While powerful, they often tempt developers to write database queries or complex `if` statements directly in the HTML. Magma enforces logic-less views, requiring the controller or a Presenter layer to format the data *before* it reaches the view.

---

## Module 9: Security, DTOs, & Error Handling

### Chapter 9.1: Bulletproof Boundaries

#### The Subject & Intent
Data Transfer Objects (DTOs) package data crossing architectural boundaries. 
Global Exception Catching guarantees the application never fails silently or leaks a stack trace.

#### Analyzing the Principles
We use PHP 8.1 `readonly` properties for DTOs to enforce **Immutability**. Once a `ReviewDTO` is created, its state cannot be altered, eliminating an entire class of side-effect bugs.

#### The "Why" & Framework Comparison
In PHP, throwing an unhandled exception results in a "Fatal Error" that halts the script and often leaves half-rendered HTML on the screen. Magma's Kernel wraps the entire execution in a `try/catch`. It scrubs the output buffer and safely renders a generic 500 error view, ensuring a professional user experience even during a catastrophic database outage.

---

## Module 10: Asynchronous Processing & Event-Driven Queues

### Chapter 10.1: Offloading the Main Thread

#### The Subject & Intent
Web requests must be fast. Tasks like sending emails or syncing the CQRS read models are offloaded to a background queue.

#### Analyzing the Principles
We use the **Command/Job Pattern**. A controller pushes a class name (e.g., `SyncVendorInventoryJob`) and a payload to Redis. A standalone worker daemon (`bin/worker.php`) pulls this off the queue, uses the DI Container to instantiate the Job (satisfying its dependencies), and executes it.

#### The "Why" & Framework Comparison
Many simple frameworks rely on Cron jobs for background tasks, which only run every minute and can easily overlap. Magma uses a true persistent worker daemon blocking on Redis (`BLPOP`), meaning jobs execute instantaneously with zero polling overhead. By adhering to the **Open/Closed Principle**, adding a new job type requires zero modifications to the worker script.

---

## Module 11: High-Performance Optimization Techniques

### Chapter 11.1: Squeezing Out Every Millisecond

#### The Subject & Intent
Magma utilizes advanced techniques to prevent CPU and memory spikes under load.

#### Analyzing the Principles
*   **Lazy JSON Parsing:** `Request` only decodes JSON if explicitly asked, saving memory if a firewall middleware drops the request early.
*   **Keyset Pagination:** We avoid SQL `OFFSET`, which degrades linearly (O(N)), and instead use `WHERE id > last_seen_id`, leveraging B-Tree indexes for O(1) performance.
*   **Generators (`yield`):** Repositories stream records one at a time, keeping memory usage flat even when exporting 100,000 rows.

---

## Module 12: Advanced Production Considerations & Roadmap

### Chapter 12.1: The Final Polish

#### The Subject & Intent
To take Magma to an enterprise production environment, we look beyond the code to the infrastructure.

#### Analyzing the Principles
*   **CDN & Asset Minification:** Offloads static file delivery to edge servers, physically reducing latency.
*   **Containerization (Docker):** Guarantees that the environment (PHP version, Redis, PostgreSQL) is absolutely identical across local, staging, and production.
*   **Observability (APM):** Implementing tools like Datadog or New Relic to monitor memory leaks or N+1 queries in real-time, because you cannot fix what you cannot measure.
