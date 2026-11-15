# Magma Framework: The Masterclass Textbook


## Module 1: Introduction & Philosophy

### Chapter 1.1: The Domain Context & The Platform Vision

#### Subject & Intent: Understanding the "Why" Before the "How"
In software engineering, code is simply a tool used to solve a specific human problem. Before we write a single line of PHP, we must intimately understand the **Domain**—the business environment in which our software will operate. If we build an elegant architecture that solves the wrong business problem, we have failed as engineers.

In our case, the Magma Framework is a generic, robust software platform located in the cloud. We specialize in enterprise-grade architecture, specifically distinguishing ourselves with a "no magic" approach.

This gives us immediate clues about our domain entities and data structures:
*   **Data Structures:** We aren't dealing with simple, unstructured arrays. We use strict Data Transfer Objects (DTOs) and Domain Models.
*   **Services & Repositories:** Business logic is decoupled from data access. State isn't just a database row; it's managed through strict, isolated layers.
*   **Requests & Responses:** We are dealing with clear HTTP lifecycles, robust middleware pipelines, and structured input/output.

However, the defining characteristic of our architecture is the **Platform Vision**.

While a single organization might be our *first* client (our "Tenant"), our intent is to design this system from the ground up as a platform capable of supporting *multiple* distinct vendors in the future. This concept is known as **Multi-Tenancy**.

#### Analyzing the Principles: Designing for Multi-Tenancy from Day One
It is a common trap in software development to hardcode business logic for a single client, assuming you can "generalize it later." Generalizing a massive, tightly-coupled codebase later is incredibly expensive and error-prone. As we established, hardcoding rules for one vendor means they will inevitably be incorrectly applied to future vendors.

Instead, we are adopting a platform-first mindset.

> **Historical Context:** In the early days of SaaS (Software as a Service), companies often stood up a completely separate database and codebase for every new client (Single-Tenant). While secure, this became a nightmare to maintain and deploy. The industry shifted toward Multi-Tenant architectures, where a single instance of the application and database serves multiple clients, using a `tenant_id` to strictly separate data.

To achieve this in the Magma Framework framework, we apply the following principles:

1.  **Strict Data Isolation at the Repository Layer:** By establishing the Repository Pattern early, we ensure that every database query can eventually be scoped to a specific `vendor_id`. A controller will never accidentally query `SELECT * FROM orders`; it will always ask the repository for `Orders for Vendor X`. This mitigates the massive risk of cross-tenant data leakage—the most critical danger in a shared database environment.
2.  **Agnostic Core Domain:** The core application doesn't care about a specific tenant's business. It only understands abstract concepts: `Vendors`, `Products`, `Orders`, and `Inventory`. The specifics are data, not code.
3.  **Configuration over Hardcoding:** If a vendor has a specific rule, we abstract this into a configurable business rule associated with the vendor's profile, rather than burying it in `if/else` statements within our services.

**Conceptual OOP Illustration: Hardcoding vs. Abstraction**

Imagine a hypothetical `OrderService` where a specific vendor ("Vendor A") does not allow orders to be placed on Sundays: 

*The Wrong Way (Hardcoded Logic):*
```php
class OrderService 
{
    public function placeOrder(OrderDTO $order): bool 
    {
        // BAD: Hardcoding a specific tenant's rule into the core platform!
        $dayOfWeek = date('l', strtotime($order->deliveryDate));
        if ($dayOfWeek === 'Sunday') {
            throw new Exception("Vendor A is closed on Sundays.");
        }
        
        // ... proceed with order
    }
}
```

*The Right Way (Configurable Business Rule):*
```php
class OrderService 
{
    // We inject the specific Vendor configuration into the service
    public function placeOrder(OrderDTO $order, VendorProfile $vendor): bool 
    {
        // GOOD: The core logic asks the Vendor's configuration if the day is valid.
        // The fact that it's Sunday is now purely data, not code.
        $dayOfWeek = date('l', strtotime($order->deliveryDate));
        
        if (!in_array($dayOfWeek, $vendor->getOperatingDays())) {
            throw new Exception("This vendor does not operate on " . $dayOfWeek);
        }
        
        // ... proceed with order
    }
}
```

#### The "Why" & Framework Comparison
Why are we taking this approach explicitly, rather than relying on a framework to do it for us?

Many popular frameworks (like Laravel) offer multi-tenancy packages. These packages often employ "magic" behind the scenes: they might automatically intercept your database queries and inject a `WHERE tenant_id = X` clause without you seeing it.

While convenient, this **hidden complexity is dangerous for learning**. If a developer doesn't understand *how* the query is being scoped, they cannot debug it when it breaks or optimize it when it scales. They become reliant on the "magic." Furthermore, as we discussed, explicit passing of context leaves far less room for error. 

In the Magma framework, we reject this magic. Our multi-tenancy preparation is explicit. When we load a tenant's context, we inject it directly into our services. The developer can trace the exact flow of execution from the HTTP request down to the SQL statement, fostering a deep, unbreakable understanding of the system's architecture.

---

### Chapter 1.2: The Engineering Philosophy: Unmasking the "Magic"

#### Subject & Intent: The Cost of Convenience
If you want to build a web application today, you have dozens of phenomenal frameworks at your disposal—Laravel and Symfony in PHP, Ruby on Rails, Django in Python, or Next.js in JavaScript. These frameworks are incredibly powerful and power a massive portion of the internet.

However, they achieve their incredible developer velocity by employing what engineers affectionately (and sometimes disparagingly) call **"Magic."**

Magic in software engineering refers to abstractions where the framework handles complex logic behind the scenes, without the developer needing to explicitly write the code or even understand how it works. 

While magic allows junior developers to ship features quickly, it becomes a severe liability when:
1.  **Things break:** If you don't know how a query is constructed, you can't debug the stack trace when it fails.
2.  **Performance bottlenecks emerge:** If the framework is eagerly loading data behind the scenes, you can't easily optimize it.
3.  **You are trying to *learn* architecture:** A framework teaches you how to use *that specific framework*. It does not necessarily teach you underlying software engineering principles.

Our philosophy for Magma Framework is to entirely strip away the magic. We want you to see the plumbing. 

#### Analyzing the Principles: The Facade Anti-Pattern
Let's look at a specific principle: **Dependency Inversion** (the 'D' in SOLID). This principle states that high-level modules should not depend on low-level modules; both should depend on abstractions (interfaces).

> [!NOTE] 
> **Professor's Definitions:**
> *   **Dependency:** An object that another object relies on to do its job. If your `ProductService` needs to save to a database, the `DatabaseConnection` is a dependency.
> *   **Abstraction (Interface):** A contract that defines *what* a class can do, without defining *how* it does it. Instead of depending on a specific `MySQLDatabase`, you depend on a generic `DatabaseInterface`.
> *   **Static Method:** A method that belongs to the class itself, not to a specific object instance. You can call it without using the `new` keyword (e.g., `Cache::get()`). Because it belongs to the class globally, it acts essentially like a global variable, which is dangerous for testability.

Modern frameworks often violate or obscure the Dependency Inversion principle for the sake of developer ergonomics. 

> **Historical Context:** The popularization of "Magic" largely began with Ruby on Rails in the mid-2000s, which championed "Convention over Configuration." It assumed that if you named your database table `users` and your class `User`, it would dynamically wire everything together. Laravel brought this philosophy to PHP, heavily utilizing a pattern called **Facades**.

A Facade in Laravel allows you to call non-static methods statically. For example, to get data from the cache, you might write:

```php
// The "Magic" Framework Way
$value = Cache::get('key'); 
```

This looks clean! But what is `Cache`? It's a static proxy. Behind the scenes, the framework is doing gymnastics: reaching into a global container, finding the bound cache instance, and executing the method. 

**Why is this problematic for learning?**
1.  **Hidden Dependencies:** As we discussed, when dependencies are scattered throughout the method bodies as static calls, you have no "manifest" of what the class needs. If I look at your class constructor, I have no idea that your class requires a Cache system to function. It makes refactoring incredibly dangerous because it's easy to miss a hidden dependency.
2.  **Global State:** Facades essentially act as global variables.
3.  **Testing Nightmare:** Mocking static methods for unit tests requires complex reflection or specialized testing libraries (like Mockery), rather than simply passing a fake object into a constructor.

#### The "Why" & Framework Comparison: The Explicit Alternative
In the Magma Framework architecture, we demand **Explicit Dependencies**. If a core controller or service needs an authentication service or cache, it must declare it explicitly in its constructor (or method signature for application/module controllers); never instantiate hidden infrastructure inside business logic.

```php
<?php

declare(strict_types=1);

namespace Modules\Reviews\controllers;

use Magma\http\Response;
use Magma\http\RedirectResponse;
use Modules\Reviews\requests\ReviewRequest;
use Modules\Reviews\interfaces\ReviewSubmissionServiceInterface;

// The Explicit "Magma" Way (from modules/Reviews/controllers/ReviewController.php)
class ReviewController
{
    public function submitReview(
        ReviewRequest $reviewRequest,
        \Magma\security\TenantContext $tenantContext,
        ReviewSubmissionServiceInterface $reviewSubmissionService,
        \Magma\http\SessionInterface $session
    ): Response {
        $dto = $reviewRequest->toDTO();
        $reviewSubmissionService->submit($dto, $tenantContext->getTenantId());

        $session->set(
            \App\constants\AppConstants::SESSION_SUCCESS_MESSAGE,
            \Modules\Reviews\constants\ReviewConstants::MSG_REVIEW_SUBMITTED
        );
        return new RedirectResponse('/');
    }
}
```

**The Danger of the `new` Keyword (Tight Coupling)**
You asked earlier why using `$mailer = new SmtpMailer();` inside a controller is almost as bad as using a Facade. 

The answer is **Tight Coupling**. If you write `new SmtpMailer()` directly inside your code, your controller is permanently glued to that exact SMTP class. If tomorrow the business says, "We are switching to another mail transport," you have to open the controller and rewrite the code.

If instead you injected a contract like `ReviewSubmissionServiceInterface` or `CacheInterface` (`Magma\interfaces\CacheInterface`), you would simply change the binding in your Service Provider (which we will cover in Module 3), and the controller *never has to change*. The `new` keyword prevents your code from being flexible!

**The Comparison Summary:**
Why do we choose explicit injection? 
*   **Readability:** The signature acts as an honest contract. Anyone looking at `ReviewController::submitReview()` instantly knows every subsystem it relies on.
*   **Testability:** To test code depending on `CacheInterface`, we simply inject an in-memory `ArrayCache` instead of a live `RedisCache`. No global state or complex mocking frameworks required.
*   **Swappability:** Because we ask for an `Interface`, we can swap `RedisCache` for `ArrayCache` in configuration without changing a single line of business logic.

By forcing ourselves to be explicit, we are forced to think about the design of our system, leading to naturally looser coupling and higher cohesion.

---

### Chapter 1.3: The Four Pillars of Clean Architecture

#### Subject & Intent: The Rules of Engagement
If we are stripping away the "magic" of frameworks, what replaces it? The answer is **Discipline**. 

When you don't have a framework automatically policing where you put your database queries or how you validate your forms, you must rely on strict architectural principles. In the Magma Framework architecture, every single file we write is governed by four core pillars.

#### Analyzing the Principles

##### Pillar 1: The SOLID Principles
SOLID is an acronym coined by Robert C. Martin (Uncle Bob) representing five design principles intended to make object-oriented designs more understandable, flexible, and maintainable. 

While we will see these in action constantly, here is a brief overview tailored to our context:
1.  **Single Responsibility Principle (SRP):** A class should have one, and only one, reason to change. A `VendorRepository` handles database queries. It does *not* format HTML.
2.  **Open/Closed Principle (OCP):** Software entities should be open for extension but closed for modification. If we add a new Payment Gateway (Stripe vs PayPal), we shouldn't have to rewrite the `CheckoutService`. We simply create a new class that implements the `PaymentGatewayInterface`.
3.  **Liskov Substitution Principle (LSP):** If you swap a parent class for its child class (or an interface for an implementation), the application shouldn't break or behave unexpectedly. 
    > [!TIP]
    > **The Magma LSP Analogy:** Imagine you have an `ServiceInterface` with a `execute(Module $module)` method. Your `StandardService` implements this perfectly. If you substitute it with a `FastService` class, and calling `execute()` causes the module to instantly explode instead of executing, you have violated LSP. The substitute failed to honor the fundamental contract of the parent!
4.  **Interface Segregation Principle (ISP):** Don't force a class to implement methods it doesn't need. Better to have many small, specific interfaces than one massive, general-purpose one.
5.  **Dependency Inversion Principle (DIP):** Depend upon abstractions (interfaces), not concretions (specific classes). We covered this heavily in Chapter 1.2.

##### Pillar 2: Strict Separation of Concerns (SoC)
Separation of Concerns is the concept of breaking a computer program into distinct sections, such that each section addresses a separate concern.

In our framework, the boundaries are fiercely guarded:
*   **Controllers** never query the database. They act purely as "Traffic Cops"—taking an HTTP request, handing it to a service, and returning an HTTP response.
*   **Repositories** contain 100% of the SQL. No SQL is allowed anywhere else.
*   **Views (Templates)** contain zero business logic. They simply loop over the data given to them and output HTML.

> **Historical Context:** Early PHP (often called "Spaghetti Code") famously mixed HTML, database connections, and business logic all in the same `.php` file. SoC was formalized to stop this madness, eventually giving rise to the MVC (Model-View-Controller) pattern.

##### Pillar 3: Instructional Docblocks
Because this is an educational framework, the code itself is the textbook. 

Every core file contains a standardized comment block explaining its **Purpose**, **Why** this design was chosen, and specific **Teaching Notes**. Method docblocks map the exact Execution Flow. We treat the code as living literature. If the intent of a class isnt perfectly clear from its Docblock, it's considered a bug.

##### Pillar 4: Strict Typing
PHP is traditionally a dynamically typed language (meaning a variable could be a string, and then become an integer later). While flexible, this causes catastrophic, silent bugs at an enterprise scale.

In our architecture, we leverage modern PHP (8+) features relentlessly. Every file begins with `declare(strict_types=1);`. Every method parameter has a type hint, and every method has a return type.

```php
// The Old Way (Dangerous)
public function calculatePrice($amount, $taxRate) {
    return $amount * $taxRate; 
}

// The Magma Way (Safe & Predictable)
public function calculatePrice(float $amount, float $taxRate): float {
    return $amount * $taxRate;
}
```

**The Friction of Strict Typing**
As you correctly noted, strict typing introduces friction, especially on the frontend! The web operates via HTTP, and HTTP requests (`$_POST`, `$_GET`) are entirely text-based. 

If a user submits a form saying `price=10.50`, PHP receives the string `"10.50"`. If we pass that string directly into our strictly typed `calculatePrice(float $amount)` method, the application will instantly crash with a Fatal Error. 

To solve this friction without compromising our strict core, we introduce **Data Transfer Objects (DTOs)** (which we cover in Module 9). The DTO acts as a "bouncer" at the door. It catches the dirty, string-based HTTP request, validates it, and explicitly casts `"10.50"` into a pure `float 10.50`. Only then is it allowed into the strictly-typed core of the application.

---


## Module 2: The Request Lifecycle & Front Controller

### Chapter 2.1: The Front Controller Pattern & The `www/` Boundary

#### Subject & Intent: The Single Gateway
In the early days of PHP web development, building a website meant creating a series of individual `.php` files. If you went to `website.com/about.php`, the server executed `about.php`. If you went to `website.com/contact.php`, the server executed `contact.php`.

While simple, this "Page Controller" pattern created massive duplication. Every single file had to manually include the database connection, start the session, and check if the user was logged in. If a developer forgot to add the session check to just *one* file, the entire application was compromised.

