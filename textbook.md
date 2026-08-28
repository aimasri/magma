# Magma Framework: The Masterclass Textbook


## Module 1: Introduction & Philosophy

### Chapter 1.1: The Domain Context & The Platform Vision

#### Subject & Intent: Understanding the "Why" Before the "How"
In software engineering, code is simply a tool used to solve a specific human problem. Before we write a single line of PHP, we must intimately understand the **Domain**—the business environment in which our software will operate. If we build an elegant architecture that solves the wrong business problem, we have failed as engineers.

In our case, the domain is "TSP," a generic software platform located in the cloud. They specialize in various domain entities, specifically distinguishing themselves with a "no magic" approach. 

This gives us immediate clues about our domain entities and data structures:
*   **Products:** We aren't selling generic widgets. We have specific attributes like ingredients, allergens, and preparation lead times.
*   **Inventory/Stock:** Baked goods are perishable and often made-to-order. Stock isnt just a number in a warehouse; it's tied to production capacity and calendar dates.
*   **Customers & Orders:** We are dealing with local logistics, specific pickup/delivery windows, and bespoke customer requests.

However, the defining characteristic of our architecture is the **Platform Vision**.

While Sandbox Corp is our *first* client (our "Tenant"), our intent is to design this system from the ground up as a platform capable of supporting *multiple* distinct vendors in the future. This concept is known as **Multi-Tenancy**.

#### Analyzing the Principles: Designing for Multi-Tenancy from Day One
It is a common trap in software development to hardcode business logic for a single client, assuming you can "generalize it later." Generalizing a massive, tightly-coupled codebase later is incredibly expensive and error-prone. As we established, hardcoding rules for one vendor means they will inevitably be incorrectly applied to future vendors.

Instead, we are adopting a platform-first mindset.

> **Historical Context:** In the early days of SaaS (Software as a Service), companies often stood up a completely separate database and codebase for every new client (Single-Tenant). While secure, this became a nightmare to maintain and deploy. The industry shifted toward Multi-Tenant architectures, where a single instance of the application and database serves multiple clients, using a `tenant_id` to strictly separate data.

To achieve this in the Magma Framework framework, we apply the following principles:

1.  **Strict Data Isolation at the Repository Layer:** By establishing the Repository Pattern early, we ensure that every database query can eventually be scoped to a specific `vendor_id`. A controller will never accidentally query `SELECT * FROM orders`; it will always ask the repository for `Orders for Vendor X`. This mitigates the massive risk of cross-tenant data leakage—the most critical danger in a shared database environment.
2.  **Agnostic Core Domain:** The core application doesn't care that Sandbox Corp makes modules. It only understands abstract concepts: `Vendors`, `Products`, `Orders`, and `Inventory`. The specifics are data, not code.
3.  **Configuration over Hardcoding:** If a vendor has a specific rule, we abstract this into a configurable business rule associated with the vendor's profile, rather than burying it in `if/else` statements within our services.

**Code Example: Hardcoding vs. Abstraction**

Imagine Sandbox Corp does not allow orders to be placed on Sundays. 

*The Wrong Way (Hardcoded Logic):*
```php
class OrderService 
{
    public function placeOrder(OrderDTO $order): bool 
    {
        // BAD: Hardcoding a specific tenant's rule into the core platform!
        $dayOfWeek = date('l', strtotime($order->deliveryDate));
        if ($dayOfWeek === 'Sunday') {
            throw new Exception("Sandbox Corp is closed on Sundays.");
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
In the Magma Framework architecture, we demand **Explicit Dependencies**. If your class needs a cache, you must ask for it in the constructor.

```php
// The Explicit "Magma" Way
class ProductService 
{
    private CacheInterface $cache;

    // We explicitly demand our dependency!
    public function __construct(CacheInterface $cache) 
    {
        $this->cache = $cache;
    }

    public function getProduct() 
    {
        $value = $this->cache->get('key');
    }
}
```

**The Danger of the `new` Keyword (Tight Coupling)**
You asked earlier why using `$mailer = new SmtpMailer();` inside a controller is almost as bad as using a Facade. 

The answer is **Tight Coupling**. If you write `new SmtpMailer()` directly inside your code, your controller is permanently glued to that exact SMTP class. If tomorrow the business says, "We are switching to the Mailgun API," you have to open the controller and rewrite the code.

If instead you injected a `MailerInterface` into the constructor, you would simply change the configuration in your Dependency Injection Container (which we will cover in Module 3), and the controller *never has to change*. The `new` keyword prevents your code from being flexible!

**The Comparison Summary:**
Why do we choose explicit injection? 
*   **Readability:** The constructor acts as an honest contract. Anyone looking at the class instantly knows exactly what external systems it relies on.
*   **Testability:** To test `ProductService`, we simply instantiate it and pass in a `new DummyArrayCache()`. No complex mocking frameworks required.
*   **Swappability:** Because we ask for an `Interface`, we can swap out a Redis cache for a Memcached system without changing a single line of code inside `ProductService`.

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

/**
 * The Front Controller.
 * 
 * This is the only file in the entire application that should be publicly 
 * accessible to the web. It serves as the gateway that initiates the 
 * bootstrapping process and hands control over to the Application kernel.
 */

// 1. Leave the public directory immediately
require __DIR__ . '/../magma/core/config/bootstrap.php';

// 2. Define our environment
define('ENVIRONMENT', \core\config\Config::get('APP_ENV', 'production'));

use core\Application;
use core\middleware\CsrfMiddleware;
use core\middleware\UTMTrackerMiddleware;
use core\middleware\ViewShareMiddleware;
use core\middleware\SessionTimeoutMiddleware;

// 3. Resolve the application and add security layers
$app = $container->get(Application::class);
$app->addMiddleware(UTMTrackerMiddleware::class);
$app->addMiddleware(CsrfMiddleware::class);
$app->addMiddleware(SessionTimeoutMiddleware::class);
$app->addMiddleware(ViewShareMiddleware::class);

// 4. Run the application!
$app->run();
```

#### Analyzing the Principles: The Public Boundary
The most crucial line in this file is the very first one:
`require __DIR__ . '/../magma/core/config/bootstrap.php';`

Notice the directory structure. The web server (like Nginx or Apache) is configured to serve files *only* from the `www/` directory. However, all of our actual code, business logic, and configuration files live in `magma/`, which sits completely outside the document root.

By executing that `require` statement, we immediately "jump" out of the public folder and into our secure application container. 

This is the ultimate Separation of Concerns regarding security. Even if the web server suffers a catastrophic misconfiguration and accidentally serves `.php` files as raw text instead of executing them, an attacker could only see the contents of `index.php`. They cannot reach our database credentials because those files simply do not exist within the server's public path.

#### The "Why" & Framework Comparison
Both Laravel (`public/index.php`) and Symfony (`public/index.php`) use this exact pattern, and for good reason. It provides a centralized location to enforce rules. 

Because we add our `SessionTimeoutMiddleware` and `CsrfMiddleware` directly into the `$app` right here at the gateway, we absolutely guarantee that no controller deep inside the application can ever be reached without passing those security checks first. If we used the old multiple-file pattern, we could never make that guarantee.

#### Common Questions and Answers

> **Q: What is the security risk of having the core application folder (e.g. `magma/`, which contains the `.env` file) inside the public `www/` directory?**
> 
> **A:** If those files are in the public domain, the database credentials and secret keys become easily accessible. If a web server misconfiguration occurs, an attacker could navigate directly to `website.com/magma/.env` and download your passwords as plain text. Keeping them strictly outside the document root eliminates this vector entirely.

> **Q: Looking at `index.php`, why does it make sense to attach "global" middleware (like CSRF and Session checks) here at the entrance, rather than waiting until the Router decides which Controller to execute?**
> 
> **A:** Think of it like a bag check at the entrance of the festival gates, rather than having one at every stall. By performing the security check once at the absolute perimeter, we guarantee that no malicious payload even reaches the inner workings of our application. We enforce security by default, rather than relying on individual controllers to remember to check it.

---

### Chapter 2.2: Bootstrapping the Application