To solve this, modern applications use the **Front Controller Pattern**.

In the Magma Framework architecture, every single HTTP request—whether it's asking for an HTML page, an API JSON response, or submitting a form—is funneled through one single entry point.

#### File Walkthrough: `www/index.php`
Let's look at the actual code for our Front Controller. This is pulled directly from our workspace (`www/index.php`):

```php
<?php

use Magma\Application;
use Magma\middleware\CsrfMiddleware;
use Magma\middleware\UTMTrackerMiddleware;
use Magma\middleware\ViewShareMiddleware;
use Magma\middleware\SessionTimeoutMiddleware;
use Magma\middleware\SecurityHeadersMiddleware;

try {
    // 1. Leave the public directory immediately
    require_once __DIR__ . '/../magma/config/bootstrap.php';

    // 2. Define our environment
    define('ENVIRONMENT', \Magma\config\Config::get('APP_ENV', 'production'));

    // 3. Resolve the application and add security layers (first in, outermost layer)
    $app = $container->get(Application::class);
    $app->addMiddleware(\Magma\middleware\TenantSecurityMiddleware::class);
    $app->addMiddleware(CsrfMiddleware::class);
    $app->addMiddleware(UTMTrackerMiddleware::class);
    $app->addMiddleware(ViewShareMiddleware::class);
    $app->addMiddleware(SessionTimeoutMiddleware::class);
    $app->addMiddleware(SecurityHeadersMiddleware::class);

    // 4. Execute the request!
    $app->run();
} catch (\Throwable $e) {
    error_log("CRITICAL BOOT FAILURE: " . $e->getMessage());
    http_response_code(500);
    echo "A critical system error occurred during initialization.";
    exit(1);
}
```

#### Analyzing the Principles: The Public Boundary
The most crucial line inside the boot block is:
`require_once __DIR__ . '/../magma/config/bootstrap.php';`

Notice the directory structure. The web server (like Nginx or Apache) is configured to serve files *only* from the `www/` directory. However, all of our actual code, business logic, and configuration files live in `magma/`, `app/`, and `modules/`, which sit completely outside the document root.

By executing that `require_once` statement, we immediately "jump" out of the public folder and into our secure application container.

This is the ultimate Separation of Concerns regarding security. Even if the web server suffers a catastrophic misconfiguration and accidentally serves `.php` files as raw text instead of executing them, an attacker could only see the contents of `www/index.php`. They cannot reach our database credentials or `.env` configuration because those files simply do not exist within the server's public path.

#### The "Why" & Framework Comparison
Both Laravel (`public/index.php`) and Symfony (`public/index.php`) use this exact pattern, and for good reason. It provides a centralized location to enforce rules.

Because we add `TenantSecurityMiddleware`, `CsrfMiddleware`, `SessionTimeoutMiddleware`, and `SecurityHeadersMiddleware` directly into `$app` right here at the gateway, we absolutely guarantee that no controller deep inside the application can ever be reached without passing those security checks first. If we used the old multiple-file pattern, we could never make that guarantee.

#### Common Questions and Answers

> **Q: What is the security risk of having the core application folder (e.g., `magma/` or the root `.env` file) inside the public `www/` directory?**
> 
> **A:** If those files are in the public domain, the database credentials and secret keys become easily accessible. If a web server misconfiguration occurs, an attacker could navigate directly to `website.com/.env` and download your passwords as plain text. Keeping them strictly outside the document root eliminates this vector entirely.

> **Q: Looking at `www/index.php`, why does it make sense to attach "global" middleware (like Tenant Security, CSRF, Session Timeout, and Security Headers) here at the entrance, rather than waiting until the Router decides which Controller to execute?**
> 
> **A:** Think of it like a bag check at the entrance of the festival gates, rather than having one at every stall. By performing the security check once at the absolute perimeter, we guarantee that no malicious payload even reaches the inner workings of our application. We enforce security by default, rather than relying on individual controllers to remember to check it.

---

### Chapter 2.2: Bootstrapping the Application

#### Subject & Intent: Waking Up the Application
If `www/index.php` is the front door, `magma/config/bootstrap.php` is the process of turning on the lights, booting up the computers, and unlocking the cash register before the customers arrive.

When execution jumps out of the `www/` directory via that `require_once` statement, it lands in `magma/config/bootstrap.php`. This file is responsible for preparing the environment so that our business logic has everything it needs to execute.

#### File Walkthrough: `magma/config/bootstrap.php`

Let's look at the critical sections of this file:

```php
<?php

declare(strict_types=1);

// 1. The Autoloader
require_once __DIR__ . '/autoload.php';

use Magma\container\Container;
use Magma\config\Config;
use Magma\providers\CoreServiceProvider;
use Magma\providers\DatabaseServiceProvider;
use Magma\providers\InfrastructureServiceProvider;
use Magma\providers\RoutingServiceProvider;
use Magma\providers\RepositoryServiceProvider;
use Magma\providers\DomainServiceProvider;
use Magma\providers\HttpServiceProvider;

// 2. Load Environment Variables
Config::initialize();

// 3. Initialize the Service Container
$container = new Container();

$container->set(Container::class, function () use ($container) {
    return $container;
});

// 4. Register Service Providers
$providers = [
    new CoreServiceProvider(),
    new InfrastructureServiceProvider(),
    new DatabaseServiceProvider(),
    new RoutingServiceProvider(),
    new RepositoryServiceProvider(),
    new DomainServiceProvider(),
    new HttpServiceProvider(),
    new \Magma\providers\EventServiceProvider(),
    new \App\providers\AppServiceProvider(),
    // Module service providers are registered via App\providers\AppServiceProvider.
];

foreach ($providers as $provider) {
    $provider->register($container);
}
```

#### Analyzing the Principles

**1. The PSR-4 Autoloader**
Before PHP 5.3, if you wanted to use 10 different classes in a file, you had to write 10 `require 'path/to/class.php';` statements at the top of your file. It was a nightmare.
Today, we use an **Autoloader** (specifically adhering to the PSR-4 standard, implemented in `magma/config/autoload.php`). We require the autoloader *once* here in the bootstrap file. From then on, whenever PHP sees `new Magma\container\Container()`, the autoloader intercepts it, maps the `Magma\` namespace prefix to `magma/` (and `App\` to `app/`, `Modules\` to `modules/`), and includes `magma/container/Container.php` automatically behind the scenes.

**2. Centralized Configuration**
`Config::initialize()` parses our hidden `.env` file (which contains our database passwords and API keys). Crucially, it loads them into a centralized `\Magma\config\Config` class (and wraps them behind `ConfigInterface` via `ConfigWrapper`). We do this early so the rest of the application can simply ask `Config` for settings, rather than interacting with the raw filesystem or server globals.

**3. The Service Providers**
To keep our bootstrap file from becoming 5,000 lines long, we use the **Service Provider Pattern**. We group our setup logic into logical chunks (`CoreServiceProvider`, `InfrastructureServiceProvider`, `DatabaseServiceProvider`, `RoutingServiceProvider`, `RepositoryServiceProvider`, `DomainServiceProvider`, `HttpServiceProvider`, `EventServiceProvider`, and `AppServiceProvider`). The bootstrap file simply loops over them and tells them to register themselves with the Dependency Injection Container.

#### The "Why" & Framework Comparison
Why keep `www/index.php` and `magma/config/bootstrap.php` separate? Why not just put all the bootstrap logic directly in `www/index.php`?

**Testing and CLI access.**
If we put all the boot logic inside `www/index.php`, we couldn't run automated tests or command-line (CLI) scripts without faking an HTTP web request. By isolating the boot sequence into `magma/config/bootstrap.php`, our web gateway (`www/index.php`), our CLI worker daemon (`bin/worker.php`), and our PHPUnit test suite on the `lava` branch can all simply `require 'magma/config/bootstrap.php'` to wake up the application securely and consistently!

#### Common Questions and Answers

> **Q: Beyond saving us from typing `require` a thousand times, how does an Autoloader actually save memory and improve performance on a per-request basis?**
> 
> **A:** The autoloader only loads what is necessary (lazy loading). If an HTTP request only triggers 5 classes out of a 500-class application, the autoloader only reads and parses those 5 files into memory. If we manually `require`d everything upfront, we would waste massive amounts of RAM and CPU parsing unused code.

> **Q: Why is it safer and more robust to load `.env` variables into a dedicated `Config` object during bootstrap, rather than letting developers write `$_ENV['DB_PASSWORD']` randomly inside their classes?**
> 
> **A:** It prevents every class from accessing the file structure, which is a major security risk. Having a single point of information (the `Config` object) allows us to control the data better. For instance, we can easily cache the configuration, validate it, or change where it comes from (e.g., switching from a `.env` file to AWS Secrets Manager) without having to rewrite any of the business logic that consumes the data.

---

### Chapter 2.3: Containerization & Execution

#### Subject & Intent: Passing the Baton
We have left the `www/` directory and we have bootstrapped our environment. We now have an autoloader and a populated `Config` object.

The final responsibility of the Front Controller phase is to **build the Dependency Injection Container** and **execute the Application**. This is the exact moment where the static "setup" phase ends, and the dynamic "runtime" phase begins.

#### File Walkthrough: The Handoff

If we look back at the final lines of our `magma/config/bootstrap.php` file, we see this:

```php
// magma/config/bootstrap.php

// The Container is instantiated
$container = new Container();

// Providers are given the container so they can register their services
foreach ($providers as $provider) {
    $provider->register($container);
}
```

At this exact millisecond, the `$container` object holds a complete "map" of every interface in our application and the concrete class or factory closure it should use (for example, `TransactionManagerInterface::class` -> `DatabaseTransactionManager`, or `CacheInterface::class` -> `RedisCache`). We will dive deeply into *how* the container works in Module 3, but for now, just understand that it is our master factory.

Now, execution returns to `www/index.php` for the finale:

```php
// www/index.php

// 1. We ask the container to build the Application Kernel for us
$app = $container->get(Application::class);

// 2. We attach our security middleware (outermost to innermost)
$app->addMiddleware(\Magma\middleware\TenantSecurityMiddleware::class);
$app->addMiddleware(CsrfMiddleware::class);
$app->addMiddleware(UTMTrackerMiddleware::class);
$app->addMiddleware(ViewShareMiddleware::class);
$app->addMiddleware(SessionTimeoutMiddleware::class);
$app->addMiddleware(SecurityHeadersMiddleware::class);

// 3. We pull the trigger.
$app->run();
```

#### Analyzing the Principles
Notice that we did **not** write `$app = new Application();`. 

We asked the container for it: `$container->get(Application::class);`. 

Why? Because the `Application` kernel itself has dependencies! It might need the Router, the ErrorHandler, and the Request object to function. By asking the Container to build the Application, the Container uses PHP Reflection to look at the `Application`'s constructor, automatically builds the Router, builds the ErrorHandler, and injects them all perfectly.

When we finally call `$app->run()`, the kernel takes over. It captures the incoming HTTP request (headers, POST data, cookies), pushes it through the middleware "bag checks," and hands it to the Router to find the correct Controller.

#### The "Why" & Framework Comparison
In some older or highly "magical" frameworks, simply including the bootstrap file implicitly executes the application. 

In the Magma Framework architecture, the `Application` object is passive until we explicitly call `run()`. 
Why is this explicit execution better? Because it gives us control. For instance, in our testing environment, we might bootstrap the application, but instead of calling `run()` to process a web request, we manually trigger a specific service to test its output. We control the execution flow, not the framework.

#### Common Questions and Answers

> **Q: What is the massive downside of hardcoding `$app = new Application(new Router(), new ErrorHandler());` directly inside `index.php` instead of using the Container? Is it memory bloat?**
> 
> **A:** While making too many copies (memory bloat) is a problem, the fundamental danger of the `new` keyword is **Tight Coupling** (Hardcoding dependencies). If you write `new Application(new Router())` directly in your `index.php`, your `index.php` file is permanently glued to that exact `Router` class. If tomorrow you decide to use a `FasterRouter` class, you have to open `index.php` and rewrite the core code. By using the Container, we simply update our configuration map, and `index.php` never has to change. The Container gives us the flexibility to swap components dynamically!

> **Q: If `$app->run()` is the trigger that actually processes the HTTP request, how does keeping the "bootstrap" phase separate from the "run" phase help us if we want to write a background cron job?**
> 
> **A:** It allows us to perform other tasks entirely! A cron job (like a script that emails users at midnight) doesn't involve an HTTP request. Because bootstrap and execution are separate, our cron script can simply `require 'bootstrap.php'` to get access to the database and services, and then execute its own specific code, entirely bypassing `$app->run()`.

---


### Chapter 2.4: Dual-Mode Kernels

#### Subject & Intent: HTTP vs. CLI Execution
Modern enterprise applications don't just respond to web browsers. They process asynchronous background jobs, run scheduled cron tasks, and execute database migrations.

If we tied all our bootstrapping logic (like session starting or header parsing) directly into the core, our background workers would crash, because a background CLI script does not have an HTTP Session or an IP Address!

To solve this, Magma implements **Dual-Mode Kernels**:
1.  **The Application Kernel (`magma/Application.php`):** Resolves the HTTP Request, dispatches it through the Middleware Onion, and returns an HTTP Response. It is strictly for web traffic.
2.  **CLI Entrypoints (`bin/worker.php`, `bin/outbox_publisher.php`, `bin/cache_routes.php`):** Boot the exact same `magma/config/bootstrap.php` Dependency Container, but completely bypass the HTTP Router and HTTP Middleware. They execute long-running daemons or build-time tasks directly against domain services and repositories.

> [!TIP]
> **The Magma Analogy:** Think of the underlying Dependency Container and Services as the engine of a car. The HTTP Kernel is the steering wheel, while the CLI Kernel is an autonomous driving script. Both interact with the exact same engine (the core domain), but their input mechanisms (drivers) are completely isolated.
## Module 3: The Dependency Injection Container (The Core)

### Chapter 3.1: Understanding Dependency Injection

#### Subject & Intent: The Restaurant Analogy

Before we look at the Container itself, we must deeply understand the pattern it facilitates: **Dependency Injection**.

Imagine you are opening a restaurant. You hire a Head Chef. 

**The Bad Way (Creating Dependencies Internally):**
If you tell the Head Chef, *"It is your job to build your own oven from scratch before you can cook,"* you have a problem. The Chef is now responsible for cooking *and* oven manufacturing. If the oven breaks, the Chef doesn't know how to fix it. This is the equivalent of using the `new` keyword inside a class:

```php
class Chef 
{
    private Oven $oven;

    public function __construct() 
    {
        // Bad: The Chef is creating his own dependency!
        $this->oven = new GasOven(); 
    }
}
```

**The Good Way (Injecting Dependencies):**
Instead, you buy a commercial oven, install it in the kitchen, and tell the Chef, *"Here is an oven. Use it."* The Chef doesn't care who built the oven or how it works, only that it has a `execute()` button. This is Dependency Injection:

```php
class Chef 
{
    private ServiceInterface $oven;