#### Subject & Intent: Waking Up the Application
If `index.php` is the front door, `bootstrap.php` is the process of turning on the lights, booting up the computers, and unlocking the cash register before the customers arrive. 

When execution jumps out of the `www/` directory via that `require` statement, it lands in `./magma/core/config/bootstrap.php`. This file is responsible for preparing the environment so that our business logic has everything it needs to execute.

#### File Walkthrough: `bootstrap.php`

Let's look at the critical sections of this file:

```php
<?php
// ... docblocks omitted ...

// 1. The Autoloader
require_once __DIR__ . '/autoload.php';

use core\container\Container;
use core\config\Config;
use core\providers\CoreServiceProvider;
use core\providers\RepositoryServiceProvider;
// ...

// 2. Load Environment Variables
Config::initialize();

// 3. Initialize the Service Container
$container = new Container();

// 4. Register Service Providers
$providers = [
    new CoreServiceProvider(),
    new RepositoryServiceProvider(),
    new DomainServiceProvider(),
    new HttpServiceProvider(),
];

foreach ($providers as $provider) {
    $provider->register($container);
}
```

#### Analyzing the Principles

**1. The PSR-4 Autoloader**
Before PHP 5.3, if you wanted to use 10 different classes in a file, you had to write 10 `require 'path/to/class.php';` statements at the top of your file. It was a nightmare. 
Today, we use an **Autoloader** (specifically adhering to the PSR-4 standard). We require the autoloader *once* here in the bootstrap file. From then on, whenever PHP sees `new core\container\Container()`, the autoloader intercepts it, maps the namespace `core\container` to the physical folder path, and includes the file automatically behind the scenes.

**2. Centralized Configuration**
`Config::initialize()` parses our hidden `.env` file (which contains our database passwords and API keys). Crucially, it loads them into a centralized `Config` object. We do this early so the rest of the application can simply ask the `Config` object for settings, rather than interacting with the raw filesystem or server globals.

**3. The Service Providers**
To keep our bootstrap file from becoming 5,000 lines long, we use the **Service Provider Pattern**. We group our setup logic into logical chunks (Core, Repository, Domain, Http). The bootstrap file simply loops over them and tells them to register themselves with the Dependency Injection Container. 

#### The "Why" & Framework Comparison
Why keep `index.php` and `bootstrap.php` separate? Why not just put all the bootstrap logic directly in `index.php`?

**Testing and CLI access.**
If we put all the boot logic inside `index.php`, we couldn't run automated tests or command-line (CLI) scripts without faking an HTTP web request. By isolating the boot sequence into `bootstrap.php`, our web gateway (`index.php`), our CLI worker daemon (`worker.php`), and our PHPUnit test suite can all simply `require 'bootstrap.php'` to wake up the application securely and consistently!

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

If we look back at the final lines of our `bootstrap.php` file, we see this:

```php
// magma/core/config/bootstrap.php

// The Container is instantiated
$container = new Container();

// Providers are given the container so they can register their services
foreach ($providers as $provider) {
    $provider->register($container);
}
```

At this exact millisecond, the `$container` object holds a complete "map" of every interface in our application and the concrete class it should use (e.g., "If someone asks for `DatabaseInterface`, give them `PostgresConnection`"). We will dive deeply into *how* the container works in Module 3, but for now, just understand that it is our master factory.

Now, execution returns to `www/index.php` for the finale:

```php
// www/index.php

// 1. We ask the container to build the Application Kernel for us
$app = $container->get(Application::class);

// 2. We attach our security middleware
$app->addMiddleware(UTMTrackerMiddleware::class);
$app->addMiddleware(CsrfMiddleware::class);
$app->addMiddleware(SessionTimeoutMiddleware::class);

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
1.  **The Application Kernel (`Application.php`):** Resolves the HTTP Request, dispatches it through the Middleware Onion, and returns an HTTP Response. It is strictly for web traffic.
2.  **The CLI Kernel (`CliKernel.php` or raw Bin Scripts):** Initializes the exact same Dependency Container, but completely bypasses the Router and Middleware. It expects console arguments instead of HTTP Requests.

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
In the Magma Framework framework, we use an advanced container technique called **Auto-Wiring**. We don't manually tell the container how to build every single class. Instead, the container uses Reflection to figure it out on the fly.

Here is a simplified example of what the Magma Container is doing inside its `get()` method when you ask it for a class:

```php
// You ask the container for the OrderController
$container->get(OrderController::class);
```

Behind the scenes, the Container uses the `ReflectionClass`:

```php
// 1. The Container reflects upon the requested class
$reflection = new ReflectionClass(OrderController::class);

// 2. It looks at the constructor
$constructor = $reflection->getConstructor();

// 3. It asks: "What parameters do you require?"
$parameters = $constructor->getParameters();

$dependencies = [];

// 4. It loops through the required parameters
foreach ($parameters as $parameter) {
    // It finds that the constructor needs an "OrderService" type!
    $dependencyType = $parameter->getType()->getName(); 
    
    // 5. RECURSION! The container calls ITSELF to build the OrderService!
    $dependencies[] = $this->get($dependencyType); 
}

// 6. Finally, it builds the OrderController, passing in the newly built dependencies.
return $reflection->newInstanceArgs($dependencies);
```

#### Analyzing the Principles
This is the moment the "Magic" becomes **Science**. 

When a framework automatically injects dependencies into your controllers, it isn't using actual magic. It's just using `ReflectionClass` to read your constructor's type hints, building those dependencies recursively, and then handing you the fully assembled object. 

Because we demand **Strict Typing** (Pillar 4), our constructors always look like this:
`public function __construct(OrderService $service)`

Because we strictly typed the `$service` parameter as an `OrderService`, the Reflection API can read that type hint and know exactly what to build. If we didn't use strict typing, Reflection would have no idea what to inject, and auto-wiring would fail entirely!

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
In Chapter 3.2, we saw that Auto-Wiring is incredibly smart. If a controller's constructor asks for an `OrderService`, the Container uses Reflection, finds the `OrderService` class, and builds it.

But what happens when we follow the Dependency Inversion Principle (Pillar 1) properly? 
What if our constructor looks like this?

```php
public function __construct(DatabaseInterface $database)
```

**Reflection hits a wall.** It looks at `DatabaseInterface` and realizes it cannot instantiate an Interface. An interface is just a contract; it has no actual code. If Reflection tries to write `$db = new DatabaseInterface();`, PHP will throw a Fatal Error.

The Container needs to know *which specific concrete class* to use when someone asks for that interface. 

#### File Walkthrough: The Service Providers
To solve this, we give the Container a manual "map." We say: *"Hey Container, whenever someone asks for `DatabaseInterface`, I want you to give them an instance of `PostgresConnection`."*

We call this **Binding**. In Magma Framework, we organize our bindings into **Service Providers**.

Let's look at `magma/core/providers/CoreServiceProvider.php`:

```php
namespace core\providers;

use core\container\Container;
use core\interfaces\DatabaseInterface;
use core\database\PostgresConnection;

class CoreServiceProvider
{
    /**
     * Register core bindings into the container.
     */
    public function register(Container $container): void
    {
        // We tell the container exactly how to resolve the interface!
        $container->bind(DatabaseInterface::class, PostgresConnection::class);
        
        // ... other bindings ...
    }
}
```

If you recall Chapter 2.2 (Bootstrapping), our `bootstrap.php` file loops through these Service Providers and executes their `register()` methods right before the application runs.

#### Analyzing the Principles: The Power of Swappability
By separating the *request* for an object (in the constructor) from the *configuration* of that object (in the Service Provider), we achieve ultimate architectural flexibility.

This is the very essence of the **Open/Closed Principle (OCP)**. 

Imagine Sandbox Corp wants to switch their cache system from Redis to Memcached. 
1. We write a new `MemcachedCache` class that implements `CacheInterface`.
2. We go to our `CoreServiceProvider` and change one line of code:
   `$container->bind(CacheInterface::class, MemcachedCache::class);`

**That's it.** We don't have to touch the `ProductService`, the `OrderController`, or any of the hundreds of files that use the Cache. The Container simply starts handing out the new Memcached object to anyone who asks for the Interface.

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

Magma's Container manages memory efficiently using a **Singleton Cache** for stateless services. When a `DatabaseConnectionManager` is resolved for the first time, the Container caches the instance. If five different Repositories ask for a `DatabaseConnectionManager`, they all receive the exact same instance reference in memory (O(1) memory overhead).

By preventing the redundant instantiation of heavy services, Magma keeps its memory footprint entirely flat, allowing it to serve thousands of requests concurrently on minimal hardware.

### Chapter 3.5: Breaking Circular Dependency Deadlocks

A classic architectural bug occurs when `ServiceA` requires `ServiceB`, but `ServiceB` requires `ServiceA`. If left unchecked, the Reflection engine will enter an infinite loop, crashing the server with a Stack Overflow or an Out Of Memory (OOM) error.

Magma's `Container` physically protects against this by tracking resolution paths. If it detects a circular dependency (e.g., trying to resolve `ServiceA` while `ServiceA` is already in the 'resolving' stack), it immediately aborts and throws a `CircularDependencyException`.
## Module 4: Routing & The HTTP Request

### Chapter 4.1: The Router - Mapping URLs to Controllers

#### Subject & Intent: The Traffic Cop
When a user types `sandbox.local/products` into their browser, an HTTP `GET` request is sent to our server. As we know from Module 2, this request hits our Front Controller (`www/index.php`) and triggers `$app->run()`.

But how does the application know that `/products` should execute the code that fetches modules from the database, while `/checkout` should execute the payment code?

The answer is the **Router**. 

The Router is the "Traffic Cop" of the application. Its sole responsibility is to look at the incoming URL (the URI) and the HTTP Method (GET, POST, PUT, DELETE), and match it against a predefined list of "Routes" to determine which **Controller** should handle the request.

#### The Theory: Defining Routes
Before the Router can route traffic, we have to give it a map. In the Magma Framework framework, we explicitly define this map in a dedicated routes file.

A typical route definition looks like this:

```php
// If the user makes a GET request to '/products', 
// execute the 'index' method on the 'ProductController' class.

$router->get('/products', [ProductController::class, 'index']);

// If they make a POST request to '/checkout' (submitting a form),
// execute the 'process' method on the 'CheckoutController' class.

$router->post('/checkout', [CheckoutController::class, 'process']);
```

#### File Walkthrough: The Execution Flow
Let's look at exactly what happens inside the `Application::run()` method when that request comes in:

1. **Capture the Request:** The application creates an `HttpRequest` object containing all the details from the user's browser (the URL, the POST data, headers).
2. **Hand it to the Router:** The application passes this request object to the `Router`.
3. **Find the Match:** The `Router` loops through all the registered routes (like the ones we defined above). It sees the user is asking for `GET /products`, and it finds a matching route!
4. **Resolve the Controller:** The Router sees that this route points to `ProductController::class`. It asks the **DI Container** (from Module 3) to build the `ProductController`.
5. **Execute the Method:** The Router takes the fully built `ProductController` and calls the `index()` method on it.

```php
// A simplified view of what the Router does internally:
$controllerInstance = $container->get($route->getControllerClass());
return $controllerInstance->{$route->getMethodName()}();
```

#### Analyzing the Principles: Separation of Concerns
You might wonder: *Why do we have a dedicated Router class? Why can't we just put a giant `if/else` statement inside `index.php`?*

```php
// The Bad Way (Violating SRP)
if ($_SERVER['REQUEST_URI'] === '/products') {
    $controller = new ProductController();
    $controller->index();
} elseif ($_SERVER['REQUEST_URI'] === '/checkout') {
    // ...
}
```

This violates the **Single Responsibility Principle (SRP)**. If we put routing logic inside the `index.php` or `Application` class, those files would grow to thousands of lines long as the application scales. By extracting routing into its own dedicated `Router` object, we keep our code modular, testable, and extremely clean. 

Furthermore, a dedicated Router can handle complex scenarios like dynamic parameters:
`$router->get('/products/{id}', [ProductController::class, 'show']);`
Here, the Router is smart enough to extract `{id}` from the URL (like `/products/5`) and pass the `5` as an argument directly into the controller's `show($id)` method!

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
Every single Middleware class in the Magma framework follows a strict contract. It receives the incoming Request, and a special function called `$next`.

The Middleware has absolute power to decide what to do:
1. **Pass:** It can inspect the Request, decide everything is fine, and call `$next($request)` to pass the request deeper into the onion.
2. **Reject:** It can inspect the Request, find a problem, and instantly return a redirect or an Error Page, **completely blocking the request from ever reaching the controller**.

#### File Walkthrough: `SessionTimeoutMiddleware`
Let's look at a concrete example. We want to automatically log out users if they have been inactive for 30 minutes. 

We *could* write this check at the top of every single Controller, but that violates DRY (Don't Repeat Yourself) and SRP. Instead, we write a single Middleware:

```php
namespace core\middleware;

use core\http\Request;
use core\http\Response;

class SessionTimeoutMiddleware 
{
    public function handle(Request $request, callable $next): Response 
    {
        // 1. Inspect the Request context (the session)
        $lastActivity = $_SESSION['last_activity'] ?? time();
        $timeoutLimit = 1800; // 30 minutes in seconds

        if (time() - $lastActivity > $timeoutLimit) {
            // 2. REJECT! We do NOT call $next. 
            // The Controller is never reached. We immediately return a redirect.
            session_destroy();
            return Response::redirect('/login?error=timeout');
        }

        // 3. Update the activity timer
        $_SESSION['last_activity'] = time();

        // 4. PASS! Hand the request to the next layer of the onion
        return $next($request);
    }
}
```

If you remember from `index.php`, we register this globally: `$app->addMiddleware(SessionTimeoutMiddleware::class);`

Because this is registered globally, our `ProductController` doesn't have to know that session timeouts even exist. It can safely assume that if a request reached it, the session is active.

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

Instead, we encapsulate everything into two strict objects: the `HttpRequest` object and the `HttpResponse` object.

#### The Theory: Why Encapsulation Matters
A fundamental rule of Object-Oriented Programming is that you should not rely on global state. 

If your `OrderController` looks directly at `$_POST['quantity']`, it is relying on a global variable. This creates a massive problem for **Testability**. If you want to write a unit test for your controller, you have to artificially inject fake data into the `$_POST` array before running the test, which is clunky and can accidentally bleed into other tests.

Furthermore, `$_POST` is untyped. Is the quantity an integer `5`, or the string `"5"`, or an array? 

#### File Walkthrough: The `Request` Object
At the very beginning of the Front Controller lifecycle, our application gathers all the global variables, packages them into a `Request` object, and then destroys the global variables (figuratively speaking) so no one else can use them.

This `Request` object is what gets passed through the Middleware and eventually into the Controller.

```php
namespace core\http;

class Request 
{
    private array $postData;
    private array $queryData;

    public function __construct(array $post, array $query) 
    {
        $this->postData = $post;
        $this->queryData = $query;
    }

    // We control exactly how the data is accessed!
    public function getPostString(string $key, string $default = ''): string 
    {
        $value = $this->postData[$key] ?? $default;
        return is_string($value) ? htmlspecialchars($value) : $default;
    }

    public function getPostInt(string $key, int $default = 0): int 
    {
        return (int) ($this->postData[$key] ?? $default);
    }
}
```

Look at how powerful this is! By forcing the Controller to use `$request->getPostInt('quantity')`, we guarantee that the Controller receives an actual integer, completely satisfying our strict typing requirements. We can also build automatic security features (like `htmlspecialchars()`) directly into the object.

#### File Walkthrough: The `Response` Object
Similarly, a Controller should never use `echo`. Why? Because `echo` immediately flushes data to the browser. If a Middleware layer on the "way out" wanted to compress that data, it's too late! The data is already gone.

Instead, a Controller always returns a `Response` object. 

```php
namespace core\http;

class Response 
{
    private string $content;
    private int $statusCode;