    // Good: The Chef demands an oven is provided to him!
    public function __construct(ServiceInterface $oven) 
    {
        $this->oven = $oven;
    }
}
```

#### Analyzing the Principles

Dependency Injection forces our classes to be honest about what they need to function. It perfectly aligns with the **Single Responsibility Principle (SRP)**: The Chef's only responsibility is cooking. It is *not* his responsibility to construct ovens or establish database connections.

It also aligns perfectly with **Testability**. If we want to test the Chef class to make sure his recipe works, we don't need to hook him up to a real, expensive gas oven. Because he accepts any `ServiceInterface`, we can pass him a fake `EasyBakeOven` just for the test. 

#### The Problem the Container Solves

Dependency Injection is fantastic, but it creates a massive logistical headache. 

If every class requires its dependencies to be passed into its constructor, who actually builds them? If our `OrderController` requires an `OrderService`, and the `OrderService` requires a `DatabaseRepository`, and the `DatabaseRepository` requires a `MySQLConnection`... we would have to manually build this massive "tree" of objects by hand every single time a request comes in!

That is exactly the problem the **DI Container** solves. It is an automated factory that builds this tree for us. 

#### Common Questions and Answers

> **Q: Why is it architecturally safer for a class to ask for its dependencies via its constructor (Injection) rather than instantiating them itself internally?**
> 
> **A:** It ensures the class is kept in its corner doing only what it is responsible for (Single Responsibility). If a class instantiates its own dependencies, it takes on the responsibility of *knowing how* to build them, creating tight coupling. By injecting them, we control the dependencies from the outside, only giving the class exactly what it is allowed to have.

> **Q: If the DI Container didn't exist, what would be the main negative impact on our code when trying to instantiate a high-level class like a `Controller` that has deeply nested dependencies?**
> 
> **A:** We would have to write a massive amount of copied, nested `new` statements (e.g., `new Controller(new Service(new Repository(new Database())))`). This "manual wiring" is tedious, repetitive, and we would quickly get lost in the boilerplate code every time we needed to create an object.

---

### Chapter 3.2: Unmasking the Container: PHP Reflection

#### Subject & Intent: The Mirror of Code
In Chapter 3.1, we established that our goal is to build an automated factory (the Container) that can build complex objects for us, so we don't have to write thousands of nested `new` statements.

But how can a generic `Container` class possibly know how to build *your* specific `OrderController`? It didn't write the code. How does it know that the `OrderController` requires an `OrderService`?

The answer is an incredibly powerful, native PHP feature called **Reflection**.

Reflection is an API that allows PHP code to "look in the mirror" and analyze itself at runtime. With Reflection, you can write code that asks questions like: *"Hey PHP, what methods does this class have? What parameters does its constructor require? What types are those parameters?"*

#### The Theory: How Auto-Wiring Works
In the Magma Framework, we use an advanced container technique called **Auto-Wiring** (`magma/container/Container.php`). We don't manually tell the container how to build every single class. Instead, the container uses Reflection to figure it out on the fly.

Here is a simplified example of what `Magma\container\Container` is doing inside its `get()` / `resolve()` methods when you ask it for a real framework class such as `Magma\controllers\LoginController`:

```php
// You ask the container for LoginController
$container->get(\Magma\controllers\LoginController::class);
```

Behind the scenes, the Container uses PHP's `ReflectionClass`:

```php
// 1. The Container reflects upon the requested class
$reflection = new ReflectionClass(\Magma\controllers\LoginController::class);

// 2. It looks at the constructor
$constructor = $reflection->getConstructor();

// 3. It asks: "What parameters do you require?"
// LoginController demands: AuthenticationService, HtmlResponseBuilderInterface, PostLoginRedirectResolver
$parameters = $constructor->getParameters();

$dependencies = [];

// 4. It loops through the required parameters
foreach ($parameters as $parameter) {
    // It inspects the type hint (e.g., AuthenticationService::class)
    $dependencyType = $parameter->getType()->getName();

    // 5. RECURSION! The container calls ITSELF to build each dependency!
    $dependencies[] = $this->get($dependencyType);
}

// 6. Finally, it builds LoginController, passing in the newly built dependencies.
return $reflection->newInstanceArgs($dependencies);
```

#### Analyzing the Principles
This is the moment the "Magic" becomes **Science**.

When a framework automatically injects dependencies into your controllers, it isn't using actual magic. It's just using `ReflectionClass` to read your constructor's type hints, building those dependencies recursively, and then handing you the fully assembled object.

Because we demand **Strict Typing** (Pillar 4), our constructors look like `Magma\controllers\LoginController::__construct`:
`public function __construct(private readonly AuthenticationService $authService, private readonly HtmlResponseBuilderInterface $html, private readonly PostLoginRedirectResolver $redirectResolver)`

Because we strictly typed every parameter, the Reflection API can read those type hints and know exactly what to build (or look up via interface bindings). If we didn't use strict typing, Reflection would have no idea what to inject, and auto-wiring would fail entirely!

#### The "Why" & Framework Comparison
In older or simpler frameworks, you don't use auto-wiring. Instead, you have to write a massive configuration array manually defining how to build *every single class* in your application.

```php
// The Old "Manual Configuration" Way (Tedious)
$container['OrderController'] = function($c) {
    return new OrderController($c['OrderService']);
};
```

By utilizing PHP Reflection, our Container becomes "smart." We don't have to configure 99% of our classes. The container just reads the constructors and figures it out. It gives us the convenience of modern frameworks without hiding the mechanism behind a black box.

#### Common Questions and Answers

> **Q: Why is our architectural rule of Strict Typing absolutely mandatory if we want to use an Auto-Wiring Container?**
> 
> **A:** It makes sure the reflection class understands exactly what it is looking for and doesn't get lost. If a constructor parameter is just `$service` without a type hint, Reflection only sees a `mixed` type. It has no idea what class to instantiate! The type hint is the physical map the Container follows.

> **Q: Reflection forces PHP to analyze constructors while the application is running. What potential downside does this introduce every time a user makes a web request?**
> 
> **A:** It takes a little longer to load the initial tree (a performance penalty). However, enterprise applications solve this by "compiling" or caching the Reflection tree during the deployment phase. This means in production, the app reads from a highly efficient cached map rather than running Reflection on every single request, giving us the best of both worlds.

---

### Chapter 3.3: Interface Binding (The Service Providers)

#### Subject & Intent: The Limits of Reflection
In Chapter 3.2, we saw that Auto-Wiring is incredibly smart. If `LoginController`'s constructor asks for `AuthenticationService`, the Container uses Reflection to inspect its dependencies and build it.

But what happens when we follow the Dependency Inversion Principle (Pillar 1) properly?
What if a constructor looks like `Magma\middleware\SessionTimeoutMiddleware::__construct` or `Magma\services\RegistrationService::__construct`?

```php
public function __construct(
    UserCommandInterface $userCommandRepository,
    EventDispatcherInterface $dispatcher,
    TransactionManagerInterface $transactionManager
)
```

**Reflection hits a wall.** It looks at `TransactionManagerInterface` and realizes it cannot instantiate an Interface. An interface is just a contract; it has no actual code. If Reflection tries to instantiate an interface directly, PHP throws an error (`Target class [...] is not instantiable`).

The Container needs to know *which specific concrete class* to use when someone asks for that interface.

#### File Walkthrough: The Service Providers
To solve this, we give the Container a manual "map." We say: *"Hey Container, whenever someone asks for `TransactionManagerInterface`, give them `DatabaseTransactionManager`; whenever someone asks for `RolePolicyInterface`, give them `ConfigRolePolicy`; whenever someone asks for `ConfigInterface`, give them `ConfigWrapper`."*

We call this **Binding**. In Magma Framework, we organize our bindings into **Service Providers** implementing `Magma\container\ServiceProviderInterface`.

Let's look at real bindings from `magma/providers/DatabaseServiceProvider.php` and `magma/providers/CoreServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Magma\providers;

use Magma\container\Container;
use Magma\container\ServiceProviderInterface;
use Magma\database\DatabaseTransactionManager;
use Magma\database\TransactionManagerInterface;

// From magma/providers/DatabaseServiceProvider.php
class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Alias an interface directly to a concrete class
        $container->bind(TransactionManagerInterface::class, DatabaseTransactionManager::class);
    }
}
```

And in `magma/providers/CoreServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Magma\providers;

// Excerpt from CoreServiceProvider::register(Container $container)
$container->set(\Magma\config\ConfigInterface::class, function ($c) {
    return new \Magma\config\ConfigWrapper();
});

$container->bind(
    \Magma\contracts\RolePolicyInterface::class,
    \Magma\security\ConfigRolePolicy::class
);
```

If you recall Chapter 2.2 (Bootstrapping), `magma/config/bootstrap.php` loops through these Service Providers and executes their `register($container)` methods right before the application runs.

#### Analyzing the Principles: The Power of Swappability
By separating the *request* for an object (in the constructor) from the *configuration* of that object (in the Service Provider), we achieve ultimate architectural flexibility.

This is the very essence of the **Open/Closed Principle (OCP)**.

Imagine a service depends on `CacheInterface` (`magma/interfaces/CacheInterface.php`).
1. In production, `InfrastructureServiceProvider` binds `CacheInterface::class` to `RedisCache`.
2. In isolated unit tests without Redis, we swap one binding line to `ArrayCache` (`magma/cache/ArrayCache.php`):
   `$container->bind(CacheInterface::class, ArrayCache::class);`

**That's it.** We don't have to touch any domain service or controller that uses `CacheInterface`. The Container simply starts handing out the `ArrayCache` instance to anyone who asks for the Interface.

#### The "Why" & Framework Comparison
Some frameworks allow you to bind interfaces using configuration arrays (like YAML or XML files). We explicitly choose to use PHP classes (Service Providers) for our bindings. 

Why? Because PHP code can be statically analyzed by your IDE. If you have a typo in an XML configuration file, your application will crash at runtime. If you have a typo in a PHP Service Provider, your editor will immediately underline it in red before you even run the code. We prefer explicit, type-safe PHP over abstract configuration files.

#### Common Questions and Answers

> **Q: If we bind `PaymentGatewayInterface` to `StripeGateway` in our Service Provider, and next year the business wants to switch to `PayPalGateway`, exactly what files in our core business logic (`OrderService`, `CheckoutController`) do we need to modify?**
> 
> **A:** Absolutely none of them! We only change the binding in the Service Provider to point to the new `PayPalGateway`. Because the core business logic only depends on the generic `PaymentGatewayInterface`, it remains entirely untouched. This is the Open/Closed Principle in action.

> **Q: If Reflection is so smart, why can't it just automatically figure out which class to use when a constructor asks for an Interface?**
> 
> **A:** Because of intent and control. If we have 5 different classes that all implement `PaymentGatewayInterface` (Stripe, PayPal, ApplePay, FakeTestingGateway, etc.), it is physically impossible for PHP to magically guess which one the developer *intends* to use for this specific environment. By strictly mapping out the connections, we explicitly control exactly what logic is used and where.

---


### Chapter 3.4: Defending Against Memory Leaks & O(1) Management

As the application grows, resolving hundreds of dependencies per request can become slow and consume massive amounts of RAM.

Magma's Container manages memory efficiently using a **Singleton Cache** (`$this->instances`) for explicit factory definitions registered via `$container->set(...)` (while leaving auto-wired classes transient by default so request-scoped objects/DTOs do not leak across daemon iterations). When `DatabaseConnectionManager` is resolved for the first time via `DatabaseServiceProvider`, the Container caches the instance. If five different Repositories ask for `DatabaseConnectionManager`, they all receive the exact same instance reference in memory (O(1) memory overhead), and constructor reflection metadata is memoized in `self::$reflectionCache`.

By preventing the redundant instantiation of heavy services, Magma keeps its memory footprint entirely flat, allowing it to serve thousands of requests concurrently on minimal hardware.

### Chapter 3.5: Breaking Circular Dependency Deadlocks

A classic architectural bug occurs when `ServiceA` requires `ServiceB`, but `ServiceB` requires `ServiceA`. If left unchecked, the Reflection engine will enter an infinite loop, crashing the server with a Stack Overflow or an Out Of Memory (OOM) error.

Magma's `Container` (`magma/container/Container.php`) physically protects against this by tracking resolution paths in `$this->resolving`. If it detects a circular dependency (e.g., trying to resolve `ServiceA` while `ServiceA` is already in the `$this->resolving` stack), it immediately aborts and throws a `\RuntimeException("Circular dependency detected while resolving [{$id}].")`.
## Module 4: Routing & The HTTP Request

### Chapter 4.1: The Router - Mapping URLs to Controllers

#### Subject & Intent: The Traffic Cop
When a user types `sandbox.local/products` into their browser, an HTTP `GET` request is sent to our server. As we know from Module 2, this request hits our Front Controller (`www/index.php`) and triggers `$app->run()`.

But how does the application know that `/products` should execute the code that fetches modules from the database, while `/checkout` should execute the payment code?

The answer is the **Router**. 

The Router is the "Traffic Cop" of the application. Its sole responsibility is to look at the incoming URL (the URI) and the HTTP Method (GET, POST, PUT, DELETE), and match it against a predefined list of "Routes" to determine which **Controller** should handle the request.

#### The Theory: Defining Routes
Before the Router can route traffic, we have to give it a map. In the Magma Framework, we explicitly define this map using fluent `RouteDefinition` Value Object builders inside dedicated routes files (such as `magma/config/routes.php`, `app/routes.php`, and `modules/Reviews/routes.php`).

Here is a real excerpt from `magma/config/routes.php`:

```php
<?php

declare(strict_types=1);

use Magma\controllers\LoginController;
use Magma\middleware\RateLimitMiddleware;
use Magma\routing\RouteDefinition;

return [
    // GET '/login' -> LoginController::login()
    RouteDefinition::get('/login', [LoginController::class, 'login']),

    // POST '/login' -> LoginController::authenticate() with parameterized rate-limit middleware
    RouteDefinition::post('/login', [LoginController::class, 'authenticate'])
        ->middleware([RateLimitMiddleware::class, 5, 60]),
];
```

#### File Walkthrough: The Execution Flow
Let's look at what happens inside `Magma\Application::run()` (`magma/Application.php`) and `Magma\routing\Router::dispatch()` (`magma/routing/Router.php`) when that request arrives:

1. **Capture the Request:** `Application::run()` resolves `RequestInterface` from the DI Container (bound in `CoreServiceProvider` to `Request::createFromGlobals()`).
2. **Hand it to the Router:** `Application::handle()` passes the `RequestInterface` and global middleware list to `RouterInterface::dispatch($request, $this->middleware)`.
3. **Find the Match:** `Router::dispatch()` checks `matchStaticRoute()` first (an O(1) hash lookup), then falls back to `matchDynamicRoute()` using the compiled PCRE mega-regex. If no route matches, it checks for `405 Method Not Allowed` or throws `RouteNotFoundException`.
4. **Resolve the Controller & Middleware:** `RouteDispatcher::dispatch()` builds the middleware onion and asks the **DI Container** (from Module 3) to instantiate `LoginController`.
5. **Execute the Method:** `RouteParameterResolver` auto-wires any method-injected parameters (including `FormRequest` validation) and invokes the controller method, returning a `Response`.

#### Analyzing the Principles: Separation of Concerns
You might wonder: *Why do we have a dedicated Router class? Why can't we just put a giant `if/else` statement inside `www/index.php`?*

```php
// The Bad Way (Violating SRP)
if ($_SERVER['REQUEST_URI'] === '/login') {
    $controller = new LoginController(...);
    $controller->login($request);
} elseif ($_SERVER['REQUEST_URI'] === '/reviews') {
    // ...
}
```

This violates the **Single Responsibility Principle (SRP)**. If we put routing logic inside `www/index.php` or `Application`, those files would grow to thousands of lines long as the application scales. By extracting routing into `Magma\routing\Router`, `RouteCollection`, `RouteCompiler`, and `RouteDispatcher`, we keep our code modular, testable, and extremely clean.

Furthermore, `RouteDefinition` can handle dynamic parameters with regex constraints:
`RouteDefinition::get('/users/{id}', [UserController::class, 'show'])->where('id', '\d+');`
Here, the Router extracts `{id}` from the URL (like `/users/5`), validates it against `\d+`, and passes it directly into the controller's method parameters!

#### Common Questions and Answers

> **Q: Imagine a junior developer adds a new route, but instead of just returning the mapped controller, they write code inside the Router class to query the database to check if the user is an admin. Why is this a severe architectural violation?**
> 
> **A:** It forces the router class to handle more than one task, violently breaking the Single Responsibility Principle. The Router is suddenly responsible for mapping URLs *and* executing authorization logic. This means the Router is no longer highly testable or easily extendable, and that admin-checking logic will likely have to be copied and pasted elsewhere because it is trapped in the wrong layer of the application.

> **Q: We map URLs using specific HTTP methods (`GET` vs `POST`). What would go horribly wrong if the framework didn't care about the HTTP method, and allowed a user to trigger the `CheckoutController::process()` method (which charges a card) via a simple GET request in the browser?**
> 
> **A:** The site's code could be hacked to perform actions not requested! `GET` requests are intended to be "safe" (read-only). If a destructive action like charging a card was allowed via `GET`, an attacker could simply trick a user into clicking a link (`sandbox.local/checkout`) or embed an invisible image tag that loads the URL, instantly charging the user's card without their consent. Enforcing `POST` for destructive actions is a fundamental security requirement.

---

### Chapter 4.2: Middleware - Border Security (The Onion)

#### Subject & Intent: Filtering the Traffic
In Chapter 2.1, we talked about "bag checks" at the festival entrance. In modern web architecture, these bag checks are called **Middleware**.

If the Router is the Traffic Cop pointing cars to their destinations, Middleware are the toll booths and security checkpoints along the highway. 

We can visualize Middleware as an **Onion**. 
* The **Core** of the onion is our Controller (where the actual business logic happens).
* The **Layers** of the onion are our Middleware.
* An incoming HTTP Request must pass *inward* through every layer of the onion to reach the Core.
* The resulting HTTP Response must pass *outward* through every layer before returning to the user's browser.

#### The Theory: The Contract of Middleware
Every single Middleware class in the Magma framework implements `Magma\middleware\MiddlewareInterface` (`magma/middleware/MiddlewareInterface.php`):

```php
<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;