    public function __construct(string $content, int $statusCode = 200) 
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
    }

    // The ONLY place in the application where output actually happens
    public function send(): void 
    {
        http_response_code($this->statusCode);
        echo $this->content;
    }
}
```

The Application kernel receives this `Response` object from the Controller, passes it outward through the Middleware (allowing them to modify `$response->content` if they wish), and finally, the kernel calls `$response->send()` at the very end of the lifecycle.

#### Analyzing the Principles
By utilizing Request and Response objects, we enforce **Immutability and Control**. The flow of data is no longer a chaotic free-for-all of global variables. It is a strictly typed, trackable package moving through an organized pipeline.

#### Common Questions and Answers

> **Q: How does requiring a Request object (rather than looking directly at `$_POST`) make writing automated unit tests for a Controller significantly easier and cleaner?**
> 
> **A:** If everything passes through our Request object checkpoint, we can easily test the controller without a real browser. We simply write `$request = new Request(['quantity' => 5], []);` and pass it to our Controller. We don't have to hack the global `$_POST` array, meaning our tests are perfectly isolated, predictable, and we can test the data against any rule we choose from one location.

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
## Module 5: Controllers & Services (The Business Logic)

### Chapter 5.1: The Controller - The Traffic Cop (Redux)

#### Subject & Intent: The Delegator
The word "Controller" in the MVC (Model-View-Controller) pattern is somewhat dangerous. It implies that the controller should *control* how the application works. 

This historically led to what developers call "Fat Controllers." A fat controller is a file with 1,000 lines of code that handles file uploads, writes to the database, calculates taxes, and sends emails all in one giant method.

In the Magma Framework architecture, we view the Controller strictly as a **Delegator**. It is a middle-manager. It does not do the hard work; it simply organizes the work.

#### The Theory: The Three Rules of a Controller
To prevent "Fat Controllers," we enforce three absolute rules for any Controller class:
1. **Never write Business Logic:** A controller should not calculate tax. It should not check inventory.
2. **Never query the Database:** A controller should never contain SQL or interact directly with a database connection.
3. **Always return a Response:** The controller's only purpose is to take the incoming `Request`, give the data to a "Service," and wrap the result in a `Response`.

#### File Walkthrough: A Magma Controller
Let's look at what a perfectly clean Controller looks like when a customer submits an order:

```php
namespace app\controllers;

use core\http\Request;
use core\http\Response;
use app\services\OrderService;

class CheckoutController 
{
    // 1. Dual Dependency Injection Strategy: Application controllers use Method Injection!
    // The Router injects the OrderService exactly when this specific route is hit, keeping the class lightweight.
    public function process(Request $request, OrderService $orderService): Response 
    {
        // 2. Extract the clean data from the Request object
        $productId = $request->getPostInt('product_id');
        $quantity = $request->getPostInt('quantity');

        try {
            // 3. Delegate the actual work to the Service!
            $success = $orderService->placeOrder($productId, $quantity);

            // 4. Return the appropriate Response
            return new Response("Order Placed Successfully!");

        } catch (\Exception $e) {
            // 5. Handle failures gracefully
            return new Response("Error: " . $e->getMessage(), 400);
        }
    }
}
```

#### Analyzing the Principles
This class perfectly adheres to the **Single Responsibility Principle (SRP)**. Its *only* responsibility is translating HTTP traffic into PHP method calls. 

Notice how small the `process()` method is. If the logic for calculating tax on an order changes tomorrow, we do not need to touch the `CheckoutController`. The Controller doesn't care how an order is placed, it only cares *that* an order is placed. 

By pushing the complex logic down into the `OrderService` (which we will cover next), we keep our HTTP layer incredibly thin and easy to read.

#### Common Questions and Answers

> **Q: Imagine Sandbox Corp decides to launch a mobile app next year that communicates with our server via an API, not a web browser. If we had written all of our tax calculation and database logic directly inside the `CheckoutController`, why would building this new API be incredibly painful?**
> 
> **A:** Because we would have to rewrite and duplicate every single business rule (like calculating taxes) for the API! By keeping the Controller as just a "Traffic Cop" and pushing the logic into a Service, our new `ApiController` can simply inject the exact same `OrderService` and reuse 100% of the business logic.

> **Q: In our example, the Controller asks the `OrderService` to do the work. If the `OrderService` discovers that a module is out of stock, should the `OrderService` be the one to generate the `new Response("Out of stock", 400)` object?**
> 
> **A:** No! This is a critical distinction in Separation of Concerns. A `Response` is an HTTP concept (it belongs to the web layer). The `OrderService` is pure business logic; it doesn't know what the internet is. If it's out of stock, the Service should throw an `OutOfStockException` (or return a boolean/result object). The *Controller* catches that exception and builds the HTTP `Response`. This keeps the Service layer perfectly isolated from the web layer!

---

### Chapter 5.2: The Service Layer - Where the Work Happens

#### Subject & Intent: The Brain of the Application (The Evolutionary Starting Point)
If the Controller is the Traffic Cop, the **Service** is the highly-trained mechanic inside the garage. 

*A Historical Note on Architecture:* When the initial framework was first built, the Service Layer was where 100% of your business rules lived. If a developer asked, *"How does Sandbox Corp calculate tax?"*, they would open the relevant Service class. This pattern is known as the **Transaction Script**. 

We teach this pattern here because it is crucial to understand *how* logic is isolated from Controllers and Databases. However, as you will see in **Module 10 (Domain-Driven Design)**, this approach eventually breaks down as an application grows into an enterprise platform. The code examples below represent the *starting point* of our framework's evolution, not its final destination.

#### The Theory: Services Orchestrate
A Service's job is **Orchestration**. It rarely does everything by itself. Instead, it coordinates other specialized classes.

For example, when a user places an order, the `OrderService` must:
1. Ask the `InventoryRepository` if the module is in stock.
2. Ask the `PricingService` to calculate the tax and total.
3. Ask the `PaymentGateway` to charge the credit card.
4. Ask the `OrderRepository` to save the final receipt to the database.
5. Ask the `MailerService` to email the customer.

Because the Service is doing all this heavy lifting, it is *heavily* dependent on Dependency Injection.

#### File Walkthrough: The `OrderService`
Let's look at what the `placeOrder` method actually looks like inside `magma/services/OrderService.php`:

```php
namespace magma\services;

use magma\repositories\InventoryRepository;
use magma\repositories\OrderRepository;
use core\interfaces\PaymentGatewayInterface;
use core\interfaces\MailerInterface;
use magma\exceptions\OutOfStockException;

class OrderService 
{
    // Look at all these dependencies! 
    // The DI Container (Module 3) wires all of these up automatically.
    public function __construct(
        private InventoryRepository $inventoryRepo,
        private OrderRepository $orderRepo,
        private PaymentGatewayInterface $paymentGateway,
        private MailerInterface $mailer
    ) {}

    public function placeOrder(int $productId, int $quantity, string $creditCardToken): bool 
    {
        // 1. Check Inventory (Business Logic!)
        if (!$this->inventoryRepo->hasStock($productId, $quantity)) {
            // Notice: We throw an exception, NOT an HTTP Response!
            throw new OutOfStockException("Module is sold out.");
        }

        // 2. Charge the card
        $paymentSuccess = $this->paymentGateway->charge($creditCardToken, 50.00);
        
        if (!$paymentSuccess) {
            return false;
        }

        // 3. Save to Database
        $orderId = $this->orderRepo->createOrder($productId, $quantity);

        // 4. Send Email
        $this->mailer->sendReceipt($orderId);

        return true;
    }
}
```

#### Analyzing the Principles: The Power of Isolation
Look closely at that file. Notice what is missing?

* **No `$_POST` or `Request` objects.** The Service doesn't know it's a web app. It just takes standard PHP variables (`int $productId`).
* **No `Response` objects.** It returns a boolean (`true`/`false`) or throws an Exception.
* **No `SQL` strings.** It asks the Repositories to handle the database saving.
* **No `new` keywords.** It uses interfaces (`MailerInterface`, `PaymentGatewayInterface`) so we can swap providers easily.

Because the `OrderService` is perfectly isolated, we can write a CLI script (a terminal command) that uses this exact same service to create test orders, and it works perfectly!

This isolation makes **Unit Testing** a dream. We can test the `placeOrder` logic by passing a "fake" `PaymentGateway` into the constructor that always returns `true`. We can instantly verify that the email logic triggers correctly without actually charging a real credit card.

#### Common Questions and Answers

> **Q: Imagine we wrote a command-line script to automatically generate 10 test orders for our developers. Why is it structurally impossible for that CLI script to use the `CheckoutController` to create the orders, and why does it *have* to use the `OrderService`?**
> 
> **A:** The Controller only deals with HTTP concepts; it requires an `HttpRequest` object and returns an `HttpResponse` object. A command-line script doesn't have an HTTP request! By using the `OrderService` directly, the CLI script bypasses the web layer entirely and utilizes the pure PHP logic.

> **Q: The `OrderService` asks the `MailerInterface` to send an email. Why didn't we just write the `mail()` logic directly inside the `OrderService`?**
> 
> **A:** It violates the Single Responsibility Principle (SRP). The `OrderService` is responsible for orchestrating the order flow, not for dealing with SMTP protocols, email headers, and connection timeouts. By delegating to a `MailerInterface`, the service remains clean and focused.

> **Q: Would `OrderService` eventually become monolithic and unwieldy? Eventually, will this class have to be extrapolated into multiple services that handle an order?**
> 
> **A:** Absolutely correct! This is a phenomenon known as the "God Class" anti-pattern. If `OrderService` grows to 2,000 lines because it handles taxes, fraud detection, shipping calculations, and inventory reservations, it violates SRP again. 
> To solve this, enterprise applications extrapolate logic into smaller services (e.g., injecting an `OrderTaxService` and a `FraudDetectionService` into the `OrderService`). Alternatively, they use **Domain Events**—instead of the `OrderService` calling the Mailer directly, it simply broadcasts an event: `"OrderPlaced"`. The Mailer, completely separately, listens for that event and sends the email without the `OrderService` even knowing it exists!

---


### Chapter 5.3: Declarative Auto-Wiring & FormRequests

How do we validate data without cluttering the controller? We use **FormRequests**.

When the Router detects that a controller method requires a `ProductCreateRequest`, it intercepts the pipeline *before* the controller is executed. 

1. The Router instantiates the `ProductCreateRequest`.
2. It executes the declarative validation rules (e.g., `'price' => 'numeric|min:0'`).
3. If validation fails, it automatically throws a `ValidationException`, bouncing the user back with error messages.
4. If it succeeds, the beautifully clean, perfectly validated `ProductCreateRequest` object is injected straight into the Controller's method signature (Method Injection).

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

```php
namespace core\interfaces;

interface OrderRepositoryInterface 
{
    public function findById(int $orderId): ?array;
    public function createOrder(int $productId, int $quantity): int;
}
```

By defining this contract, our `OrderService` knows *what* it can ask for, without caring *how* it gets done.

#### File Walkthrough: The Concrete Implementation
Now, let's look at the actual class that executes the SQL, located in `magma/repositories/PostgresOrderRepository.php`.

Notice how we inject the generic `DatabaseInterface` into the Repository!

```php
namespace magma\repositories;

use core\interfaces\OrderRepositoryInterface;
use core\interfaces\DatabaseInterface;

class PostgresOrderRepository implements OrderRepositoryInterface 
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db) 
    {
        $this->db = $db;
    }

    public function findById(int $orderId): ?array 
    {
        // This is the ONLY place in the app where this SQL exists.
        $sql = "SELECT * FROM orders WHERE id = :id LIMIT 1";
        
        $result = $this->db->fetchOne($sql, ['id' => $orderId]);
        return $result ?: null;
    }

    public function createOrder(int $productId, int $quantity): int 
    {
        // ... implementation
    }
}
```

#### Analyzing the Principles: The Multi-Tenancy Shield
Let's return to the most critical business requirement of the Magma framework: **Multi-Tenancy** (supporting multiple vendors on one platform).

If we share one database table for *all* vendors, the biggest risk is that "Vendor A" logs in and accidentally sees "Vendor B's" orders. This is a catastrophic failure.

If developers are allowed to write SQL in controllers or services, it is almost guaranteed that someone will write `SELECT * FROM orders` and forget to add `WHERE vendor_id = 1`. 

Because we use the Repository Pattern, we can enforce Multi-Tenancy centrally. We can inject the `TenantContext` directly into the Repository, and the Repository can *automatically* append the `vendor_id` to every single query it runs! 

The developer writing the `OrderService` literally cannot make a mistake and query another vendor's data, because the Repository intercepts and scopes the query automatically.

#### Common Questions and Answers

> **Q: Our `OrderService` requires an `OrderRepositoryInterface`. If we want to unit-test the `OrderService` without actually connecting to a real database, how does the Repository Pattern allow us to do that? Do we just write test SQL?**
> 
> **A:** We don't write any SQL to test it at all! Because the Service only requires the `Interface`, we can write a fake class (like an `InMemoryOrderRepository`) that simply returns hardcoded PHP arrays. We inject that fake class into our Service during the test, completely bypassing the database layer. The Service never knows the difference, and our tests run instantly.

> **Q: If a new privacy law passes requiring us to "soft delete" orders (mark them as `deleted = 1` rather than actually dropping the row from the database), how many files in our codebase would we have to modify to ensure that `findById()` no longer returns deleted orders?**
> 
> **A:** Just one: The Repository! Because all SQL is quarantined inside the repository, we update the query there, and every Service and Controller across the entire application instantly respects the new privacy law.

---

### Chapter 6.2: Tenant Context - The Invisible Shield

#### Subject & Intent: The Greatest Risk in SaaS
As we mentioned in Chapter 6.1, the absolute greatest risk when building a Multi-Tenant platform (where many vendors share one database) is **Cross-Tenant Data Leakage**.

If "Sandbox Corp" logs into their dashboard, and due to a coding error, they see "Client B's" orders, you have a massive legal and security breach on your hands. 

If we rely on developers to manually type `WHERE vendor_id = X` in every single repository method they ever write, human error *will* eventually cause a data leak. We need a systematic, invisible shield that protects the data automatically.

#### The Theory: The Context Object
To build this shield, we use a concept called a **Context Object**.

When a user logs into the application, or when we determine which vendor's subdomain is currently being accessed (e.g., `client.sandboxplatform.com`), our early Middleware creates a `TenantContext` object.

This object holds the immutable ID of the current vendor.

```php
namespace core\context;

class TenantContext 
{
    private int $vendorId;

    public function __construct(int $vendorId) 
    {
        $this->vendorId = $vendorId;
    }

    public function getVendorId(): int 
    {
        return $this->vendorId;
    }
}
```

#### File Walkthrough: Injecting the Shield
The magic happens when we combine this `TenantContext` with the Dependency Injection Container and our Repositories.

Instead of passing the vendor ID around manually to every function, we inject the `TenantContext` directly into the Repository's constructor.

```php
namespace magma\repositories;

use core\interfaces\OrderRepositoryInterface;
use core\interfaces\DatabaseInterface;
use core\context\TenantContext;

class PostgresOrderRepository implements OrderRepositoryInterface 
{
    private DatabaseInterface $db;
    private TenantContext $tenant;

    public function __construct(DatabaseInterface $db, TenantContext $tenant) 
    {
        $this->db = $db;
        $this->tenant = $tenant;
    }