interface MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): Response;
}
```

It receives the incoming `RequestInterface` (directly enforcing the Dependency Inversion Principle rather than relying on parameter contravariance from a concrete `Request`) and a closure `$next`. The Middleware has absolute power to decide what to do:
1. **Pass:** It can inspect the Request, decide everything is fine, and call `$next($request)` to pass the request deeper into the onion.
2. **Reject:** It can inspect the Request, find a problem, and instantly return a `RedirectResponse` or a `401` JSON `Response`, **completely blocking the request from ever reaching the controller**.

#### File Walkthrough: `AuthMiddleware` & `SessionTimeoutMiddleware`
Let's look at a concrete example from `magma/middleware/AuthMiddleware.php`. Instead of repeating session authentication checks at the top of every protected Controller, we enforce them once in a single Middleware:

```php
<?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\http\SessionInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public const INTENDED_URL_SESSION_KEY = 'intended_url';

    public function __construct(
        private SessionInterface $session,
        private \Magma\interfaces\cqrs\UserQueryInterface $userRepository
    ) {}

    public function process(RequestInterface $request, callable $next): Response
    {
        $user = $this->session->get('user');

        if (!is_array($user)) {
            // REJECT! The Controller is never reached.
            return $this->handleUnauthorized($request);
        }

        // Verify password timestamp against DB to invalidate stale sessions
        $loginTime = $this->session->get('login_time');
        $userId = $user['id'] ?? null;

        if (is_numeric($userId) && is_numeric($loginTime)) {
            $passwordChangedAt = $this->userRepository->getPasswordChangedAt((int) $userId);
            if ($passwordChangedAt && $passwordChangedAt > $loginTime) {
                $this->session->destroy();
                return $this->handleUnauthorized($request);
            }
        }

        // PASS! Hand the request to the next layer of the onion
        return $next($request);
    }

    private function handleUnauthorized(RequestInterface $request): Response
    {
        if ($request->isJsonExpected() || $request->expectsJson()) {
            $payload = json_encode([
                'success' => false,
                'error'   => 'Unauthenticated access.',
                'code'    => 401,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            return new Response($payload, 401, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return new RedirectResponse('/login');
    }
}
```

Similarly, `Magma\middleware\SessionTimeoutMiddleware` (`magma/middleware/SessionTimeoutMiddleware.php`) implements `MiddlewareInterface::process(RequestInterface $request, callable $next): Response` using injected `SessionInterface`, `ClockInterface`, and `RolePolicyInterface`, returning `new RedirectResponse('/login')` (with a cleared `remember_user` cookie) when inactivity exceeds the role's configured timeout. Because `SessionTimeoutMiddleware` is registered globally in `www/index.php` (`$app->addMiddleware(SessionTimeoutMiddleware::class);`), individual controllers don't have to know that session timeouts even exist.

#### Analyzing the Principles: The Power of the Pipeline
This pattern is formally known as the **Pipeline Pattern**. 

It gives us immense flexibility. We can create highly specific Middleware for specific routes. For example, the `AdminMiddleware` might only be attached to routes that start with `/admin`.

It perfectly satisfies the **Open/Closed Principle (OCP)**. If the business asks us to implement a new feature—for example, "Block all IP addresses coming from outside the UK"—we do not touch a single line of existing code. We simply write a `GeoIPMiddleware` class, add it to our pipeline in `index.php`, and the entire application instantly inherits the new security rule.

#### Common Questions and Answers

> **Q: If a Middleware class detects a session timeout, it returns a Redirect response immediately without calling `$next`. Why is it highly efficient that the Middleware can abort the journey without ever letting the Request reach the Controller?**
> 
> **A:** The Controller does not need to know that everything is okay; it only needs to do its job. By letting the middleware block invalid requests, it saves the server from doing unnecessary work (like database queries or complex logic inside the controller) because the request is aborted early!

> **Q: We described Middleware as an onion. What might be a real-world example of a Middleware that does its job by modifying the *Response* on the way out, rather than inspecting the *Request* on the way in?**
> 
> **A:** Modifying the response format is a great example, such as appending seasonal information. Other common real-world examples include adding CORS (Cross-Origin Resource Sharing) headers to the response, compressing the HTML (like gzip) to save bandwidth before sending it to the browser, or stripping out whitespace to minify the output.

---

### Chapter 4.3: The Request & Response Objects

#### Subject & Intent: Encapsulating the Web
In raw, native PHP, when you want to see what the user typed into a form or check what their IP address is, you look at superglobal arrays like `$_POST`, `$_GET`, `$_COOKIE`, and `$_SERVER`. 

When you want to send data back to the user, you use functions like `echo "Hello";` or `header('Location: /login');`.

In the Magma framework, **we absolutely forbid the use of these raw global variables and functions.** 

Instead, we encapsulate everything into two strict objects: `Magma\http\Request` (`magma/http/Request.php`) and `Magma\http\Response` / `Magma\http\RedirectResponse` (`magma/http/Response.php`, `magma/http/RedirectResponse.php`).

#### The Theory: Why Encapsulation Matters
A fundamental rule of Object-Oriented Programming is that you should not rely on global state. 

If your controller looks directly at `$_POST['email']`, it is relying on a global variable. This creates a massive problem for **Testability**. If you want to write a unit test for your controller, you have to artificially inject fake data into the `$_POST` array before running the test, which is clunky and can accidentally bleed into other tests.

Furthermore, `$_POST` is untyped and ignores JSON request bodies.

#### File Walkthrough: The `Request` Object
At the very beginning of the Front Controller lifecycle, `CoreServiceProvider` binds `RequestInterface::class` to `Request::createFromGlobals()` (`magma/http/Request.php`), which gathers `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_SERVER`, and `php://input` via `Request::build(...)`.

This `Request` object is what gets passed through the Middleware and eventually into the Controller.

```php
<?php

declare(strict_types=1);

namespace Magma\http;

// Excerpt from magma/http/Request.php
class Request implements RequestInterface
{
    public static function createFromGlobals(): self
    {
        if (self::$cachedRawBody === null) {
            $input = file_get_contents('php://input');
            self::$cachedRawBody = $input !== false ? $input : null;
        }
        return self::build($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, self::$cachedRawBody);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->get : ($this->get[$key] ?? $default);
    }

    public function request(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->requestData : ($this->requestData[$key] ?? $default);
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->cookies : ($this->cookies[$key] ?? $default);
    }
}
```

Look at how clean this boundary is! By accessing `$request->query('sso')`, `$request->request('email')`, or `$request->cookie('remember_user')`, controllers and middleware never touch PHP superglobals directly—and `$request->request()` seamlessly unifies `application/json` payloads and standard form POST data! (Typed casting and validation are then enforced by `FormRequest` and `readonly` DTOs, covered in Module 9.)

#### File Walkthrough: The `Response` & `RedirectResponse` Objects
Similarly, a Controller should never use `echo` or call `header('Location: ...')` directly. Why? Because `echo` immediately flushes data to the browser. If a Middleware layer on the "way out" wanted to attach security headers or cookies, it's too late—headers are already sent!

Instead, a Controller always returns a `Response` (or `RedirectResponse`) object.

```php
<?php

declare(strict_types=1);

namespace Magma\http;

// Excerpt from magma/http/Response.php
class Response
{
    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        private array $headers = []
    ) {}

    // The ONLY place in the application where output actually happens
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", false);
        }

        // ... emits buffered cookies via setcookie(...) ...
        echo $this->content;
    }
}
```

And for redirects, `Magma\http\RedirectResponse` (`magma/http/RedirectResponse.php`) extends `Response`:

```php
// Excerpt from magma/http/RedirectResponse.php
class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('', $status, ['Location' => $url]);
    }
}
```

The `Application` kernel receives this `Response` (or `RedirectResponse`) object from the `Router`, passes it outward through the Middleware (allowing layers like `SecurityHeadersMiddleware` to add headers or `AppliesAuthenticationCookiesTrait` to attach cookies), and finally calls `$response->send()` at the very end of the lifecycle.

#### Analyzing the Principles
By utilizing Request and Response objects, we enforce **Immutability and Control**. The flow of data is no longer a chaotic free-for-all of global variables. It is a strictly typed, trackable package moving through an organized pipeline.

#### Common Questions and Answers

> **Q: How does requiring a Request object (rather than looking directly at `$_POST`) make writing automated unit tests for a Controller significantly easier and cleaner?**
> 
> **A:** If everything passes through our Request object checkpoint, we can easily test the controller without a real browser. We simply write `$request = Request::build(post: ['email' => 'user@example.com'], server: ['REQUEST_METHOD' => 'POST']);` and pass it to our Controller. We don't have to hack the global `$_POST` array, meaning our tests are perfectly isolated and predictable.

> **Q: If a controller uses `echo "Hello";`, the text is sent to the user instantly. If a controller instead returns `new Response("Hello");`, how does the "Middleware Onion" architecture benefit from this delay?**
> 
> **A:** It allows the framework to intercept the object on the way out! Because the output is delayed until the very end, we can perform any operation on the response before providing it to the user.

---


### Chapter 4.4: The Evolution to O(1) Regex Routing

Many frameworks evaluate routes sequentially. If you have 1,000 routes, the framework might run a regex check 1,000 times until it finds a match. This is an O(N) operation, meaning routing gets slower as your application grows.

Magma evolved to utilize a highly optimized **PCRE (Perl Compatible Regular Expressions) compiled route strategy**. 

Instead of checking routes one by one, Magma compiles all registered routes into a single, massive Regular Expression map (`routes.cache.php`). When a request comes in, the entire routing table is evaluated in a single, lightning-fast O(1) operation. 

> [!TIP]
> **Performance Note:** If you add a new route, you MUST run `php bin/cache_routes.php`. Because Magma reads from the compiled cache rather than the raw file, this ensures absolute maximum performance in production environments.

### Chapter 4.5: The Dynamic Route Discovery Engine

As applications scale, dumping hundreds of application routes alongside core framework routes inside `magma/config/routes.php` creates severe architectural pollution. The framework's core configuration files should be treated as pristine, untouched infrastructure, completely agnostic of the downstream application's features (e.g. `HomeController` or `ReviewController`).

To solve this, Magma implements the **Dynamic Route Discovery Engine**. Instead of hardcoding all application logic into a single central file, the engine dynamically discovers, loads, and merges routes from across the project:
1. **Core Framework Routes:** (`magma/config/routes.php`) - e.g., default authentication scaffolding or health checks.
2. **Application Routes:** (`app/routes.php`) - Primary downstream app controllers.
3. **Module Routes:** (`modules/*/routes.php`) - Discovering fully decoupled module routes via deterministic `scandir()` traversal.

**Why `scandir()` instead of `glob()`?**
In enterprise systems, functions like `glob()` can behave inconsistently across different OS environments or specific filesystem configurations. By utilizing standard `scandir()` loops, Magma ensures highly predictable, cross-platform deterministic directory traversals. 

Crucially, because this Route Discovery Engine executes _before_ the O(1) Route Compiler runs (`bin/cache_routes.php`), the dynamic discovery overhead never impacts HTTP runtime. In production, the application still executes against the lightning-fast `routes.cache.php` manifest!

## Module 5: Controllers & Services (The Business Logic)

### Chapter 5.1: The Controller - The Traffic Cop (Redux)

#### Subject & Intent: The Delegator
The word "Controller" in the MVC (Model-View-Controller) pattern is somewhat dangerous. It implies that the controller should *control* how the application works. 

This historically led to what developers call "Fat Controllers." A fat controller is a file with 1,000 lines of code that handles file uploads, writes to the database, calculates taxes, and sends emails all in one giant method.

In the Magma Framework architecture, we view the Controller strictly as a **Delegator**. It is a middle-manager. It does not do the hard work; it simply organizes the work.

#### The Theory: Magma's Dual Dependency Injection Strategy & The Three Rules of a Controller
Magma enforces a deliberate **Dual Dependency Injection Strategy** across its architectural layers:
* **Core Framework Controllers (`/magma/controllers/*`, e.g., `Magma\controllers\LoginController`):** Use **Constructor Injection** (`__construct(AuthenticationService $authService, HtmlResponseBuilderInterface $html, PostLoginRedirectResolver $redirectResolver)`) to enforce rigid instantiation contracts inside the framework kernel.
* **Application & Module Controllers (`/app/` and `/modules/`, e.g., `App\controllers\HomeController`, `Modules\Reviews\controllers\ReviewController`):** Use **Method Injection** so the Router's `RouteParameterResolver` resolves only the services needed for the matched route action, preventing constructor bloat as a controller grows.

To prevent "Fat Controllers," we enforce three absolute rules for any Controller class:
1. **Never write Business Logic:** A controller should not hash passwords, calculate taxes, or enforce domain invariants.
2. **Never query the Database:** A controller should never contain SQL or interact directly with a PDO connection.
3. **Always return a Response:** The controller's only purpose is to accept the validated `Request`/`FormRequest`, delegate to a Service, and return a `Response` (or `RedirectResponse`).

#### File Walkthrough: Real Magma Controllers
First, look at a downstream application controller in `app/controllers/HomeController.php`:

```php
<?php
declare(strict_types=1);

namespace App\controllers;

use App\services\SystemDiagnosticsServiceInterface;

class HomeController
{
    // Method Injection: RouteParameterResolver injects only what this action needs
    public function index(
        SystemDiagnosticsServiceInterface $diagnosticsService,
        \Magma\view\HtmlResponseBuilderInterface $html
    ): \Magma\http\Response {
        $diagnostics = $diagnosticsService->getDiagnostics();

        return $html->render('welcome', [
            'title'       => \App\constants\AppConstants::HOME_TITLE,
            'diagnostics' => $diagnostics,
        ]);
    }
}
```

And look at a modular feature controller in `modules/Reviews/controllers/ReviewController.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Reviews\controllers;

use Magma\http\Response;
use Magma\http\RedirectResponse;
use Modules\Reviews\interfaces\ReviewSubmissionServiceInterface;
use Modules\Reviews\requests\ReviewRequest;

class ReviewController
{
    // Method Injection with automatic FormRequest validation
    public function submitReview(
        ReviewRequest $reviewRequest,
        \Magma\security\TenantContext $tenantContext,
        ReviewSubmissionServiceInterface $reviewSubmissionService,
        \Magma\http\SessionInterface $session
    ): Response {
        $dto = $reviewRequest->toDTO();
        $tenantId = $tenantContext->getTenantId();
        $reviewSubmissionService->submit($dto, $tenantId);

        $session->set(
            \App\constants\AppConstants::SESSION_SUCCESS_MESSAGE,
            \Modules\Reviews\constants\ReviewConstants::MSG_REVIEW_SUBMITTED
        );
        return new RedirectResponse('/');
    }
}
```

#### Analyzing the Principles
Both controllers perfectly adhere to the **Single Responsibility Principle (SRP)**. Their *only* responsibility is translating HTTP traffic into typed domain calls and returning a `Response`. Notice how `HomeController::index()` delegates diagnostics to `SystemDiagnosticsServiceInterface` and HTML rendering to `HtmlResponseBuilderInterface`, while `ReviewController::submitReview()` delegates input validation to `ReviewRequest` and business logic to `ReviewSubmissionServiceInterface`.

#### Common Questions and Answers

> **Q: Imagine we launch a mobile app or JSON API next year alongside our web routes. If we had written all of our review validation and persistence rules directly inside `ReviewController`, why would building this new API be painful?**
> 
> **A:** Because we would have to duplicate every single business rule for the API! By keeping the Controller as just a "Traffic Cop" and pushing orchestration into `ReviewSubmissionService` (`modules/Reviews/services/ReviewSubmissionService.php`), a new API controller can inject the exact same `ReviewSubmissionServiceInterface` and reuse 100% of the business logic.

> **Q: In our architecture, a Controller delegates work to a Service. If `RegistrationService` discovers that an email is already registered, should `RegistrationService` return `new RedirectResponse('/register')`?**
> 
> **A:** No! This is a critical distinction in Separation of Concerns. A `Response` or `RedirectResponse` is an HTTP concept (it belongs to the web layer). `RegistrationService` is pure business logic; it throws `ValidationException` (caught at the HTTP boundary by `ErrorHandler`). This keeps the Service layer completely isolated from the web layer!

---

### Chapter 5.2: The Service Layer - Where the Work Happens

#### Subject & Intent: The Brain of the Application (The Evolutionary Starting Point)
If the Controller is the Traffic Cop, the **Service** is the highly-trained mechanic inside the garage.

*A Historical Note on Architecture:* When an application first starts, the Service Layer often holds most of the business rules (a pattern known as the **Transaction Script**). We teach this pattern here because it is crucial to understand *how* orchestration is isolated from Controllers and Databases. As you will see in **Module 10 (Domain-Driven Design)**, domain invariants evolve inward into Rich Domain Entities, keeping Services focused purely on orchestration.

#### The Theory: Services Orchestrate
A Service's job is **Orchestration**. It coordinates specialized collaborators:
1. Constructing or mutating domain objects (`UserRegistration`, `Review`).
2. Wrapping database writes inside atomic transactions (`TransactionManagerInterface::transactional()`).
3. Calling Command/Query repository interfaces (`UserCommandInterface`, `SiteReviewCommandInterface`).
4. Dispatching Domain Events (`EventDispatcherInterface::dispatch()`) so side-effects like emails happen decoupled from the core workflow.

#### File Walkthrough: `RegistrationService` (`magma/services/RegistrationService.php`)
Let's look at the actual `registerUser()` workflow in `magma/services/RegistrationService.php`:

```php
<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\validation\ValidationException;
use Magma\interfaces\EventDispatcherInterface;
use Magma\domain\events\UserRegisteredEvent;
use Magma\database\TransactionManagerInterface;

class RegistrationService
{
    protected UserCommandInterface $userCommandRepository;
    protected EventDispatcherInterface $dispatcher;
    protected TransactionManagerInterface $transactionManager;

    public function __construct(
        UserCommandInterface $userCommandRepository,
        EventDispatcherInterface $dispatcher,
        TransactionManagerInterface $transactionManager
    ) {
        $this->userCommandRepository = $userCommandRepository;
        $this->dispatcher = $dispatcher;
        $this->transactionManager = $transactionManager;
    }

    public function registerUser(\Magma\dto\RegistrationDTO $dto): int
    {
        $hashedPassword = password_hash($dto->password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);

        $registration = new \Magma\domain\UserRegistration($dto->name, $dto->email, $hashedPassword);

        $result = $this->transactionManager->transactional(function () use ($registration) {
            try {
                $userId = $this->userCommandRepository->create($registration);

                if ($userId) {
                    $this->dispatcher->dispatch(new UserRegisteredEvent($registration, $userId));
                }

                return $userId;
            } catch (\Magma\domain\exceptions\DuplicateResourceException $e) {
                throw new ValidationException(['email' => 'This email is already registered.']);
            }
        });

        return is_scalar($result) ? (int) $result : 0;
    }
}
```

#### Analyzing the Principles: The Power of Isolation
Look closely at `RegistrationService`. Notice what is missing?

* **No `$_POST`, `Request`, or `Session` objects.** The Service doesn't know it's a web app. It takes a strictly typed `RegistrationDTO`.
* **No `Response` objects.** It returns an `int` user ID or throws a `ValidationException`.
* **No `SQL` strings.** It delegates persistence to `UserCommandInterface`.
* **No concrete infrastructure couplings.** It uses interfaces (`UserCommandInterface`, `EventDispatcherInterface`, `TransactionManagerInterface`) so we can swap or test implementations easily, and delegates welcome emails to listeners subscribed to `UserRegisteredEvent`.

Because `RegistrationService` is perfectly isolated, a CLI seeder script can invoke the exact same service to create users without needing an HTTP request!

#### Common Questions and Answers

> **Q: Imagine we write a CLI script to provision users from the terminal. Why is it structurally impossible for that CLI script to use a web Controller directly, and why does it use a Service instead?**
> 
> **A:** Controllers deal with HTTP concepts (`Request`, `FormRequest`, `Response`, `RedirectResponse`). A command-line script doesn't have an HTTP request! By using a Service (`RegistrationService`) directly with a DTO, the CLI script bypasses the web layer entirely and reuses the pure PHP business logic.

> **Q: Why does `RegistrationService` dispatch `UserRegisteredEvent` via `EventDispatcherInterface` instead of directly calling `MailerService` to send a welcome email?**
> 
> **A:** It protects the Single Responsibility Principle (SRP). `RegistrationService` orchestrates user registration and transaction boundaries; it should not be coupled to SMTP transports or email templates. Listeners subscribed to `UserRegisteredEvent` handle welcome emails cleanly without bloating the registration workflow.

> **Q: How does Magma prevent a Service from becoming a monolithic "God Class" as features multiply?**
> 
> **A:** First, we split distinct workflows into cohesive services (e.g., `RegistrationService`, `AuthenticationService`, `PasswordResetRequestService`, `ReviewSubmissionService`). Second, we push data invariants into pure Domain Entities (Module 10) and offload side-effects via **Domain Events** (`EventDispatcherInterface`) and background jobs (Modules 11–12).

---


### Chapter 5.3: Declarative Auto-Wiring & FormRequests

How do we validate data without cluttering the controller? We use **FormRequests** (`Magma\validation\FormRequest`).

When `RouteParameterResolver` detects that a controller method requires a `FormRequest` subclass such as `Modules\Reviews\requests\ReviewRequest` (`modules/Reviews/requests/ReviewRequest.php`) or `Magma\requests\LoginRequest` (`magma/requests/LoginRequest.php`), it intercepts the pipeline *before* the controller is executed:

1. The Router instantiates the `FormRequest` (e.g., `ReviewRequest`) with the current `Request` and `Validator`.
2. It executes the declarative `rules()` validation automatically (for `LoginRequest`: `'email' => 'required|email'`, `'password' => 'required|max:100'`).
3. If validation fails, it automatically throws a `ValidationException`, which `ErrorHandler` catches to return JSON errors (`422`) or flash errors/old input and redirect back.
4. If it succeeds, the validated `FormRequest` object is injected straight into the Controller's method signature (Method Injection), ready for `$reviewRequest->toDTO()` or `$loginRequest->toDTO()`.

The Controller never even sees invalid data. It operates with absolute trust.
## Module 6: Data Persistence & Multi-Tenancy

### Chapter 6.1: The Repository Pattern - Protecting the Database

#### Subject & Intent: The SQL Quarantine Zone
In legacy applications, you often find raw SQL queries (`SELECT * FROM orders WHERE ...`) scattered everywhere. They are in the controllers, in the templates, and in the services. 

This creates three massive problems:
1. **Vendor Lock-in:** If you have MySQL-specific queries hardcoded in 500 different files, migrating to PostgreSQL is nearly impossible.
2. **Duplication:** You will inevitably write the same "Find user by ID" query in dozens of different places.
3. **Security (Our biggest concern):** If SQL is everywhere, it is incredibly easy for a developer to accidentally forget to add a critical `WHERE` clause, exposing data they shouldn't.

In the Magma framework, we use the **Repository Pattern**. 

A Repository acts as an intermediary collection. To the `OrderService`, the `OrderRepository` just looks like an array in memory. The Service says *"Give me Order #5"*, and the Repository goes and gets it. The Service has no idea if the order came from a MySQL database, a JSON file, or an external API.

**We enforce a strict rule: 100% of SQL must live inside a Repository class. SQL is illegal everywhere else.**

#### The Theory: The Contract of the Repository
Because we want to protect against Vendor Lock-in (Problem 1), we don't just inject a concrete Repository into our Service. We inject an **Interface**.

For example, in the Reviews module (`modules/Reviews/interfaces/cqrs/SiteReviewQueryInterface.php` and `SiteReviewCommandInterface.php`), read and write contracts are separated cleanly:

```php
<?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces\cqrs;

use Modules\Reviews\domain\Review;

interface SiteReviewQueryInterface
{
    public const DEFAULT_LIMIT = 10;

    /** @return iterable<\Modules\Reviews\dto\ReviewDTO> */
    public function getApprovedReviews(int $tenantId, int $limit = self::DEFAULT_LIMIT, ?int $lastId = null): iterable;
}

interface SiteReviewCommandInterface
{
    public function addReview(Review $review): bool;
}
```

By defining these contracts, `ReviewSubmissionService` knows *what* it can ask for without caring *how* PostgreSQL executes the SQL.

#### File Walkthrough: Concrete CQRS Implementations
Now let's look at the actual read repository in `modules/Reviews/repositories/SiteReviewQueryRepository.php` (extending `Magma\models\AbstractQueryRepository` in `magma/models/AbstractQueryRepository.php`):

```php
<?php

declare(strict_types=1);

namespace Modules\Reviews\repositories;

use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Magma\models\AbstractQueryRepository;
use Modules\Reviews\dto\ReviewDTO;
use Modules\Reviews\domain\Review;

class SiteReviewQueryRepository extends AbstractQueryRepository implements SiteReviewQueryInterface
{
    public const TABLE_NAME = 'site_reviews';

    public function getApprovedReviews(int $tenantId, int $limit = SiteReviewQueryInterface::DEFAULT_LIMIT, ?int $lastId = null): iterable
    {
        $pagination = new \Magma\dto\PaginationDTO(limit: $limit, lastId: $lastId);

        $sql = "SELECT id, comment, author, rating FROM \"" . self::TABLE_NAME . "\" WHERE status = :status AND tenant_id = :tenant_id";
        $params = [
            ':status' => Review::STATUS_APPROVED,
            ':tenant_id' => $tenantId,
        ];

        $result = $this->cursorPaginate($sql, $params, $pagination, 'id', 'DESC');

        foreach ($result['items'] as $row) {
            yield new ReviewDTO(
                author: $row['author'],
                comment: $row['comment'],
                rating: (int) $row['rating'],
                id: (int) $row['id']
            );
        }
    }
}
```

Meanwhile, write repositories extend `Magma\models\AbstractCommandRepository` (`magma/models/AbstractCommandRepository.php`) to route mutations to the Primary Write connection and inject `Magma\contracts\ClockInterface` for deterministic timestamps instead of SQL `NOW()`. Look at `magma/repositories/UserCommandRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Magma\repositories;

use Magma\models\AbstractCommandRepository;
use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\contracts\ClockInterface;
use Magma\database\DatabaseConnectionManager;
use Magma\security\TenantContext;
use Magma\domain\UserRegistration;

class UserCommandRepository extends AbstractCommandRepository implements UserCommandInterface
{
    protected ClockInterface $clock;

    public function __construct(DatabaseConnectionManager $dbManager, ?TenantContext $tenantContext, ClockInterface $clock)
    {
        parent::__construct($dbManager, $tenantContext);
        $this->clock = $clock;
    }

    public function create(UserRegistration $registration): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $data = [
            'name' => $registration->getName(),
            'email' => $registration->getEmail(),
            'password' => $registration->getHashedPassword(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $tenantId = $this->getTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }
        return $this->insertAndGetId('users', $data);
    }
}
```

#### Analyzing the Principles: The Multi-Tenancy Shield
Let's return to the most critical business requirement of the Magma framework: **Multi-Tenancy** (supporting multiple tenants on one shared PostgreSQL database).

If we share one database table for *all* tenants, the biggest risk is that "Tenant A" logs in and accidentally sees "Tenant B's" data. This is a catastrophic failure.

If developers are allowed to write SQL in controllers or services, it is almost guaranteed that someone will write `SELECT * FROM site_reviews` and forget to add `WHERE tenant_id = :tenant_id`.

Because we use the Repository Pattern paired with `Magma\security\TenantContext`, both `AbstractQueryRepository` and `AbstractCommandRepository` receive `$tenantContext` in their constructors (`$this->getTenantId()`). Every SQL query lives inside the repository and explicitly binds `tenant_id` in its `WHERE` clause or `INSERT` payload.

#### Common Questions and Answers

> **Q: Our `ReviewSubmissionService` requires a `SiteReviewCommandInterface`. If we want to unit-test the service without actually connecting to PostgreSQL, how does the Repository Pattern allow us to do that?**
> 
> **A:** We don't write any SQL to test it at all! Because the Service depends only on the `Interface`, we can inject an in-memory test double implementing `SiteReviewCommandInterface` during unit tests, completely bypassing the database layer.

> **Q: If a new privacy law passes requiring us to filter out archived reviews in `getApprovedReviews()`, how many files in our codebase do we modify?**
> 
> **A:** Just one: `SiteReviewQueryRepository`! Because all SQL is quarantined inside the repository, we update the query there, and every Service and Controller across the application respects the rule immediately.

---

### Chapter 6.2: Tenant Context - The Invisible Shield

#### Subject & Intent: The Greatest Risk in SaaS
As we mentioned in Chapter 6.1, the absolute greatest risk when building a Multi-Tenant platform (where many tenants share one database) is **Cross-Tenant Data Leakage**.

If "Sandbox Corp" logs into their dashboard, and due to a coding error, they see "Client B's" records, you have a massive legal and security breach on your hands.

If we rely on controllers to pass user-supplied `tenant_id` values around, human error or parameter tampering *will* eventually cause a data leak. We need a systematic, request-scoped context bound early in the pipeline.

#### The Theory: The Context Object
To build this shield, Magma uses `Magma\security\TenantContext` (`magma/security/TenantContext.php`). Early in the request lifecycle, `TenantSecurityMiddleware` resolves the tenant and binds it into this shared context object:

```php
<?php

declare(strict_types=1);

namespace Magma\security;

class TenantContext
{
    private ?int $tenantId = null;
    private ?int $venueId = null;
    private ?TenantContextProviderInterface $provider;

    public function __construct(?TenantContextProviderInterface $provider = null)
    {
        $this->provider = $provider;
    }

    public function setTenantId(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): int
    {
        if ($this->tenantId === null) {
            throw new \RuntimeException(
                'Tenant ID is not set in the current tenant context. This usually indicates unauthenticated access, missing tenant scoping middleware, or a cross-tenant boundary breach.'
            );
        }
        return $this->tenantId;
    }

    public function hasTenantId(): bool
    {
        return $this->tenantId !== null;
    }
}
```

Notice the fail-closed invariant: calling `getTenantId()` when no tenant has been bound immediately throws a `\RuntimeException` instead of silently returning `0` or `null`.

#### File Walkthrough: Consuming the Shield & Auth Tenant Modes
When a repository extends `AbstractQueryRepository` or `AbstractCommandRepository`, `DatabaseConnectionManager` and `TenantContext` are injected via the constructor, exposing `$this->getTenantId()` safely:

```php
$tenantId = $this->getTenantId();
if ($tenantId !== null) {
    $data['tenant_id'] = $tenantId;
}
return $this->insertAndGetId('users', $data);
```

In addition, `TenantSecurityMiddleware` supports two distinct operating modes via the backed/unit enum `Magma\security\AuthTenantMode` (`magma/security/AuthTenantMode.php`):
*   `AuthTenantMode::TenantScoped` (default for white-label tenant domains): validates that an authenticated user belongs to the resolved domain's `tenant_id`.
*   `AuthTenantMode::GlobalGateway` (for central portal gateways): rebinds `TenantContext` authoritatively from the authenticated user's `tenant_id`.

#### Analyzing the Principles: Secure by Default
Why is this architecture superior?

1. **Fail-Closed Isolation:** `TenantContext::getTenantId()` refuses to proceed if no tenant has been established by middleware.
2. **Scoped by Construction:** Both `AbstractQueryRepository` and `AbstractCommandRepository` accept `TenantContext` directly in their constructors, keeping tenant awareness available to every repository without touching `$_SESSION` or `$_POST`.
3. **CLI & Worker Portability:** Background scripts (`bin/worker.php`) don't have an HTTP session, so they can bind `$tenantContext->setTenantId($id)` explicitly for each job and reuse the exact same CQRS repositories safely.

#### Common Questions and Answers

> **Q: We write a CLI job that needs to process data across *all* tenants on the platform. If our Repositories rely on an injected `TenantContext`, how can a single script process multiple tenants safely?**
> 
> **A:** The worker iterates over the list of tenants, calls `$container->flushInstances()` between jobs (or creates a fresh `TenantContext` / binds the active `$tenantId`), and executes the same CQRS repositories for each tenant sequentially—preserving strict isolation on every iteration.

> **Q: Some ORMs use "Global Scopes," where the framework magically intercepts SQL behind the scenes and injects `WHERE tenant_id = 1` before it hits the database. Why does Magma require explicit `WHERE tenant_id = :tenant_id` clauses in repositories instead?**
> 
> **A:** You have to know what is going on behind the scenes to debug and audit security boundaries safely. Relying on hidden SQL rewriting can backfire when you encounter joins, subqueries, or global gateway queries. By explicitly injecting `TenantContext` and writing `WHERE tenant_id = :tenant_id` in each repository, the isolation boundary is transparent, auditable, and crystal clear.

#### Domain-Based Tenant Resolution (White Labeling)
As a platform grows, tenants may want their own dedicated URLs (e.g., `client.sandboxplatform.com` or custom white-label domains). To support this without polluting business logic, `TenantSecurityMiddleware` delegates host resolution to `DomainTenantContextProvider` (`TenantContextProviderInterface`), which resolves the incoming host via `TenantQueryInterface` (backed by the read-replica and cache) and binds the resulting `TenantContext`. The rest of Magma remains agnostic to whether the tenant came from a custom domain or an authenticated session.

---

### Chapter 6.3: The Evolution to CQRS & SERIALIZABLE ACID Compliance

The most profound architectural evolution in the Magma Framework is its persistence layer. As systems scale, a unified Repository becomes a bottleneck. Magma evolved to use **CQRS (Command Query Responsibility Segregation)**.

In Magma, read operations and write operations are physically and conceptually segregated:
*   **`AbstractQueryRepository` (`magma/models/AbstractQueryRepository.php`):** Used purely for fetching data via `$this->dbManager->getReadConnection()`. It returns DTOs (or yields them via Generators), preventing accidental writes or lazy-loading side effects in the presentation layer.
*   **`AbstractCommandRepository` (`magma/models/AbstractCommandRepository.php`):** Used purely for mutations (`INSERT`, `UPDATE`, `DELETE`) via `$this->dbManager->getWriteConnection()`.

#### Extreme ACID Compliance (Eliminating Phantom Reads)
Data integrity is the absolute highest priority. PostgreSQL defaults to `READ COMMITTED`, which can allow phantom reads under high concurrency.

Magma's `DatabaseTransactionManager` (`magma/database/DatabaseTransactionManager.php`) executes `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE` when starting transactions, guaranteeing that concurrent transactions behave as if executed serially and eliminating race conditions.

#### Write-Master Redirection
Crucially, when a transaction begins, `DatabaseConnectionManager` / `DatabaseTransactionManager` ensures queries inside an active transaction see the primary write-master state rather than lagging read replicas—eliminating replication-lag read-your-own-writes bugs.

#### The Liskov Substitution Principle (LSP) Firewall
If an abstract base class declares `protected function update(string $table, array $data)`, any concrete subclass trying to implement a domain interface with `public function update(int $id, array $data)` will crash PHP with a signature mismatch.

Magma solves this by naming internal base helpers `insertAndGetId()`, `executeUpdate()`, `executeDelete()`, and `execute()` inside `AbstractCommandRepository`, acting as an **LSP Firewall** so concrete repositories can freely implement domain method names (`create()`, `update()`, `delete()`, `addReview()`) without colliding with base class signatures.
## Module 7: Views and the Template Engine

### Chapter 7.1: The Final Boundary - HTML & Logic

#### Subject & Intent: The Sin of "Spaghetti Code"
If you look at PHP written 15 years ago, you will almost certainly find files that look like this:

```php
<!-- The Bad Old Days (Spaghetti Code) -->
<html>
<body>
    <h1>Latest Modules</h1>
    <?php
        $db = new PDO("mysql:host=localhost;dbname=sandbox", "root", "password");
        $stmt = $db->query("SELECT * FROM modules");
        while ($module = $stmt->fetch()) {
            if ($module['price'] > 20) {
                echo "<div class='expensive'>" . $module['name'] . "</div>";
            } else {
                echo "<div class='cheap'>" . $module['name'] . "</div>";
            }
        }
    ?>
</body>
</html>
```

This is called **Spaghetti Code** because the HTML (View), the Database Connection (Repository), and the Business Logic (Checking if the price is > 20) are all tangled together in one massive, unreadable knot. 

If a front-end designer wants to change the CSS class from `expensive` to `premium`, they have to open a file full of SQL queries and risk breaking the entire application.

In the Magma Framework framework, we fiercely enforce **Separation of Concerns**. The View layer is the absolute final boundary. 

#### The Theory: Dumb Views
Our philosophy is that **Views should be incredibly "dumb".** 

A View is simply an HTML template with "holes" cut out of it. It does not calculate anything. It does not query the database. It is entirely passive. It simply waits for the Controller to hand it a pre-packaged array of variables, and then it blindly loops through those variables and outputs them into the HTML holes.

If there is a business rule (e.g., validating a review status or calculating diagnostic status), that rule belongs in a Service or Domain Entity such as `Modules\Reviews\domain\Review`, *never* in the View.

#### File Walkthrough: Passing Data to the View
Let's trace how data reaches the View in the real `app/controllers/HomeController.php`:

```php
<?php

declare(strict_types=1);

namespace App\controllers;

use App\services\SystemDiagnosticsServiceInterface;

class HomeController
{
    public function index(
        SystemDiagnosticsServiceInterface $diagnosticsService,
        \Magma\view\HtmlResponseBuilderInterface $html
    ): \Magma\http\Response {
        $diagnostics = $diagnosticsService->getDiagnostics();

        return $html->render('welcome', [
            'title'       => \App\constants\AppConstants::HOME_TITLE,
            'diagnostics' => $diagnostics,
        ]);
    }
}
```

Notice that `HomeController` receives `\Magma\view\HtmlResponseBuilderInterface` (`magma/view/HtmlResponseBuilder.php`) via Method Injection and does not `echo` any HTML! Under the hood, `HtmlResponseBuilder::render()` automatically injects `csrfField` and `csrfToken`, delegates rendering to `Magma\view\TemplateEngine` (`magma/view/TemplateEngine.php`), and wraps the compiled string inside a `Magma\http\Response`. Meanwhile, `ViewShareMiddleware` (`magma/middleware/ViewShareMiddleware.php`) shares request-wide globals (`tenant`, `tagline`, `user`, `errors`, `old`), and `AssetVersionManager` (`magma/assets/AssetVersionManager.php`) provides cache-busted asset URLs.

#### The Template Engine (The Compiler)
Every view file in `app/views/` (like `app/views/404.php` or `app/views/welcome.php`) starts with a mandatory docblock specifying `Purpose`, `Teaching notes`, and `@var` parameter types, and escapes all dynamic values explicitly:

```php
<?php
/**
 * Title: Not Found Error View Template
 *
 * Purpose:
 * - Renders a clean, user-friendly fallback error page when a requested URL or resource is not found (404).
 *
 * Teaching notes:
 * - This template acts as an excellent standalone presentation layer fallback.
 *
 * @var string|null $message Safe error description.
 * @var int|null $code HTTP status code (404).
 * @var array{theme?: array<string, string|null>, tenant?: \Magma\dto\TenantDTO|null} $data
 */
$tenant = $data['tenant'] ?? null;
$appName = $tenant->name ?? (getenv('APP_NAME') ?: 'Magma Framework');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>404 Not Found | Magma</title>
</head>
<body>
    <h1><?= htmlspecialchars((string) ($code ?? 404), ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>
```

#### Analyzing the Principles: The XSS Vulnerability
You might have noticed something critical in the template above: we wrap dynamic text in `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

This is the most critical security rule of the View layer. **Cross-Site Scripting (XSS)** occurs when an attacker types malicious JavaScript into a form (e.g., setting their username to `<script>stealCookies();</script>`). If the View layer blindly outputs that username directly into the HTML using `echo $username;`, the browser will execute the attacker's script!

By wrapping every variable in `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, we neutralize the threat. It converts literal `<` and quote characters into safe HTML entities, rendering them harmless text on the screen.

#### Common Questions and Answers

> **Q: Imagine Sandbox Corp hires a junior Front-End Web Designer who only knows HTML and CSS, and does not know PHP. How does enforcing the "Dumb View" architecture make their job significantly safer and easier compared to the "Spaghetti Code" era?**
> 
> **A:** The data received is sanitized and empty of any logic that might harm or break the app. It provides simply the necessary information, ready for the view. The designer can focus on HTML and CSS without worrying about accidentally executing database queries or breaking complex backend routines.

> **Q: The business wants to highlight reviews that are approved with a green badge. A developer wants to write raw SQL or status-transition calculations directly inside `app/views/welcome.php`. Why does this violate the "Dumb View" philosophy, and where *should* that logic live?**
> 
> **A:** Business logic should not be in the View or in the Controller! (Controllers are just Traffic Cops). Domain rules belong in the **Service Layer** or the **Domain Model** (such as `Modules\Reviews\domain\Review` or a dedicated DTO property). The View simply checks the prepared boolean/state or renders the pre-formatted DTO field safely.

---


### Chapter 7.2: Multi-Directory Fallback & Resolution Caching

Large SaaS applications store views across multiple directory structures (`app/views` and namespaced `modules/*/views` directories automatically registered by `LocalFileViewLoader` inside `magma/providers/CoreServiceProvider.php`).

Magma's `TemplateEngine` intelligently falls back across these directories to resolve layouts. However, under heavy load, checking `file_exists()` across multiple directories for every partial creates severe disk I/O bottlenecks. 

To prevent this, the resolution paths are cached in-memory (`$resolvedLayoutCache`). The engine guarantees that the disk is only queried once per layout or partial per request lifecycle.

Furthermore, to support fully decoupled, multi-tenant components without relying on legacy assumptions, Magma forces explicit layout rendering. The framework does not default to searching for a `'default'` layout wrapper; components render completely standalone unless a layout is specifically requested in the controller.

### Chapter 7.3: Native PHP Stacks for Modular Assets

Large applications require child views to inject their own specific assets (like unique CSS files or JavaScript modules) up into the master layout's `<head>` or before the closing `</body>` tag. 

Magma's `TemplateEngine` provides native PHP stacks (similar to Laravel's `@push` and `@stack`) using `push(string $name)`, `endPush()`, and `stack(string $name)`. This uses nested output buffering (`$activeStacks` and `$stacks`) to seamlessly capture and bubble modular assets up to the master layout without polluting global variables.

### Chapter 7.4: Big-O DOM Interpolation Optimization

When parsing highly nested templates or loops, standard DOM interpolation suffers from O(N*M) Big-O time complexity as the engine redundantly scans child nodes. 

Magma's frontend JavaScript `TemplateEngine` solves this by temporarily detaching nested `[data-loop]` nodes via comment placeholders before evaluating outer directives. This flattens the execution curve to strict O(N) complexity, allowing massive, data-heavy UIs to render instantly.
## Module 8: Error Handling & Logging

### Chapter 8.1: Failing Gracefully

#### Subject & Intent: The White Screen of Death
In default PHP, when a fatal error occurs (like trying to connect to a database that is offline), the application either prints the raw, ugly stack trace to the browser (revealing your directory structure and sometimes passwords to the user!), or it fails silently and returns a completely blank white page ("The White Screen of Death").

Both of these are unacceptable in a professional application. 

In the Magma Framework, we enforce a global **Exception Handler** and absolute **Exception Boundaries** at the infrastructure level. 
For example, our base `AbstractQueryRepository` and `AbstractCommandRepository` explicitly catch `PDOException` natively at the execution layer, translating them into `DatabaseException`. Similarly, our storage adapter (`LocalStorageService` in `magma/infrastructure/storage/LocalStorageService.php`) throws `StorageException` upon disk permission failures or invalid paths, preventing silent boolean data loss. This mathematically guarantees raw database credentials, SQL syntax errors, or filesystem secrets never bleed into the domain or HTTP application layers.

To further elevate our Site Reliability Engineering (SRE), deep within our `ErrorHandler` and `NativeLogger` (`magma/logging/NativeLogger.php`), we protect sensitive payloads from hitting logs and utilize `CorrelationIdProvider` (`magma/logging/CorrelationIdProvider.php`) to attach a unique Trace ID to every request cycle, allowing developers to correlate events across boundaries.

Furthermore, we enforce **Pre-Kernel Boot Safety Nets**. If a fatal error occurs in `www/index.php` or `bin/worker.php` *before* the application container and `ErrorHandler` are fully registered, an outermost `try/catch` wrapper intercepts the failure to emit a clean 500 status code, protecting the system from 0-day `.env` or container misconfiguration leaks.

#### The Theory: Catching Everything
Instead of sprinkling `try/catch` blocks randomly throughout every single file, we register `ErrorHandlerInterface` (`Magma\error\ErrorHandler`) inside `Application::run()` (booted via `magma/config/bootstrap.php` and `CoreServiceProvider`).

If a `\Throwable` is thrown anywhere in the application—whether deep inside a Repository or high up in a Middleware—and it is *not* caught locally, it bubbles all the way up to `ErrorHandler::handleException()`.

#### File Walkthrough: The Error Handler
Here is the real constructor and flow in `magma/error/ErrorHandler.php`:

```php
<?php

declare(strict_types=1);

namespace Magma\error;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\view\TemplateEngine;
use Magma\interfaces\JsonErrorPresenterInterface;
use Magma\interfaces\DebugErrorPresenterInterface;

class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        TemplateEngine $templateEngine,
        \Magma\config\ConfigInterface $config,
        JsonErrorPresenterInterface $jsonPresenter,
        DebugErrorPresenterInterface $debugPresenter,
        ?\Magma\container\Container $container = null,
        ?bool $debug = null
    ) {
        // ... resolves $this->debug from $debug or APP_DEBUG / APP_ENV / ENVIRONMENT
    }

    public function handleException(\Throwable $e, ?RequestInterface $request = null): Response
    {
        // 1. Flush any partial output buffers (ob_end_clean)
        // 2. Return 422 JSON or flash ValidationException errors & redirect
        // 3. Log technical details securely
        // 4. Content-negotiate JSON via JsonErrorPresenterInterface when expected
        // 5. If $this->debug is true, render magma/error/templates/debug_error.php (paired with magma/error/assets/debug_error.css and debug_error.js) via DebugErrorPresenterInterface
        // 6. Otherwise call $this->renderError($code, $message, null, $request) -> app/views/404.php or 500.php
    }
}
```

#### Analyzing the Principles: Security, Content Negotiation, and Observability
This architecture separates concerns across environments and client types:

By tying debug diagnostics to `APP_DEBUG` and `ENVIRONMENT` (and isolating output rendering inside `DebugErrorPresenter` -> `magma/error/templates/debug_error.php` paired with standalone offline diagnostic assets `magma/error/assets/debug_error.css` and `magma/error/assets/debug_error.js`, vs. production `app/views/404.php` and `app/views/500.php`), an attacker can never purposefully crash the site in production just to read a stack trace.

Meanwhile, API clients (`Accept: application/json` or `/api/*` routes) automatically receive structured JSON payloads from `JsonErrorPresenterInterface`, while `NativeLogger` (`magma/logging/NativeLogger.php`) and `CorrelationIdProvider` (`magma/logging/CorrelationIdProvider.php`) preserve full technical diagnostics for engineers.

#### Common Questions and Answers

> **Q: If we didn't have a global `ErrorHandler` configured for `Application::run()`, and a developer forgot to write a `try/catch` block around a failing database query, what are the *two* completely different ways the application might behave depending on `php.ini`, and why are *both* terrible for production?**
> 
> **A:** It could print a full raw stack trace (exposing file paths and internal queries—a severe security risk), or it could emit a blank White Screen of Death (a terrible user experience). With `Magma\error\ErrorHandler`, production users always receive a clean, tenant-themed error page or JSON payload while the full failure is logged safely.

---


## Module 9: The Final Polish - DTOs (Data Transfer Objects)

### Chapter 9.1: Crossing the Boundary Safely

#### Subject & Intent: The Strict Typing Friction
Let's return to a problem we discussed in Pillar 4 (Strict Typing). 

Our domain services are strictly typed: for example, `AuthenticationService::attempt(string $email, string $password, bool $rememberMe)` or `ReviewSubmissionService::submit(ReviewDTO $dto, int $tenantId)`.

But the web browser submits forms as strings inside HTTP POST arrays. If we pass raw `$_POST` arrays directly into services, we lose type safety, risk missing keys, and couple business logic to HTTP payloads.

We need a translator and an immutable boundary object.

#### The Theory: The FormRequest + DTO Pair
A **FormRequest** validates incoming HTTP inputs, and a **Data Transfer Object (DTO)** is a simple, immutable `readonly class` carrying only strongly-typed properties into the service layer.

#### File Walkthrough: `LoginRequest` and `LoginDTO`
Let's look at the real `magma/dto/LoginDTO.php`:

```php
<?php

declare(strict_types=1);

namespace Magma\dto;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $rememberMe
    ) {}
}
```

And here is `magma/requests/LoginRequest.php`, which validates the payload and transforms it into `LoginDTO`:

```php
<?php

declare(strict_types=1);

namespace Magma\requests;

use Magma\validation\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|max:100',
        ];
    }

    public function toDTO(): \Magma\dto\LoginDTO
    {
        $data = $this->request->request();
        $email = is_array($data) && isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
        $password = is_array($data) && isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
        $rememberMe = is_array($data) && !empty($data['remember_me']);

        return new \Magma\dto\LoginDTO(
            email: trim($email),
            password: $password,
            rememberMe: $rememberMe
        );
    }
}
```

Now look at how clean `Magma\controllers\LoginController::authenticate()` (`magma/controllers/LoginController.php`) becomes:

```php
public function authenticate(LoginRequest $loginRequest, Request $request): Response
{
    // Validation is automatically handled by RouteParameterResolver for LoginRequest.
    $dto = $loginRequest->toDTO();

    $result = $this->authService->attempt($dto->email, $dto->password, $dto->rememberMe);

    if (!$result->isSuccessful()) {
        return $this->redirectResolver->redirectAfterFailedLogin($dto->email, 'Invalid credentials');
    }

    $user = $result->getUser();
    if ($user !== null) {
        return $this->applyAuthResult($result, $this->redirectResolver->redirectAfterAuthentication($user, $request), $request);
    }

    return new RedirectResponse('/login');
}
```

Another practical DTO in Magma is `Magma\dto\TenantDTO` (`magma/dto/TenantDTO.php`), which encapsulates tenant metadata and exposes type-safe white-label helpers such as `$tenant->setting(string $key, string $default = '')`, `settingBool()`, `settingArray()`, and `hasSetting()` instead of raw nested array lookups.

#### Common Questions and Answers

> **Q: Why is it architecturally better to put input rule checking and type extraction inside `LoginRequest` + `LoginDTO` rather than inside `AuthenticationService` or `LoginController`?**
> 
> **A:** `LoginRequest` handles HTTP input grammar (`required|email`, `max:100`), while `LoginDTO` guarantees an immutable, strictly-typed contract (`string $email`, `string $password`, `bool $rememberMe`). That leaves `LoginController` as a thin Traffic Cop and lets `AuthenticationService` stay 100% agnostic of HTTP forms.

---


### Chapter 9.2: Engine-Enforced Immutability

If a developer accidentally mutates a DTO mid-flight (e.g., `$dto->email = '';`), it can introduce catastrophic, hard-to-trace bugs.

Magma eliminates this entirely by utilizing PHP 8.2's native `readonly class` modifiers for all DTOs (such as `LoginDTO`, `TenantDTO`, `ReviewDTO`, and `PaginationDTO`).

Once a DTO is instantiated, its properties are locked down perfectly at the engine layer. Any attempt to modify a property will throw a fatal engine error. This prevents rogue scripts or dynamic property injections from mutating state mid-flight, eliminating an entire class of side-effect bugs.
## Module 10: The Evolution - Domain-Driven Design (DDD)

### Chapter 10.1: Transaction Scripts vs. Rich Domain Models

#### Subject & Intent: The Breaking Point of Services
In Module 5, we introduced the **Service Layer** and the **Transaction Script Pattern**. We built an `OrderService` that was incredibly smart: it pulled primitive data from the database, performed all calculations, and saved data back. 

The data itself (the modules, the orders) were just "dumb" arrays or basic objects without any methods. They were purely structural.

While Transaction Scripts are excellent for medium-sized applications, as Magma Framework scaled into an enterprise platform, we hit a breaking point. The Services became bloated "God Classes" because they held *all* the rules. We needed to evolve the architecture.

To solve this, our framework migrated to the current standard: **Domain-Driven Design (DDD)** and the creation of **Rich Domain Models**. This is how the Magma framework operates today.

#### The Theory: Behavior Belongs to the Data (Conceptual DDD Illustration)
In a Rich Domain Model, the objects that represent your business (Entities) are no longer "dumb." They contain the business logic that directly pertains to them. To illustrate the contrast conceptually (using a classic e-commerce `Order` example before looking at Magma's live entities):

**The Transaction Script Way (Conceptual Illustration — Dumb Data, Smart Service):**
```php
// The Service holds all the knowledge
class PricingService 
{
    public function applyDiscount(OrderData $order): void 
    {
        if ($order->total > 100) {
            $order->discount = 10;
            $order->total = $order->total - 10;
        }
    }
}
```

**The Rich Domain Model Way (Smart Data, Thin Service):**
```php
// The Entity protects its own state and knows its own rules!
class Order 
{
    private float $total;
    private float $discount = 0;

    // The order object itself decides how discounts are applied
    public function applyLoyaltyDiscount(): void 
    {
        if ($this->total > 100) {
            $this->discount = 10;
            $this->total -= 10;
        }
    }
    
    // We cannot change the total directly from the outside. 
    // We must use the entity's methods.
}

// The Service becomes just an Orchestrator again
class OrderService 
{
    public function finalizeCheckout(int $orderId): void 
    {
        $order = $this->orderRepository->find($orderId);
        
        // The service just tells the order to apply the discount.
        // It doesn't know *how* the discount is calculated!
        $order->applyLoyaltyDiscount(); 
        
        $this->orderRepository->save($order);
    }
}
```

#### Analyzing the Principles: Encapsulation
Rich Domain Models perfectly embody the Object-Oriented principle of **Encapsulation**.

An `Order` object shouldn't let a random `Service` change its `total` property manually. If the `total` changes, the `Order` needs to ensure taxes are recalculated and statuses are updated. By forcing the outside world to call `$order->applyLoyaltyDiscount()`, the `Order` object protects its internal state from becoming corrupted by a buggy Service.

#### The Migration Path: "Pragmatic DDD" (The Hybrid Approach)
Why didn't we start with a full, strict Rich Domain Model in the Magma framework?

Because strict Enterprise DDD requires you to completely and perfectly map your business rules (the Ubiquitous Language) *before* you write the code. When a project is young, business rules are still evolving. Trying to build a rigid Domain Model too early leads to Analysis Paralysis.

Instead, we use **Pragmatic DDD** as our architectural standard as we build out the site's functions:

1. **Build Entities As You Go:** When you interact with an Order, you create an `Order` object. At first, it might just hold data. But the moment you need to change its state, you put the logic *inside* the `Order` class, not the Service.
2. **Behavior Belongs With The Data:** If you need to map an incoming array into a format for the database, or if you need to calculate a tax rate, that logic belongs inside the Entity.
3. **Services Remain Orchestrators:** Services are forbidden from making decisions about a data object's internal state. The Service's only job is to fetch the Entity, tell the Entity to do something (`$order->markAsPaid()`), and save the Entity.

By using this hybrid approach, we prevent our Services from becoming bloated "God Classes", while retaining the flexibility to build our Domain Models organically as we discover what the business actually needs.

#### Common Questions and Answers

> **Q: A customer is trying to apply a promo code to their `Order` entity. To know if the code is valid, we must query the `promotions` table in the database. In Pragmatic DDD, should the `Order` entity receive the database connection to run the SQL query itself, or should the `CheckoutService` handle the database query and pass the result into the `Order` entity?**
> 
> **A:** The `Order` entity absolutely must run its own logic, but the `CheckoutService` must remain the orchestrator that touches the database! If we give the `Order` entity a database connection, it becomes tightly coupled to our infrastructure (the "Active Record" anti-pattern). Instead, the Service runs the SQL (via a Repository), fetches the pure data (like a `PromoCode` entity), and passes that pure data into the `Order` entity (`$order->applyPromo($promoCode)`). The service runs the SQL but doesn't care what the data is; the data is passed to the one that does care—the entity.

---


### Chapter 10.2: 100% Pure Domain Entities

Many architectures corrupt their domain models by passing HTTP-specific objects (like a `Request` or a framework-specific FormRequest) directly into them. 

Magma enforces **100% Pure Domain Entities**. 

Entities are completely agnostic of the HTTP and persistence layers. For example:
*   `Modules\Reviews\domain\Review` (`modules/Reviews/domain/Review.php`) accepts pure scalar fields (`int $tenantId`, `string $author`, `string $comment`, `int $rating`, `string $status`), validates domain invariants (`MIN_RATING = 1`, `MAX_RATING = 5`), and never touches a database connection or HTTP request.
*   `Magma\domain\AuthUser` (`magma/domain/AuthUser.php`) is a `readonly class` encapsulating only safe session fields (`id`, `name`, `role`, `email`, `tenantId`), preventing password hashes from ever leaking into session payloads.
## Module 11: Decoupling with Event-Driven Architecture

### Chapter 11.1: The Pub/Sub Pattern (Publish/Subscribe)

#### Subject & Intent: Breaking Apart the "God Class"
Even if we move business rules into Domain Models (Module 10), our Services can still become tangled. When a user places an order, the `CheckoutService` orchestrates the flow. But what happens if completing an order requires sending an email, deducting inventory, awarding loyalty points, notifying the fulfillment center, and updating the accounting ledger? 

If the `CheckoutService` explicitly calls five different systems, it is tightly coupled to all of them.

To solve this, enterprise applications use **Event-Driven Architecture**.

#### The Theory: Shouting into the Void
Instead of the `CheckoutService` explicitly telling the Mailer to send an email, it simply announces to the system: *"Hey everyone, an order was just completed!"* (Publish). 

Other systems in the application (like the Mailer or the Inventory system) "listen" for that announcement and react accordingly (Subscribe). The `CheckoutService` doesn't know who is listening, and it doesn't care.

#### The Synergy: Dispatching Rich Domain Models
This pattern becomes exceptionally powerful when combined with our **Pragmatic DDD** approach (Module 10). 

When our application was using simple Transaction Scripts, an event might look like `new OrderPlacedEvent(['order_id' => 5, 'total' => 100])`. The listeners would have to parse arrays.

Now, because we have Rich Domain Entities, the Event simply carries the entity itself:
`$dispatcher->dispatch(new OrderPlacedEvent($order))`

Because the `$order` entity already knows how to calculate its taxes, validate its status, and format its data, any Listener (like the Mailer) that catches the event instantly has access to all of those rich business rules. The Service orchestrates the flow, the Domain Model handles the rules, and the Event Dispatcher handles the side-effects.

#### Analyzing the Principles
By implementing an Event Dispatcher, you achieve ultimate **Separation of Concerns**. The checkout logic is completely decoupled from the notification logic, making it vastly easier to add new features later without touching the core checkout flow.

#### Common Questions and Answers

> **Q: In "magical" frameworks like Laravel, you can configure the framework so that any time a database row is inserted, it *magically* and invisibly fires an event behind the scenes. Based on our philosophy of avoiding "Magic", why do we actively reject this, and instead force the developer to manually type `$this->dispatcher->dispatch(new OrderPlacedEvent($order))` inside the Service?**
> 
> **A:** Because we need absolute control over *what* events are fired and *when* they are fired. Magic functions are hidden and may do things we don't want them to do. For example, if we run a bulk data import script that inserts 10,000 historic orders into the database, a "magical" framework might invisible fire 10,000 "OrderPlaced" events and accidentally spam 10,000 emails to customers from five years ago! By making the dispatch explicit in our Services, the code is transparent, safe, and entirely under our control.

---


## Module 12: Asynchronous Background Workers

### Chapter 12.1: Keeping the Web Fast

#### Subject & Intent: Offloading the Heavy Lifting
You've already built the foundation for this in your application (e.g., `bin/worker.php`). When a user submits an action via the web browser, they expect an instant response. If your PHP script connects to an external API (like an SMTP email server) and that server is slow, the user is left staring at a loading spinner.

#### The Theory: Queues and Workers
To solve this, we use an **Asynchronous Queue**. 
When a slow task needs to be done, the web server doesn't do it. Instead, it writes a small "Job" note (e.g., "Send a welcome email to User ID 5") and pushes it into a Redis list. Writing to Redis takes 1 millisecond. The web server immediately returns a success page to the user.

Meanwhile, a separate PHP process running continuously in the background (the Worker Daemon) constantly checks that Redis list. When it sees the note, it picks it up and spends the 3 seconds required to actually send the email.

#### Analyzing the Principles
This ensures the web tier remains lightning fast and completely isolated from the performance bottlenecks of third-party APIs or heavy background calculations.

#### Self-Healing and Tenant Safety
To guarantee that these background workers never crash your infrastructure, Magma implements **Self-Healing Workers** in `QueueWorkerDaemon` (`magma/queue/QueueWorkerDaemon.php`, backed by `Magma\queue\ReliableQueueInterface` in `magma/queue/ReliableQueueInterface.php`). It wraps queue polling in a `try/catch` block so transient Redis or worker failures log via `NativeLogger` (`magma/logging/NativeLogger.php`) and recover cleanly instead of crashing the daemon.

Additionally, because background workers run continuously within a single PHP process, they share the same `Container`. This presents a data-leakage risk in multi-tenant SaaS architectures if singleton state bleeds from Job A into Job B. To prevent this, `QueueWorkerDaemon` calls `$container->flushInstances()` between jobs so every job runs in a fresh instance scope.

---


### Chapter 12.2: The Transactional Outbox Pattern & Idempotent Projections

#### The Dual-Write Problem
Synchronous background tasks kill web performance. The standard solution is to dispatch to a message queue. However, this introduces the **Dual-Write Problem**: What happens if the database transaction commits successfully, but the network connection to Redis fails? The system is now in an inconsistent state.

Magma completely solves this using the **Transactional Outbox Pattern**.

Instead of dispatching directly to a queue, `OutboxJobRepository` (`magma/database/OutboxJobRepository.php`) records domain jobs *atomically* within the exact same database transaction as the domain mutation. If the transaction commits, the job is guaranteed to be in the database (`bin/outbox_publisher.php` publishes it safely); if it rolls back, the job vanishes.

#### FOR UPDATE SKIP LOCKED & Idempotent Projections
When multiple outbox publisher workers poll concurrently, `OutboxJobRepository::fetchAndLockPending()` uses PostgreSQL's native `SELECT ... FOR UPDATE SKIP LOCKED` locking primitive so concurrent workers skip locked rows and never block each other.

Because distributed queues provide at-least-once delivery, downstream projection consumers guard against duplicate replay via `IdempotentProjectionGuard` (`magma/queue/IdempotentProjectionGuard.php`), which records processed event checkpoints in the `projection_checkpoints` table.

### Chapter 12.3: Mastering Concurrency and Race Conditions

#### Subject & Intent: Designing for High Throughput
As web applications scale horizontally, naive assumptions about code execution order break down. A race condition occurs when two or more threads (or web requests) attempt to read and write shared data at the exact same millisecond. Without strict synchronization mechanisms, this leads to corrupted data, duplicate actions, and catastrophic failures.

Magma has undergone a rigorous, multi-pass **Concurrency Audit** to mathematically eliminate these architectural vulnerabilities from its core.

#### 1. TOCTOU (Time-Of-Check to Time-Of-Use) Races
A common anti-pattern is checking if a condition exists, and then acting on it (`if (!$repo->exists()) { $repo->insert(); }`). Under concurrency, Worker A and Worker B both check simultaneously, both receive `false`, and both insert duplicate state.

**The Magma Solution:** `IdempotentProjectionGuard` relies on the database engine's atomic truth via `INSERT ... ON CONFLICT DO NOTHING`. If the insert claims the row, execution proceeds; otherwise another worker already processed the event and execution halts safely.

#### 2. Two-Phase Reliable Queue Recovery (Orphan Prevention)
A naive Redis `BLPOP` removes a job from the list the instant a worker reads it. If that worker process suffers an OOM kill or hardware crash mid-job, the popped job vanishes forever.

**The Magma Solution:** `ReliableQueueInterface` (`magma/queue/ReliableQueueInterface.php`) and `RedisQueue` implement two-phase reliable consumption (`popReliable()`, `acknowledge()`, and `recoverOrphaned()`), moving in-flight payloads into a processing list until acknowledged so crashed jobs are deterministically recovered.

#### 3. Isolating Network I/O from Database Locks
Performing synchronous network I/O (pushing to Redis or calling an external HTTP API) *inside* an open PostgreSQL transaction holds row locks across network latency spikes and exhausts connection pools.

**The Magma Solution:** `bin/outbox_publisher.php` claims batches atomically without holding a database transaction open across Redis network pushes, keeping PostgreSQL lock durations sub-millisecond.

#### 4. Graceful Daemon Termination and Deadlocks
Background workers run in continuous loops (`while ($this->running)`). If a worker blocks indefinitely on Redis during deployment `SIGTERM`, orchestrators are forced to `SIGKILL` it mid-operation.

**The Magma Solution:** `QueueWorkerDaemon` registers `pcntl_signal` handlers, uses bounded `popReliable()` timeouts so shutdown flags are evaluated promptly, disconnects database handles cleanly, and invokes `$container->flushInstances()` per job.

#### 5. Cross-Tenant Rate Limit Starvation
If rate-limit keys are scoped only by route or IP, a spike or attack on Tenant A can exhaust shared counters and starve Tenant B.

**The Magma Solution:** `RedisRateLimiter` (`magma/security/RedisRateLimiter.php`) injects `TenantContext` and scopes Redis rate-limit keys per tenant, isolating rate-limit buckets across organizations.

#### 6. Dual-Write Events and Exception Aggregation
When an event such as `UserRegisteredEvent` triggers multiple listeners:
1. **The Dual Write:** Side-effects are persisted through `OutboxJobRepository` inside the ACID transaction rather than firing raw network calls before commit.
2. **Listener Isolation:** `EventDispatcher` (`magma/events/EventDispatcher.php`) aggregates listener throwables and throws `EventDispatchException` (`magma/events/EventDispatchException.php`) only after running remaining independent listeners, ensuring deterministic transaction rollback without silently skipping sibling listeners.
## Module 13: End of Cycle Considerations - Automated Testing

### Chapter 13.1: Reaping the Rewards of Architecture

#### Subject & Intent: Testing is a Byproduct of Design
We placed testing at the very end of this textbook because true, robust automated testing is only possible *after* you have built a clean architecture. 

If you write "Spaghetti Code" where controllers contain `new SmtpMailer()` or direct `$_POST` references, unit testing is nearly impossible because you cannot isolate the code from the real world. 

Because we built the Magma framework using strict **Dependency Injection (Module 3)** and **Encapsulated Requests (Module 4)**, testing becomes trivial. 

#### The Theory: Mocks and Fakes
If you want to test a service such as `RegistrationService`, you do not need a live PostgreSQL database. You inject in-memory fakes for `UserCommandInterface`, `EventDispatcherInterface`, and `TransactionManagerInterface`, verifying domain orchestration instantly without booting external infrastructure.

### Chapter 13.2: Deterministic Time (The Clock Interface)

#### Subject & Intent: Eradicating Hidden Global State
Time is inherently non-deterministic. Every time you call `time()`, `new DateTime()`, or SQL's `NOW()`, the output changes. If your application logic relies on these native functions (for instance, checking if a remember-me or password-reset token has expired), it becomes a hidden global dependency. This makes testing time-sensitive features flaky.

To resolve this, Magma implements a strict **`Magma\contracts\ClockInterface`** (`magma/contracts/ClockInterface.php`). Rather than calling native time functions, repositories and services inject `ClockInterface`. 
In production (`InfrastructureServiceProvider`), the DI container binds this to `Magma\infrastructure\time\SystemClock` (`magma/infrastructure/time/SystemClock.php`). 
In testing, we bind it to `Magma\infrastructure\time\MockClock` (`magma/infrastructure/time/MockClock.php`). This allows test suites to freeze, advance, or rewind time deterministically without pausing the CPU!


## Module 14: Frontend Architecture: Deep Freeze & CSS Layers

### Chapter 14.1: Deeply Immutable Reactive State Store

The client-side Vanilla ES6 architecture is as robust as the backend. Rather than relying on heavy frameworks like React or Vue, Magma implements a lightweight `ObservableStore.js`.

It employs a recursive `_deepFreeze()` algorithm that locks deeply nested state objects from rogue frontend mutations. Developers must dispatch explicit actions to mutate state, enforcing strict unidirectional data flow.

### Chapter 14.2: Defensive Garbage Collection

In dynamic single-page applications, DOM elements are frequently created and destroyed. Standard event listeners create "zombie" memory leaks.

Magma's global event delegators utilize defensive `isConnected` checks to gracefully unbind themselves if their target component is ripped from the DOM dynamically, ensuring clean garbage collection.

### Chapter 14.3: CSS Cascade Layers

Specificity wars destroy maintainability. Magma enforces native CSS Cascade Layers (`@layer reset, tokens, components, utilities, states;`). This permanently structures CSS precedence regardless of file inclusion order.

## Module 15: Security & Big-O Analytics

### Chapter 15.1: Static AST Boundary Auditing

Human code reviews are fallible. A developer might accidentally use a global `$_POST` variable inside a deeply nested Domain Service.

Magma provides a static analysis linter (`bin/audit_schema.php`, powered by `TenantSecurityAuditor`) alongside static AST rules on the `lava` testing branch (such as `tests/PHPStan/SuperglobalBanRule.php`), verifying that:
1. Multi-tenant foreign keys are correctly indexed.
2. Direct superglobal usage is statically prohibited inside business services and controllers.

If boundaries are breached, the linter fails the CI/CD pipeline.

### Chapter 15.2: Constant-Time B-Tree Keyset Pagination

Standard SQL pagination (`LIMIT 100 OFFSET 10000`) degrades linearly in performance (O(N)). 

Magma utilizes **Keyset Seeking** (`WHERE id < :last_id` / `WHERE id > :cursor_last_id` in `AbstractQueryRepository::cursorPaginate()`). By leveraging B-Tree indexes, the database jumps directly to the cursor boundary, delivering predictable keyset performance regardless of table offset depth.

### Chapter 15.3: Memory-Streaming Generators

Repositories returning large collections do not load the entire dataset into memory as a massive array. Instead, repositories such as `SiteReviewQueryRepository` stream records using PHP generators (`yield`), keeping RAM consumption flat during heavy workloads.

### Chapter 15.4: Zero-Trust Cross-Domain SSO Handoff & Role Policy

In a multi-tenant platform where each tenant has its own domain (e.g., `tenant-a.com` vs. `tenant-b.com`), global session cookies must never leak authentication state across boundaries.

Magma solves this natively using **Zero-Trust Cross-Domain SSO**. When `TenantSecurityMiddleware` (`magma/middleware/TenantSecurityMiddleware.php`) detects that an authenticated user belongs to a different `tenant_id` than the active host domain in `AuthTenantMode::TenantScoped`, it issues a short-lived SSO token via `AuthenticationService::issueSsoToken()` (delegating to `RememberMeService::generateToken($userId, 60)` backed by `RememberTokenRepository` in `magma/repositories/RememberTokenRepository.php`), calls `$this->authService->logout()` to clear the mismatched session, resolves the user's true domain via `TenantContextProviderInterface::resolveDomainByTenantId()`, and redirects them cleanly with `?sso=...`. Downstream authorization checks remain decoupled from raw strings via `RolePolicyInterface` and `ConfigRolePolicy` (`magma/security/ConfigRolePolicy.php`).

## Module 16: The Lava Hardening Phase - Enterprise Quality Control

### Chapter 16.1: The Eradication of Legacy Facades and "Magic"
Enterprise software accumulates technical debt primarily through backward compatibility. To achieve architectural purity, Magma underwent a "Lava" hardening phase where legacy facades and unused abstractions were systematically eliminated. 

The framework entirely abandoned primitive tuple arrays in the Routing engine in favor of strongly-typed `RouteDefinition` Value Objects. Backward-compatible wrappers and legacy shims were purged, forcing modern, object-oriented contracts at every layer.

### Chapter 16.2: Mathematical Type Safety via PHPStan Level 9 & `strict_types`
Type safety is the ultimate defense against runtime defects. The Magma core targets **PHPStan Level 9** (run on the `lava` testing branch alongside `tests/PHPStan/SuperglobalBanRule.php`) and enforces `declare(strict_types=1);` across `100%` of core PHP files in `magma/`.

By enforcing strict scalar types, demanding explicit array shapes, and preventing `mixed` types from leaking through boundaries, the framework eliminates silent type-coercion bugs before code ever reaches production.

### Chapter 16.3: Advanced Cryptography and Boundary Enforcement
Security is not bolted on; it is embedded into the core. 
1. **Argon2id Hashing:** `AuthenticationService` and `RegistrationService` use `PASSWORD_ARGON2ID`—a memory-hard hashing algorithm that defends against GPU-based brute-force attacks.
2. **Transparent Rehashing:** `AuthenticationService` detects outdated password hashes during successful logins and transparently upgrades them to Argon2id via `UserCommandRepository::updatePassword()`.
3. **Strict Headers:** `SecurityHeadersMiddleware` injects `Permissions-Policy` and defensive security headers by default, reducing the application's attack surface.

### Chapter 16.4: The Lava Testing Infrastructure - Zero Cross-Tenant Leakage
While Magma represents the dependency-free core architecture, the **Lava** branch represents its hardened, tested realization before being pulled downstream. Lava provides Database Integration test suites for downstream modules (`tests/Integration/Persistence/DatabaseIntegrationTestCase.php`).
By wrapping PHPUnit tests in isolated PostgreSQL `BEGIN/ROLLBACK` transactions, Lava prevents database pollution while keeping execution fast. Downstream developers extend `DatabaseIntegrationTestCase` to prove their CQRS repositories enforce `tenant_id` boundaries with zero cross-tenant leakage.

### Chapter 16.5: Deterministic Time, Factories, and Headless Execution
Lava pairs core contracts with industry-agnostic test harnesses:
1. **Deterministic `MockClock`:** `Magma\infrastructure\time\MockClock` replaces non-deterministic `time()` calls so tests can freeze, advance, or rewind wall-clock timestamps deterministically without sleeping.
2. **Agnostic Factories:** `AbstractModelFactory` (`tests/Integration/Persistence/Factories/AbstractModelFactory.php`) and `GenericTenantFactory` (`tests/Integration/Persistence/Factories/GenericTenantFactory.php`) hydrate relational test fixtures cleanly without repetitive boilerplate SQL.
3. **Async Event Marshalling & Headless HTTP:** `AsyncIntegrationTestCase` (`tests/Integration/Persistence/AsyncIntegrationTestCase.php`) drains the Transactional Outbox synchronously in tests, while `HttpIntegrationTestCase` (`tests/Integration/Http/HttpIntegrationTestCase.php`) dispatches simulated `Request` instances directly through `Application::handle()` and `TenantSecurityMiddleware` without spinning up a web server.

### Chapter 16.6: CI/CD Gates and AST Enforcement
GitHub Actions (`.github/workflows/ci.yml` and `.github/workflows/enforce-main-purity.yml`) boots PostgreSQL and Redis services to execute the full suite on every commit while physically guarding `main` purity. Lava also runs custom PHPStan AST rules (`tests/PHPStan/SuperglobalBanRule.php`) at **Level 9** to ban direct superglobal access (`$_POST`, `$_GET`, `$_SESSION`) outside designated boundary adapters.

> **CRITICAL WARNING FOR LAVA BRANCH:** The `lava` branch contains testing libraries (`PHPUnit`, `PHPStan`) and CI/CD configuration. **NEVER** merge `lava` back into `main`. The `main` branch must remain 100% pure, dependency-free Magma Core.