    public function getLatestOrders(): array 
    {
        // The developer doesn't have to 'remember' the vendor ID.
        // It is inherently part of the repository's state!
        
        $sql = "SELECT * FROM orders WHERE vendor_id = :vendor_id ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, [
            'vendor_id' => $this->tenant->getVendorId()
        ]);
    }
}
```

#### Analyzing the Principles: Secure by Default
Why is this architecture superior?

1. **Secure by Default:** The `OrderService` simply calls `$this->orderRepo->getLatestOrders()`. The Service does not know about the `vendor_id`. The Repository handles it automatically. The data is fundamentally isolated at the lowest level.
2. **Immutability:** Because the `TenantContext` is injected via the constructor, a malicious or buggy script cannot easily "swap" the tenant ID halfway through execution. The repository is locked to that tenant for the duration of the request.
3. **No Global State:** We didn't use `$_SESSION['vendor_id']` inside the repository. Using the session superglobal inside a repository would make it impossible to use that repository in a background cron job (which doesn't have a session). By injecting a formal `TenantContext` object, our cron job can just manually create a `new TenantContext(1)` and use the exact same secure repository.

#### Common Questions and Answers

> **Q: We write a terminal command that runs every night at midnight to generate a sales report for *all* vendors on the platform. If our Repositories are strictly locked to a single injected `TenantContext`, how would a single script generate reports for multiple different vendors?**
> 
> **A:** The script would write a loop to fetch each tenant. For every iteration of the loop, it creates a *brand new* `TenantContext` object and uses it to build a new repository instance specifically for that tenant. We don't try to use a "master key" or bypass the security; we just sequentially put on the "hat" of each vendor, ensuring the strict data isolation is never broken.

> **Q: Some ORMs use "Global Scopes," where the framework magically intercepts the SQL behind the scenes and adds `WHERE vendor_id = 1` before it hits the database. Based on our philosophy in Module 1, why might we prefer explicitly writing the `WHERE` clause in the Repository over using a framework's magical global scope?**
> 
> **A:** You have to know what is going on behind the scenes to properly code new functions. Relying on "magic" can backfire entirely if you don't actually know where the data you are receiving is coming from, or if you encounter an edge case where you *need* to bypass the magic but can't figure out how. By explicitly injecting the context and writing the `WHERE` clause in the repository, the mechanism is completely transparent, readable, and debuggable.

#### Domain-Based Tenant Resolution (White Labeling)
As a platform grows, tenants may want their own dedicated URLs (e.g., `client.sandboxplatform.com` or entirely custom domains like `www.mybakery.com`). To securely support this "White Label" service without polluting the core logic, Magma utilizes the **Strategy Pattern** at the Middleware layer.

Instead of hardcoding domain logic, the `TenantSecurityMiddleware` injects a `DomainTenantContextProvider`. This provider inspects the incoming HTTP request's host, securely queries a `tenant_domains` mapping table using a read-replica connection, and explicitly binds the corresponding `TenantContext`. The Magma core remains beautifully agnostic—it doesn't care if the tenant is resolved via a domain, an API token, or a user session; the downstream Repositories and Error Handlers simply consume the active `TenantContext` to load the correct themes and isolate data.

---


### Chapter 6.3: The Evolution to CQRS & SERIALIZABLE ACID Compliance

The most profound architectural evolution in the Magma Framework is its advanced approach to persistence. As systems scale, a unified Repository often becomes a bottleneck. Magma evolved to use **CQRS (Command Query Responsibility Segregation)**.

In Magma, read operations and write operations are physically and conceptually segregated:
*   **AbstractQueryRepository:** Used purely for fetching data. It is injected with a **Read-Replica** PDO connection. It returns Data Transfer Objects (DTOs), preventing any accidental writes or lazy-loading side effects in the presentation layer.
*   **AbstractCommandRepository:** Used purely for mutations (Insert, Update, Delete). It is injected with the **Write-Master** database connection.

#### Extreme ACID Compliance (Eliminating Phantom Reads)
Data integrity is the absolute highest priority. PostgreSQL defaults to the `READ COMMITTED` isolation level. While fast, it is vulnerable to phantom reads under extreme concurrency.

Magma's `DatabaseTransactionManager` forces the connection into `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE`. This mathematically guarantees that concurrent transactions behave as if they were executed sequentially, completely eliminating race conditions.

#### Write-Master Redirection
Crucially, when a transaction begins, the `DatabaseTransactionManager` intercepts the read-replica connection and routes *all* active queries during that transaction to the write-master. This avoids replication-lag bugs! If you create a user on the master and immediately query them from a replica inside the same transaction, they might not exist yet. Dynamic routing solves this.

#### The Liskov Substitution Principle (LSP) Firewall
If an abstract base class declares `protected function update(string $table, array $data)`, any concrete subclass trying to implement a domain interface with `public function update(int $id, array $data)` will crash PHP with a signature mismatch.

Magma solves this by strictly segregating internal framework methods (e.g., `executeInsert`) from common domain CRUD terminologies. The base classes act as an **LSP Firewall**, ensuring domain repositories can freely implement `create()`, `update()`, and `delete()` without colliding with the underlying SQL engines.
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

If there is a business rule (e.g., "Is this module considered expensive?"), that rule belongs in the `PricingService` or the `CakeEntity` itself, *never* in the View.

#### File Walkthrough: Passing Data to the View
Let's trace how data actually reaches the View. Remember our Controller from Module 5? Let's look at it again, focusing on the return statement:

```php
namespace magma\controllers;

use core\http\Response;
use core\view\ViewRenderer;
use magma\services\OrderService;

class StorefrontController 
{
    public function __construct(
        private OrderService $orderService,
        private ViewRenderer $view
    ) {}

    public function index(): Response 
    {
        // 1. Ask the Service for the data
        $modules = $this->orderService->getAvailableCakes();

        // 2. Render the View, passing the data as a clean array!
        $html = $this->view->render('storefront/index.html.php', [
            'modules' => $modules,
            'pageTitle' => 'Welcome to Sandbox Corp'
        ]);

        // 3. Return the fully baked HTML inside the HTTP Response
        return new Response($html);
    }
}
```

Notice that the Controller does not echo the HTML! It asks the `ViewRenderer` to compile the HTML string using the provided data, and then it wraps that compiled string in the standard `Response` object.

#### The Template Engine (The Compiler)
What does that `storefront/index.html.php` file actually look like?

In our framework, we use a basic, native PHP templating engine. We intentionally limit what PHP functions can be used inside these template files.

```php
<!-- magma/views/storefront/index.html.php -->
<html>
<head>
    <title><?= htmlspecialchars($pageTitle) ?></title>
</head>
<body>
    <h1>Menu</h1>
    
    <ul>
        <!-- The view only loops and displays. No logic! -->
        <?php foreach ($modules as $module): ?>
            <li>
                <?= htmlspecialchars($module->getName()) ?> - 
                £<?= number_format($module->getPrice(), 2) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
```

#### Analyzing the Principles: The XSS Vulnerability
You might have noticed something critical in the template above: we wrap everything we output in `htmlspecialchars()`.

This is the most critical security rule of the View layer. **Cross-Site Scripting (XSS)** occurs when an attacker types malicious JavaScript into a form (e.g., setting their username to `<script>stealCookies();</script>`). If the View layer blindly outputs that username directly into the HTML using `echo $username;`, the browser will execute the attacker's script!

By wrapping every variable in `htmlspecialchars()`, we neutralize the threat. It converts the literal `<` bracket into the safe HTML entity `&lt;`, rendering it harmless text on the screen.

#### Common Questions and Answers

> **Q: Imagine Sandbox Corp hires a junior Front-End Web Designer who only knows HTML and CSS, and does not know PHP. How does enforcing the "Dumb View" architecture make their job significantly safer and easier compared to the "Spaghetti Code" era?**
> 
> **A:** The data received is sanitized and empty of any logic that might harm or break the app. It provides simply the necessary information, ready for the view. The designer can just write HTML without worrying about accidentally deleting a database table or breaking complex backend routines.

> **Q: The business wants to display a red "SALE!" badge next to any module that costs less than £10. A developer wants to write `<?php if ($module->getPrice() < 10) { echo "<span class='sale'>SALE!</span>"; } ?>` directly into the `index.html.php` View file. Why does this violate the "Dumb View" philosophy, and where *should* that "less than 10" logic actually live?**
> 
> **A:** Business logic should not be in the View. However, it shouldn't be in the Controller either! (Controllers are just Traffic Cops). That logic belongs deep in the **Service Layer** or the **Domain Model**. For instance, the `Module` object itself should have a method `$module->isOnSale()`. The View simply asks `if ($module->isOnSale())`—keeping the actual calculation ("is it less than £10?") safely hidden in the backend.

---


### Chapter 7.2: Multi-Directory Fallback & Resolution Caching

Large SaaS applications store views across multiple directory structures (`views/layouts`, `views/partials`, `modules/Inventory/views`). 

Magma's `TemplateEngine` intelligently falls back across these directories to resolve layouts. However, under heavy load, checking `file_exists()` across multiple directories for every partial creates severe disk I/O bottlenecks. 

To prevent this, the resolution paths are cached in-memory. The engine guarantees that the disk is only queried once per layout or partial per request lifecycle.

### Chapter 7.3: Big-O DOM Interpolation Optimization

When parsing highly nested templates or loops, standard DOM interpolation suffers from O(N*M) Big-O time complexity as the engine redundantly scans child nodes. 

Magma's frontend JavaScript `TemplateEngine` solves this by temporarily detaching nested `[data-loop]` nodes via comment placeholders before evaluating outer directives. This flattens the execution curve to strict O(N) complexity, allowing massive, data-heavy UIs to render instantly.
## Module 8: Error Handling & Logging

### Chapter 8.1: Failing Gracefully

#### Subject & Intent: The White Screen of Death
In default PHP, when a fatal error occurs (like trying to connect to a database that is offline), the application either prints the raw, ugly stack trace to the browser (revealing your directory structure and sometimes passwords to the user!), or it fails silently and returns a completely blank white page ("The White Screen of Death").

Both of these are unacceptable in a professional application. 

In the Magma Framework, we enforce a global **Exception Handler** and absolute **Exception Boundaries** at the infrastructure level. 
For example, our base `AbstractQueryRepository` and `AbstractCommandRepository` explicitly catch `PDOException` natively at the execution layer, translating them into `DatabaseException`. Similarly, our storage adapters (`LocalStorageService`, `S3StorageService`) throw `StorageException` upon network drops or disk permission failures, preventing silent boolean data loss. This mathematically guarantees raw database credentials, SQL syntax errors, or cloud storage secrets never bleed into the domain or HTTP application layers.

Furthermore, we enforce **Pre-Kernel Boot Safety Nets**. If a fatal error occurs in `www/index.php` or `bin/worker.php` *before* the application container and `ErrorHandler` are fully registered, an outermost `try/catch` wrapper intercepts the failure to emit a clean 500 status code, protecting the system from 0-day `.env` or container misconfiguration leaks.

#### The Theory: Catching Everything
Instead of sprinkling `try/catch` blocks randomly throughout every single file, we register a global listener at the very start of the application lifecycle (inside `bootstrap.php`).

If an Exception is thrown anywhere in the application—whether deep inside a Repository or high up in a Middleware—and it is *not* caught by the local code, it bubbles all the way up to our global Error Handler.

#### File Walkthrough: The Error Handler
Here is a simplified version of our core Error Handler:

```php
namespace core\error;

use Throwable;
use core\http\Response;

class ErrorHandler 
{
    public function __construct(private LoggerInterface $logger) {}

    public function handle(Throwable $e): void 
    {
        // 1. ALWAYS log the exact technical error securely.
        $this->logger->error($e->getMessage() . "\n" . $e->getTraceAsString());

        // 2. Decide what to show the user based on the Environment
        if (ENVIRONMENT === 'development') {
            // In Dev: Show the ugly, detailed stack trace so we can fix it!
            $response = new Response("<h1>Fatal Error</h1><pre>" . $e->getTraceAsString() . "</pre>", 500);
        } else {
            // In Production: NEVER show technical details. Show a polite apology.
            $response = new Response("<h1>Whoops!</h1><p>Something went wrong on our end. We've been notified.</p>", 500);
        }

        // 3. Send the final response
        $response->send();
        exit(); // Immediately halt all further execution
    }
}
```

#### Analyzing the Principles: Security and UX
This architecture perfectly separates concerns based on the environment. 

By tying the output to the `ENVIRONMENT` constant (which we defined way back in `index.php`), we ensure that an attacker can never purposefully crash the site just to read the stack trace to find vulnerabilities. 

Simultaneously, we ensure that the development team gets notified immediately via the `LoggerInterface`. Because we are injecting a `LoggerInterface`, we can swap where those logs go! In development, it might write to a text file. In production, it might send a Slack message or an alert to a service like Sentry or Datadog.

#### Common Questions and Answers

> **Q: If we didn't have a global Error Handler configured in `bootstrap.php`, and a developer forgot to write a `try/catch` block around a database query that failed, what are the *two* completely different ways the application might behave depending on the server's default configuration, and why are *both* terrible for a production site?**
> 
> **A:** It could show a full stack trace which looks ugly and presents a massive security risk (exposing file paths and potentially credentials), or it could show the White Screen of Death that tells the user absolutely nothing, providing a terrible User Experience (UX).

---


## Module 9: The Final Polish - DTOs (Data Transfer Objects)

### Chapter 9.1: Crossing the Boundary Safely

#### Subject & Intent: The Strict Typing Friction
Let's return to a problem we discussed in Pillar 4 (Strict Typing). 

Our `OrderService` is incredibly strict:
`public function placeOrder(int $productId, int $quantity): bool`

But the web browser submits forms using text. The `Request` object catches this text. If we just pull the text out and pass it to the service, the application crashes because `"5"` (string) is not `5` (integer).

We need a translator.

#### The Theory: The DTO
A Data Transfer Object (DTO) is a simple, dumb class. Its only job is to take the messy, untyped data from the outside world, validate it, and convert it into a strict, strongly-typed object that our core business logic can safely consume.

#### File Walkthrough: The Bouncer
Let's look at `magma/dto/OrderRequestDTO.php`:

```php
namespace magma\dto;

class OrderRequestDTO 
{
    public readonly int $productId;
    public readonly int $quantity;

    // The constructor forces strict typing immediately!
    public function __construct(int $productId, int $quantity) 
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Quantity must be at least 1.");
        }

        $this->productId = $productId;
        $this->quantity = $quantity;
    }

    // A static factory method to build the DTO from the messy Request
    public static function fromRequest(\core\http\Request $request): self 
    {
        return new self(
            $request->getPostInt('product_id'),
            $request->getPostInt('quantity')
        );
    }
}
```

Now, look at how beautiful and safe our Controller becomes:

```php
public function process(Request $request): Response 
{
    try {
        // 1. The DTO translates the messy request into a strict object
        $orderData = OrderRequestDTO::fromRequest($request);

        // 2. The Service now only accepts the strict DTO!
        $this->orderService->placeOrder($orderData);

        return new Response("Success!");
    } catch (\InvalidArgumentException $e) {
        // The DTO validation failed! (e.g. quantity was 0)
        return new Response($e->getMessage(), 400);
    }
}
```

By using DTOs, our `OrderService` never has to worry about receiving a string when it expected an integer. The DTO acts as the bouncer at the club, ensuring only properly dressed data gets through the door.

#### Common Questions and Answers

> **Q: Our `OrderRequestDTO` has a validation check: `if ($quantity <= 0) throw Exception;`. Why is it architecturally better to put this specific validation inside the DTO, rather than putting it inside the `OrderService` or the `CheckoutController`?**
> 
> **A:** The DTO's job is to make sure everything matches the required type and format. By keeping "grammar" validation (like ensuring a quantity is a positive number, or an email looks like an email) inside the DTO, the Service can focus purely on cleaner *business logic* (like checking if we actually have enough flour in stock to fulfill that quantity).

---


### Chapter 9.2: Engine-Enforced Immutability

If a developer accidentally mutates a DTO mid-flight (e.g., `$dto->price = 0;`), it can introduce catastrophic, hard-to-trace bugs.

Magma eliminates this entirely by utilizing PHP 8.2's native `readonly class` modifiers for all DTOs.

Once a DTO is instantiated, its properties are locked down perfectly at the engine layer. Any attempt to modify a property will throw a fatal engine error. This prevents rogue scripts or dynamic property injections from mutating state mid-flight, eliminating an entire class of side-effect bugs.
## Module 10: The Evolution - Domain-Driven Design (DDD)

### Chapter 10.1: Transaction Scripts vs. Rich Domain Models

#### Subject & Intent: The Breaking Point of Services
In Module 5, we introduced the **Service Layer** and the **Transaction Script Pattern**. We built an `OrderService` that was incredibly smart: it pulled primitive data from the database, performed all calculations, and saved data back. 

The data itself (the modules, the orders) were just "dumb" arrays or basic objects without any methods. They were purely structural.

While Transaction Scripts are excellent for medium-sized applications, as Magma Framework scaled into an enterprise platform, we hit a breaking point. The Services became bloated "God Classes" because they held *all* the rules. We needed to evolve the architecture.

To solve this, our framework migrated to the current standard: **Domain-Driven Design (DDD)** and the creation of **Rich Domain Models**. This is how the Magma framework operates today.

#### The Theory: Behavior Belongs to the Data
In a Rich Domain Model, the objects that represent your business (Entities) are no longer "dumb." They contain the business logic that directly pertains to them.

**The Transaction Script Way (Dumb Data, Smart Service):**
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

Many architectures corrupt their domain models by passing HTTP-specific objects (like a `Request` or a framework-specific DTO) directly into them. 

Magma enforces **100% Pure Domain Entities**. 

Entities are completely agnostic of the application layer. They only accept raw scalars (strings, integers, booleans) or other pure domain Value Objects in their constructors. A `Product` entity has no idea that it lives on a web server, that it is being saved to PostgreSQL, or that an HTTP request triggered its creation.
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
To guarantee that these background workers never crash your infrastructure, Magma implements **Self-Healing Workers**. The `QueueWorkerDaemon` wraps its Redis polling in a `try/catch` block. If the Redis server drops offline, the daemon doesn't fatally crash; it simply logs a critical alert to the PSR-3 `NativeLogger`, sleeps for 5 seconds, and cleanly retries. 

Additionally, because background workers run continuously within a single PHP process, they share the exact same Dependency Injection `Container`. This presents a massive data-leakage risk in multi-tenant SaaS architectures if "Singleton" state bleeds from Job A into Job B. To prevent this, Magma explicitly calls `$container->flushInstances()` at the beginning of *every single loop iteration*, ensuring every job executes in a completely pristine environment.

---


### Chapter 12.2: The Transactional Outbox Pattern

#### The Dual-Write Problem
Synchronous background tasks kill web performance. The standard solution is to dispatch to a message queue. However, this introduces the **Dual-Write Problem**: What happens if the database transaction commits successfully, but the network connection to Redis fails? The system is now in an inconsistent state.

Magma completely solves this using the **Transactional Outbox Pattern**.

Instead of dispatching directly to a queue, the `OutboxJobRepository` records domain events *atomically* within the exact same database transaction that created the entity. If the transaction commits, the job is guaranteed to be in the database. If it rolls back, the job vanishes. Absolute consistency is guaranteed.

#### FOR UPDATE SKIP LOCKED
A continuous background daemon polls the outbox table to execute the jobs. If you run multiple parallel workers, they will race each other to grab the same job.

Magma relies on PostgreSQL's native `FOR UPDATE SKIP LOCKED` locking primitive. When a worker selects a job, it locks the row. If a second worker queries the table at the exact same millisecond, the database seamlessly *skips* the locked row and hands it the next available job. This guarantees exactly-once delivery with zero lock-contention CPU churn.
## Module 13: End of Cycle Considerations - Automated Testing

### Chapter 13.1: Reaping the Rewards of Architecture

#### Subject & Intent: Testing is a Byproduct of Design
We placed testing at the very end of this textbook because true, robust automated testing is only possible *after* you have built a clean architecture. 

If you write "Spaghetti Code" where controllers contain `new SmtpMailer()` or direct `$_POST` references, unit testing is nearly impossible because you cannot isolate the code from the real world. 

Because we built the Magma framework using strict **Dependency Injection (Module 3)** and **Encapsulated Requests (Module 4)**, testing becomes trivial. 

#### The Theory: Mocks and Fakes
If you want to test the `OrderService`, you do not need a database. You simply write a test script that injects a fake `InMemoryOrderRepository` into the service. You can instantly verify the logic is flawless without ever booting up PostgreSQL. This is the ultimate validation of our architectural choices!


## Module 14: Frontend Architecture: Deep Freeze & CSS Layers

### Chapter 14.1: Deeply Immutable Reactive State Store

The client-side Vanilla ES6 architecture is as robust as the backend. Rather than relying on heavy frameworks like React or Vue, Magma implements a proprietary, lightweight `ObservableStore.js`.

It employs a recursive `_deepFreeze()` algorithm that physically locks deeply nested state objects from rogue frontend mutations. Developers must dispatch explicit actions to mutate state, enforcing strict unidirectional data flow.

### Chapter 14.2: Defensive Garbage Collection

In dynamic single-page applications, DOM elements are frequently created and destroyed. Standard event listeners create "zombie" memory leaks.

Magma's global event delegators utilize defensive `isConnected` checks to gracefully unbind themselves if their target component is ripped from the DOM dynamically, ensuring perfect garbage collection.

### Chapter 14.3: CSS Cascade Layers

Specificity wars destroy maintainability. Magma enforces native CSS Cascade Layers (`@layer reset, tokens, components, utilities, states;`). This permanently structures CSS precedence regardless of file inclusion order.

## Module 15: Security & Big-O Analytics

### Chapter 15.1: Static AST Boundary Auditing

Human code reviews are fallible. A developer might accidentally use a global `$_POST` variable inside a deeply nested Domain Service.

Magma provides a powerful static analysis linter (`bin/audit_schema.php`) that parses the **Abstract Syntax Tree (AST)** of the codebase. It actively verifies that:
1. Multi-tenant foreign keys are correctly indexed.
2. Direct superglobal usage is statically prohibited inside business services.

If boundaries are breached, the linter fails the CI/CD pipeline.

### Chapter 15.2: Constant-Time B-Tree Keyset Pagination

Standard SQL pagination (`LIMIT 100 OFFSET 10000`) degrades linearly in performance (O(N)). 

Magma utilizes **Keyset Seeking** (`WHERE id > :cursor_last_id`). By leveraging B-Tree indexes, the database jumps instantly to the correct row, delivering instantaneous O(1) performance regardless of table size.

### Chapter 15.3: Memory-Streaming Generators

Repositories returning large collections do not load the resulting array into memory. Instead, Magma streams the records directly from the database driver using PHP generators (`yield`). This keeps RAM consumption entirely flat, preventing OOM crashes during heavy analytical workloads.
## Module 16: The Lava Hardening Phase - Enterprise Quality Control

### Chapter 16.1: The Eradication of Legacy Facades and "Magic"
Enterprise software accumulates technical debt primarily through backward compatibility. To achieve true architectural purity, Magma underwent a "Lava" hardening phase where all legacy facades were systematically eliminated. 

The framework entirely abandoned primitive tuple arrays in the Routing engine in favor of strongly-typed `RouteDefinition` Value Objects. Backward-compatible interfaces and legacy middleware were completely purged. The result is a framework that forces modern, object-oriented contracts at every layer.

### Chapter 16.2: Mathematical Type Safety via PHPStan Level 9
Type safety is the ultimate defense against runtime defects. The Magma core implements **PHPStan Level 9**, the most stringent static analysis level available. 

By enforcing strict scalar types (`declare(strict_types=1)`), demanding explicit array shapes, and mathematically proving the impossibility of `mixed` types passing through boundaries, the framework eliminates silent type-coercion bugs entirely. Any code that cannot be statically proven to be safe will instantly fail the CI/CD pipeline.

### Chapter 16.3: Advanced Cryptography and Boundary Enforcement
Security is not bolted on; it is embedded into the core. 
1. **Argon2id Hashing:** We replaced legacy Bcrypt with Argon2id—the most robust, memory-hard hashing algorithm available, defending against GPU-based brute-force attacks.
2. **Transparent Rehashing:** The `AuthenticationService` actively listens for legacy hashes during successful logins and transparently upgrades them to Argon2id via the `UserCommandRepository`.
3. **Strict Headers:** The injection of `Permissions-Policy` disables intrusive browser features (camera, microphone, geolocation) by default, dramatically reducing the application's attack surface.
