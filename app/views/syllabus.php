<?php
/**
 * Title: Syllabus View
 * Purpose: Renders the textbook / syllabus page.
 */
$pageTitle = $data['title'] ?? 'Architectural Syllabus | Magma Framework';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="welcome-page">
    <div class="welcome-container">
<div class="welcome-hero syllabus-hero">
    <div class="container">
        <h1 class="welcome-hero__title">Architectural Syllabus</h1>
        <p class="welcome-hero__subtitle syllabus-hero__subtitle">A Masterclass in Enterprise Software Architecture. This textbook is a comprehensive, iteratively compiled record of our deep dives into the explicit, vanilla PHP/JS architecture that powers Magma.</p>
    </div>
</div>
<main class="syllabus-page">
    <div class="container syllabus-layout">
        <div class="syllabus-sidebar"><details class="syllabus-toc" open><summary class="toc-summary"><h3>Chapters</h3></summary><ul><li><a href="#module-1-introduction-philosophy"><span class="toc-num">1</span> <span class="toc-title">Introduction & Philosophy</span></a></li><li><a href="#module-2-the-request-lifecycle-front-controller"><span class="toc-num">2</span> <span class="toc-title">The Request Lifecycle & Front Controller</span></a></li><li><a href="#module-3-the-dependency-injection-container-the-core"><span class="toc-num">3</span> <span class="toc-title">The Dependency Injection Container (The Core)</span></a></li><li><a href="#module-4-routing-the-http-request"><span class="toc-num">4</span> <span class="toc-title">Routing & The HTTP Request</span></a></li><li><a href="#module-5-controllers-services-the-business-logic"><span class="toc-num">5</span> <span class="toc-title">Controllers & Services (The Business Logic)</span></a></li><li><a href="#module-6-data-persistence-multi-tenancy"><span class="toc-num">6</span> <span class="toc-title">Data Persistence & Multi-Tenancy</span></a></li><li><a href="#module-7-views-and-the-template-engine"><span class="toc-num">7</span> <span class="toc-title">Views and the Template Engine</span></a></li><li><a href="#module-8-error-handling-logging"><span class="toc-num">8</span> <span class="toc-title">Error Handling & Logging</span></a></li><li><a href="#module-9-the-final-polish-dtos-data-transfer-objects"><span class="toc-num">9</span> <span class="toc-title">The Final Polish - DTOs (Data Transfer Objects)</span></a></li><li><a href="#module-10-the-evolution-domain-driven-design-ddd"><span class="toc-num">10</span> <span class="toc-title">The Evolution - Domain-Driven Design (DDD)</span></a></li><li><a href="#module-11-decoupling-with-event-driven-architecture"><span class="toc-num">11</span> <span class="toc-title">Decoupling with Event-Driven Architecture</span></a></li><li><a href="#module-12-asynchronous-background-workers"><span class="toc-num">12</span> <span class="toc-title">Asynchronous Background Workers</span></a></li><li><a href="#module-13-end-of-cycle-considerations-automated-testing"><span class="toc-num">13</span> <span class="toc-title">End of Cycle Considerations - Automated Testing</span></a></li><li><a href="#module-14-frontend-architecture-deep-freeze-css-layers"><span class="toc-num">14</span> <span class="toc-title">Frontend Architecture: Deep Freeze & CSS Layers</span></a></li><li><a href="#module-15-security-big-o-analytics"><span class="toc-num">15</span> <span class="toc-title">Security & Big-O Analytics</span></a></li></ul></details></div>
        <div class="syllabus-content">
            <h1 class="syllabus-title">Magma Framework: The Masterclass Textbook</h1>
<div class="chapter-module" id="module-1-introduction-philosophy"><h2 class="chapter-title">Module 1: Introduction & Philosophy</h2>
<h3>Chapter 1.1: The Domain Context & The Platform Vision</h3>
<h4>Subject & Intent: Understanding the "Why" Before the "How"</h4>
<p>
In software engineering, code is simply a tool used to solve a specific human problem. Before we write a single line of PHP, we must intimately understand the <strong>Domain</strong>—the business environment in which our software will operate. If we build an elegant architecture that solves the wrong business problem, we have failed as engineers.
</p>
<p>
In our case, the domain is "TSP," a generic software platform located in the cloud. They specialize in various domain entities, specifically distinguishing themselves with a "no magic" approach.
</p>
<p>
This gives us immediate clues about our domain entities and data structures:
</p>
<ul class="syllabus-list">
<li><strong>Products:</strong> We aren't selling generic widgets. We have specific attributes like ingredients, allergens, and preparation lead times.</li>
<li><strong>Inventory/Stock:</strong> Baked goods are perishable and often made-to-order. Stock isnt just a number in a warehouse; it's tied to production capacity and calendar dates.</li>
<li><strong>Customers & Orders:</strong> We are dealing with local logistics, specific pickup/delivery windows, and bespoke customer requests.</li>
</ul>
<p>
However, the defining characteristic of our architecture is the <strong>Platform Vision</strong>.
</p>
<p>
While Sandbox Corp is our *first* client (our "Tenant"), our intent is to design this system from the ground up as a platform capable of supporting *multiple* distinct vendors in the future. This concept is known as <strong>Multi-Tenancy</strong>.
</p>
<h4>Analyzing the Principles: Designing for Multi-Tenancy from Day One</h4>
<p>
It is a common trap in software development to hardcode business logic for a single client, assuming you can "generalize it later." Generalizing a massive, tightly-coupled codebase later is incredibly expensive and error-prone. As we established, hardcoding rules for one vendor means they will inevitably be incorrectly applied to future vendors.
</p>
<p>
Instead, we are adopting a platform-first mindset.
</p>
<blockquote class="syllabus-quote">
<strong>Historical Context:</strong> In the early days of SaaS (Software as a Service), companies often stood up a completely separate database and codebase for every new client (Single-Tenant). While secure, this became a nightmare to maintain and deploy. The industry shifted toward Multi-Tenant architectures, where a single instance of the application and database serves multiple clients, using a <code>tenant_id</code> to strictly separate data.<br>
</blockquote>
<p>
To achieve this in the Magma Framework framework, we apply the following principles:
</p>
<ol class="syllabus-list">
<li><strong>Strict Data Isolation at the Repository Layer:</strong> By establishing the Repository Pattern early, we ensure that every database query can eventually be scoped to a specific <code>vendor_id</code>. A controller will never accidentally query <code>SELECT * FROM orders</code>; it will always ask the repository for <code>Orders for Vendor X</code>. This mitigates the massive risk of cross-tenant data leakage—the most critical danger in a shared database environment.</li>
<li><strong>Agnostic Core Domain:</strong> The core application doesn't care that Sandbox Corp makes modules. It only understands abstract concepts: <code>Vendors</code>, <code>Products</code>, <code>Orders</code>, and <code>Inventory</code>. The specifics are data, not code.</li>
<li><strong>Configuration over Hardcoding:</strong> If a vendor has a specific rule, we abstract this into a configurable business rule associated with the vendor's profile, rather than burying it in <code>if/else</code> statements within our services.</li>
</ol>
<p>
<strong>Code Example: Hardcoding vs. Abstraction</strong>
</p>
<p>
Imagine Sandbox Corp does not allow orders to be placed on Sundays.
</p>
<p>
*The Wrong Way (Hardcoded Logic):*
</p>
<pre><code>class OrderService 
{
    public function placeOrder(OrderDTO $order): bool 
    {
        // BAD: Hardcoding a specific tenant&#039;s rule into the core platform!
        $dayOfWeek = date(&#039;l&#039;, strtotime($order-&gt;deliveryDate));
        if ($dayOfWeek === &#039;Sunday&#039;) {
            throw new Exception(&quot;Sandbox Corp is closed on Sundays.&quot;);
        }
        
        // ... proceed with order
    }
}
</code></pre>
<p>
*The Right Way (Configurable Business Rule):*
</p>
<pre><code>class OrderService 
{
    // We inject the specific Vendor configuration into the service
    public function placeOrder(OrderDTO $order, VendorProfile $vendor): bool 
    {
        // GOOD: The core logic asks the Vendor&#039;s configuration if the day is valid.
        // The fact that it&#039;s Sunday is now purely data, not code.
        $dayOfWeek = date(&#039;l&#039;, strtotime($order-&gt;deliveryDate));
        
        if (!in_array($dayOfWeek, $vendor-&gt;getOperatingDays())) {
            throw new Exception(&quot;This vendor does not operate on &quot; . $dayOfWeek);
        }
        
        // ... proceed with order
    }
}
</code></pre>
<h4>The "Why" & Framework Comparison</h4>
<p>
Why are we taking this approach explicitly, rather than relying on a framework to do it for us?
</p>
<p>
Many popular frameworks (like Laravel) offer multi-tenancy packages. These packages often employ "magic" behind the scenes: they might automatically intercept your database queries and inject a <code>WHERE tenant_id = X</code> clause without you seeing it.
</p>
<p>
While convenient, this <strong>hidden complexity is dangerous for learning</strong>. If a developer doesn't understand *how* the query is being scoped, they cannot debug it when it breaks or optimize it when it scales. They become reliant on the "magic." Furthermore, as we discussed, explicit passing of context leaves far less room for error.
</p>
<p>
In the Magma framework, we reject this magic. Our multi-tenancy preparation is explicit. When we load a tenant's context, we inject it directly into our services. The developer can trace the exact flow of execution from the HTTP request down to the SQL statement, fostering a deep, unbreakable understanding of the system's architecture.
</p>
<hr>
<h3>Chapter 1.2: The Engineering Philosophy: Unmasking the "Magic"</h3>
<h4>Subject & Intent: The Cost of Convenience</h4>
<p>
If you want to build a web application today, you have dozens of phenomenal frameworks at your disposal—Laravel and Symfony in PHP, Ruby on Rails, Django in Python, or Next.js in JavaScript. These frameworks are incredibly powerful and power a massive portion of the internet.
</p>
<p>
However, they achieve their incredible developer velocity by employing what engineers affectionately (and sometimes disparagingly) call <strong>"Magic."</strong>
</p>
<p>
Magic in software engineering refers to abstractions where the framework handles complex logic behind the scenes, without the developer needing to explicitly write the code or even understand how it works.
</p>
<p>
While magic allows junior developers to ship features quickly, it becomes a severe liability when:
</p>
<ol class="syllabus-list">
<li><strong>Things break:</strong> If you don't know how a query is constructed, you can't debug the stack trace when it fails.</li>
<li><strong>Performance bottlenecks emerge:</strong> If the framework is eagerly loading data behind the scenes, you can't easily optimize it.</li>
<li><strong>You are trying to *learn* architecture:</strong> A framework teaches you how to use *that specific framework*. It does not necessarily teach you underlying software engineering principles.</li>
</ol>
<p>
Our philosophy for Magma Framework is to entirely strip away the magic. We want you to see the plumbing.
</p>
<h4>Analyzing the Principles: The Facade Anti-Pattern</h4>
<p>
Let's look at a specific principle: <strong>Dependency Inversion</strong> (the 'D' in SOLID). This principle states that high-level modules should not depend on low-level modules; both should depend on abstractions (interfaces).
</p>
<blockquote class="syllabus-quote">
<strong class="alert-tag">Note:</strong><br>
<strong>Professor's Definitions:</strong><br>
*   <strong>Dependency:</strong> An object that another object relies on to do its job. If your <code>ProductService</code> needs to save to a database, the <code>DatabaseConnection</code> is a dependency.<br>
*   <strong>Abstraction (Interface):</strong> A contract that defines *what* a class can do, without defining *how* it does it. Instead of depending on a specific <code>MySQLDatabase</code>, you depend on a generic <code>DatabaseInterface</code>.<br>
*   <strong>Static Method:</strong> A method that belongs to the class itself, not to a specific object instance. You can call it without using the <code>new</code> keyword (e.g., <code>Cache::get()</code>). Because it belongs to the class globally, it acts essentially like a global variable, which is dangerous for testability.<br>
</blockquote>
<p>
Modern frameworks often violate or obscure the Dependency Inversion principle for the sake of developer ergonomics.
</p>
<blockquote class="syllabus-quote">
<strong>Historical Context:</strong> The popularization of "Magic" largely began with Ruby on Rails in the mid-2000s, which championed "Convention over Configuration." It assumed that if you named your database table <code>users</code> and your class <code>User</code>, it would dynamically wire everything together. Laravel brought this philosophy to PHP, heavily utilizing a pattern called <strong>Facades</strong>.<br>
</blockquote>
<p>
A Facade in Laravel allows you to call non-static methods statically. For example, to get data from the cache, you might write:
</p>
<pre><code>// The &quot;Magic&quot; Framework Way
$value = Cache::get(&#039;key&#039;); 
</code></pre>
<p>
This looks clean! But what is <code>Cache</code>? It's a static proxy. Behind the scenes, the framework is doing gymnastics: reaching into a global container, finding the bound cache instance, and executing the method.
</p>
<p>
<strong>Why is this problematic for learning?</strong>
</p>
<ol class="syllabus-list">
<li><strong>Hidden Dependencies:</strong> As we discussed, when dependencies are scattered throughout the method bodies as static calls, you have no "manifest" of what the class needs. If I look at your class constructor, I have no idea that your class requires a Cache system to function. It makes refactoring incredibly dangerous because it's easy to miss a hidden dependency.</li>
<li><strong>Global State:</strong> Facades essentially act as global variables.</li>
<li><strong>Testing Nightmare:</strong> Mocking static methods for unit tests requires complex reflection or specialized testing libraries (like Mockery), rather than simply passing a fake object into a constructor.</li>
</ol>
<h4>The "Why" & Framework Comparison: The Explicit Alternative</h4>
<p>
In the Magma Framework architecture, we demand <strong>Explicit Dependencies</strong>. If your class needs a cache, you must ask for it in the constructor.
</p>
<pre><code>// The Explicit &quot;Magma&quot; Way
class ProductService 
{
    private CacheInterface $cache;

    // We explicitly demand our dependency!
    public function __construct(CacheInterface $cache) 
    {
        $this-&gt;cache = $cache;
    }

    public function getProduct() 
    {
        $value = $this-&gt;cache-&gt;get(&#039;key&#039;);
    }
}
</code></pre>
<p>
<strong>The Danger of the <code>new</code> Keyword (Tight Coupling)</strong>
<br>
You asked earlier why using <code>$mailer = new SmtpMailer();</code> inside a controller is almost as bad as using a Facade.
</p>
<p>
The answer is <strong>Tight Coupling</strong>. If you write <code>new SmtpMailer()</code> directly inside your code, your controller is permanently glued to that exact SMTP class. If tomorrow the business says, "We are switching to the Mailgun API," you have to open the controller and rewrite the code.
</p>
<p>
If instead you injected a <code>MailerInterface</code> into the constructor, you would simply change the configuration in your Dependency Injection Container (which we will cover in Module 3), and the controller *never has to change*. The <code>new</code> keyword prevents your code from being flexible!
</p>
<p>
<strong>The Comparison Summary:</strong>
<br>
Why do we choose explicit injection?
</p>
<ul class="syllabus-list">
<li><strong>Readability:</strong> The constructor acts as an honest contract. Anyone looking at the class instantly knows exactly what external systems it relies on.</li>
<li><strong>Testability:</strong> To test <code>ProductService</code>, we simply instantiate it and pass in a <code>new DummyArrayCache()</code>. No complex mocking frameworks required.</li>
<li><strong>Swappability:</strong> Because we ask for an <code>Interface</code>, we can swap out a Redis cache for a Memcached system without changing a single line of code inside <code>ProductService</code>.</li>
</ul>
<p>
By forcing ourselves to be explicit, we are forced to think about the design of our system, leading to naturally looser coupling and higher cohesion.
</p>
<hr>
<h3>Chapter 1.3: The Four Pillars of Clean Architecture</h3>
<h4>Subject & Intent: The Rules of Engagement</h4>
<p>
If we are stripping away the "magic" of frameworks, what replaces it? The answer is <strong>Discipline</strong>.
</p>
<p>
When you don't have a framework automatically policing where you put your database queries or how you validate your forms, you must rely on strict architectural principles. In the Magma Framework architecture, every single file we write is governed by four core pillars.
</p>
<h4>Analyzing the Principles</h4>
<h5>Pillar 1: The SOLID Principles</h5>
<p>
SOLID is an acronym coined by Robert C. Martin (Uncle Bob) representing five design principles intended to make object-oriented designs more understandable, flexible, and maintainable.
</p>
<p>
While we will see these in action constantly, here is a brief overview tailored to our context:
</p>
<ol class="syllabus-list">
<li><strong>Single Responsibility Principle (SRP):</strong> A class should have one, and only one, reason to change. A <code>VendorRepository</code> handles database queries. It does *not* format HTML.</li>
<li><strong>Open/Closed Principle (OCP):</strong> Software entities should be open for extension but closed for modification. If we add a new Payment Gateway (Stripe vs PayPal), we shouldn't have to rewrite the <code>CheckoutService</code>. We simply create a new class that implements the <code>PaymentGatewayInterface</code>.</li>
<li><strong>Liskov Substitution Principle (LSP):</strong> If you swap a parent class for its child class (or an interface for an implementation), the application shouldn't break or behave unexpectedly.</li>
</ol>
<blockquote class="syllabus-quote">
<strong class="alert-tag">Tip:</strong><br>
<strong>The Magma LSP Analogy:</strong> Imagine you have an <code>ServiceInterface</code> with a <code>execute(Module $module)</code> method. Your <code>StandardService</code> implements this perfectly. If you substitute it with a <code>FastService</code> class, and calling <code>execute()</code> causes the module to instantly explode instead of executing, you have violated LSP. The substitute failed to honor the fundamental contract of the parent!<br>
</blockquote>
<ol class="syllabus-list">
<li><strong>Interface Segregation Principle (ISP):</strong> Don't force a class to implement methods it doesn't need. Better to have many small, specific interfaces than one massive, general-purpose one.</li>
<li><strong>Dependency Inversion Principle (DIP):</strong> Depend upon abstractions (interfaces), not concretions (specific classes). We covered this heavily in Chapter 1.2.</li>
</ol>
<h5>Pillar 2: Strict Separation of Concerns (SoC)</h5>
<p>
Separation of Concerns is the concept of breaking a computer program into distinct sections, such that each section addresses a separate concern.
</p>
<p>
In our framework, the boundaries are fiercely guarded:
</p>
<ul class="syllabus-list">
<li><strong>Controllers</strong> never query the database. They act purely as "Traffic Cops"—taking an HTTP request, handing it to a service, and returning an HTTP response.</li>
<li><strong>Repositories</strong> contain 100% of the SQL. No SQL is allowed anywhere else.</li>
<li><strong>Views (Templates)</strong> contain zero business logic. They simply loop over the data given to them and output HTML.</li>
</ul>
<blockquote class="syllabus-quote">
<strong>Historical Context:</strong> Early PHP (often called "Spaghetti Code") famously mixed HTML, database connections, and business logic all in the same <code>.php</code> file. SoC was formalized to stop this madness, eventually giving rise to the MVC (Model-View-Controller) pattern.<br>
</blockquote>
<h5>Pillar 3: Instructional Docblocks</h5>
<p>
Because this is an educational framework, the code itself is the textbook.
</p>
<p>
Every core file contains a standardized comment block explaining its <strong>Purpose</strong>, <strong>Why</strong> this design was chosen, and specific <strong>Teaching Notes</strong>. Method docblocks map the exact Execution Flow. We treat the code as living literature. If the intent of a class isnt perfectly clear from its Docblock, it's considered a bug.
</p>
<h5>Pillar 4: Strict Typing</h5>
<p>
PHP is traditionally a dynamically typed language (meaning a variable could be a string, and then become an integer later). While flexible, this causes catastrophic, silent bugs at an enterprise scale.
</p>
<p>
In our architecture, we leverage modern PHP (8+) features relentlessly. Every file begins with <code>declare(strict_types=1);</code>. Every method parameter has a type hint, and every method has a return type.
</p>
<pre><code>// The Old Way (Dangerous)
public function calculatePrice($amount, $taxRate) {
    return $amount * $taxRate; 
}

// The Magma Way (Safe &amp; Predictable)
public function calculatePrice(float $amount, float $taxRate): float {
    return $amount * $taxRate;
}
</code></pre>
<p>
<strong>The Friction of Strict Typing</strong>
<br>
As you correctly noted, strict typing introduces friction, especially on the frontend! The web operates via HTTP, and HTTP requests (<code>$_POST</code>, <code>$_GET</code>) are entirely text-based.
</p>
<p>
If a user submits a form saying <code>price=10.50</code>, PHP receives the string <code>&quot;10.50&quot;</code>. If we pass that string directly into our strictly typed <code>calculatePrice(float $amount)</code> method, the application will instantly crash with a Fatal Error.
</p>
<p>
To solve this friction without compromising our strict core, we introduce <strong>Data Transfer Objects (DTOs)</strong> (which we cover in Module 9). The DTO acts as a "bouncer" at the door. It catches the dirty, string-based HTTP request, validates it, and explicitly casts <code>&quot;10.50&quot;</code> into a pure <code>float 10.50</code>. Only then is it allowed into the strictly-typed core of the application.
</p>
<hr>
</div>
<div class="chapter-module" id="module-2-the-request-lifecycle-front-controller"><h2 class="chapter-title">Module 2: The Request Lifecycle & Front Controller</h2>
<h3>Chapter 2.1: The Front Controller Pattern & The <code>www/</code> Boundary</h3>
<h4>Subject & Intent: The Single Gateway</h4>
<p>
In the early days of PHP web development, building a website meant creating a series of individual <code>.php</code> files. If you went to <code>website.com/about.php</code>, the server executed <code>about.php</code>. If you went to <code>website.com/contact.php</code>, the server executed <code>contact.php</code>.
</p>
<p>
While simple, this "Page Controller" pattern created massive duplication. Every single file had to manually include the database connection, start the session, and check if the user was logged in. If a developer forgot to add the session check to just *one* file, the entire application was compromised.
</p>
<p>
To solve this, modern applications use the <strong>Front Controller Pattern</strong>.
</p>
<p>
In the Magma Framework architecture, every single HTTP request—whether it's asking for an HTML page, an API JSON response, or submitting a form—is funneled through one single entry point.
</p>
<h4>File Walkthrough: <code>www/index.php</code></h4>
<p>
Let's look at the actual code for our Front Controller. This is pulled directly from our workspace (<code>www/index.php</code>):
</p>
<pre><code>&lt;?php

/**
 * The Front Controller.
 * 
 * This is the only file in the entire application that should be publicly 
 * accessible to the web. It serves as the gateway that initiates the 
 * bootstrapping process and hands control over to the Application kernel.
 */

// 1. Leave the public directory immediately
require __DIR__ . &#039;/../magma/core/config/bootstrap.php&#039;;

// 2. Define our environment
define(&#039;ENVIRONMENT&#039;, \core\config\Config::get(&#039;APP_ENV&#039;, &#039;production&#039;));

use core\Application;
use core\middleware\CsrfMiddleware;
use core\middleware\UTMTrackerMiddleware;
use core\middleware\ViewShareMiddleware;
use core\middleware\SessionTimeoutMiddleware;

// 3. Resolve the application and add security layers
$app = $container-&gt;get(Application::class);
$app-&gt;addMiddleware(UTMTrackerMiddleware::class);
$app-&gt;addMiddleware(CsrfMiddleware::class);
$app-&gt;addMiddleware(SessionTimeoutMiddleware::class);
$app-&gt;addMiddleware(ViewShareMiddleware::class);

// 4. Run the application!
$app-&gt;run();
</code></pre>
<h4>Analyzing the Principles: The Public Boundary</h4>
<p>
The most crucial line in this file is the very first one:
<br>
<code>require __DIR__ . &#039;/../magma/core/config/bootstrap.php&#039;;</code>
</p>
<p>
Notice the directory structure. The web server (like Nginx or Apache) is configured to serve files *only* from the <code>www/</code> directory. However, all of our actual code, business logic, and configuration files live in <code>magma/</code>, which sits completely outside the document root.
</p>
<p>
By executing that <code>require</code> statement, we immediately "jump" out of the public folder and into our secure application container.
</p>
<p>
This is the ultimate Separation of Concerns regarding security. Even if the web server suffers a catastrophic misconfiguration and accidentally serves <code>.php</code> files as raw text instead of executing them, an attacker could only see the contents of <code>index.php</code>. They cannot reach our database credentials because those files simply do not exist within the server's public path.
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
Both Laravel (<code>public/index.php</code>) and Symfony (<code>public/index.php</code>) use this exact pattern, and for good reason. It provides a centralized location to enforce rules.
</p>
<p>
Because we add our <code>SessionTimeoutMiddleware</code> and <code>CsrfMiddleware</code> directly into the <code>$app</code> right here at the gateway, we absolutely guarantee that no controller deep inside the application can ever be reached without passing those security checks first. If we used the old multiple-file pattern, we could never make that guarantee.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: What is the security risk of having the core application folder (e.g. <code>magma/</code>, which contains the <code>.env</code> file) inside the public <code>www/</code> directory?</strong><br>
<br>
<strong>A:</strong> If those files are in the public domain, the database credentials and secret keys become easily accessible. If a web server misconfiguration occurs, an attacker could navigate directly to <code>website.com/magma/.env</code> and download your passwords as plain text. Keeping them strictly outside the document root eliminates this vector entirely.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Looking at <code>index.php</code>, why does it make sense to attach "global" middleware (like CSRF and Session checks) here at the entrance, rather than waiting until the Router decides which Controller to execute?</strong><br>
<br>
<strong>A:</strong> Think of it like a bag check at the entrance of the festival gates, rather than having one at every stall. By performing the security check once at the absolute perimeter, we guarantee that no malicious payload even reaches the inner workings of our application. We enforce security by default, rather than relying on individual controllers to remember to check it.<br>
</blockquote>
<hr>
<h3>Chapter 2.2: Bootstrapping the Application</h3>
<h4>Subject & Intent: Waking Up the Application</h4>
<p>
If <code>index.php</code> is the front door, <code>bootstrap.php</code> is the process of turning on the lights, booting up the computers, and unlocking the cash register before the customers arrive.
</p>
<p>
When execution jumps out of the <code>www/</code> directory via that <code>require</code> statement, it lands in <code>./magma/core/config/bootstrap.php</code>. This file is responsible for preparing the environment so that our business logic has everything it needs to execute.
</p>
<h4>File Walkthrough: <code>bootstrap.php</code></h4>
<p>
Let's look at the critical sections of this file:
</p>
<pre><code>&lt;?php
// ... docblocks omitted ...

// 1. The Autoloader
require_once __DIR__ . &#039;/autoload.php&#039;;

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
    $provider-&gt;register($container);
}
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
<strong>1. The PSR-4 Autoloader</strong>
<br>
Before PHP 5.3, if you wanted to use 10 different classes in a file, you had to write 10 <code>require &#039;path/to/class.php&#039;;</code> statements at the top of your file. It was a nightmare.
<br>
Today, we use an <strong>Autoloader</strong> (specifically adhering to the PSR-4 standard). We require the autoloader *once* here in the bootstrap file. From then on, whenever PHP sees <code>new core\container\Container()</code>, the autoloader intercepts it, maps the namespace <code>core\container</code> to the physical folder path, and includes the file automatically behind the scenes.
</p>
<p>
<strong>2. Centralized Configuration</strong>
<br>
<code>Config::initialize()</code> parses our hidden <code>.env</code> file (which contains our database passwords and API keys). Crucially, it loads them into a centralized <code>Config</code> object. We do this early so the rest of the application can simply ask the <code>Config</code> object for settings, rather than interacting with the raw filesystem or server globals.
</p>
<p>
<strong>3. The Service Providers</strong>
<br>
To keep our bootstrap file from becoming 5,000 lines long, we use the <strong>Service Provider Pattern</strong>. We group our setup logic into logical chunks (Core, Repository, Domain, Http). The bootstrap file simply loops over them and tells them to register themselves with the Dependency Injection Container.
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
Why keep <code>index.php</code> and <code>bootstrap.php</code> separate? Why not just put all the bootstrap logic directly in <code>index.php</code>?
</p>
<p>
<strong>Testing and CLI access.</strong>
<br>
If we put all the boot logic inside <code>index.php</code>, we couldn't run automated tests or command-line (CLI) scripts without faking an HTTP web request. By isolating the boot sequence into <code>bootstrap.php</code>, our web gateway (<code>index.php</code>), our CLI worker daemon (<code>worker.php</code>), and our PHPUnit test suite can all simply <code>require &#039;bootstrap.php&#039;</code> to wake up the application securely and consistently!
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Beyond saving us from typing <code>require</code> a thousand times, how does an Autoloader actually save memory and improve performance on a per-request basis?</strong><br>
<br>
<strong>A:</strong> The autoloader only loads what is necessary (lazy loading). If an HTTP request only triggers 5 classes out of a 500-class application, the autoloader only reads and parses those 5 files into memory. If we manually <code>require</code>d everything upfront, we would waste massive amounts of RAM and CPU parsing unused code.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Why is it safer and more robust to load <code>.env</code> variables into a dedicated <code>Config</code> object during bootstrap, rather than letting developers write <code>$_ENV[&#039;DB_PASSWORD&#039;]</code> randomly inside their classes?</strong><br>
<br>
<strong>A:</strong> It prevents every class from accessing the file structure, which is a major security risk. Having a single point of information (the <code>Config</code> object) allows us to control the data better. For instance, we can easily cache the configuration, validate it, or change where it comes from (e.g., switching from a <code>.env</code> file to AWS Secrets Manager) without having to rewrite any of the business logic that consumes the data.<br>
</blockquote>
<hr>
<h3>Chapter 2.3: Containerization & Execution</h3>
<h4>Subject & Intent: Passing the Baton</h4>
<p>
We have left the <code>www/</code> directory and we have bootstrapped our environment. We now have an autoloader and a populated <code>Config</code> object.
</p>
<p>
The final responsibility of the Front Controller phase is to <strong>build the Dependency Injection Container</strong> and <strong>execute the Application</strong>. This is the exact moment where the static "setup" phase ends, and the dynamic "runtime" phase begins.
</p>
<h4>File Walkthrough: The Handoff</h4>
<p>
If we look back at the final lines of our <code>bootstrap.php</code> file, we see this:
</p>
<pre><code>// magma/core/config/bootstrap.php

// The Container is instantiated
$container = new Container();

// Providers are given the container so they can register their services
foreach ($providers as $provider) {
    $provider-&gt;register($container);
}
</code></pre>
<p>
At this exact millisecond, the <code>$container</code> object holds a complete "map" of every interface in our application and the concrete class it should use (e.g., "If someone asks for <code>DatabaseInterface</code>, give them <code>PostgresConnection</code>"). We will dive deeply into *how* the container works in Module 3, but for now, just understand that it is our master factory.
</p>
<p>
Now, execution returns to <code>www/index.php</code> for the finale:
</p>
<pre><code>// www/index.php

// 1. We ask the container to build the Application Kernel for us
$app = $container-&gt;get(Application::class);

// 2. We attach our security middleware
$app-&gt;addMiddleware(UTMTrackerMiddleware::class);
$app-&gt;addMiddleware(CsrfMiddleware::class);
$app-&gt;addMiddleware(SessionTimeoutMiddleware::class);

// 3. We pull the trigger.
$app-&gt;run();
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
Notice that we did <strong>not</strong> write <code>$app = new Application();</code>.
</p>
<p>
We asked the container for it: <code>$container-&gt;get(Application::class);</code>.
</p>
<p>
Why? Because the <code>Application</code> kernel itself has dependencies! It might need the Router, the ErrorHandler, and the Request object to function. By asking the Container to build the Application, the Container uses PHP Reflection to look at the <code>Application</code>'s constructor, automatically builds the Router, builds the ErrorHandler, and injects them all perfectly.
</p>
<p>
When we finally call <code>$app-&gt;run()</code>, the kernel takes over. It captures the incoming HTTP request (headers, POST data, cookies), pushes it through the middleware "bag checks," and hands it to the Router to find the correct Controller.
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
In some older or highly "magical" frameworks, simply including the bootstrap file implicitly executes the application.
</p>
<p>
In the Magma Framework architecture, the <code>Application</code> object is passive until we explicitly call <code>run()</code>.
<br>
Why is this explicit execution better? Because it gives us control. For instance, in our testing environment, we might bootstrap the application, but instead of calling <code>run()</code> to process a web request, we manually trigger a specific service to test its output. We control the execution flow, not the framework.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: What is the massive downside of hardcoding <code>$app = new Application(new Router(), new ErrorHandler());</code> directly inside <code>index.php</code> instead of using the Container? Is it memory bloat?</strong><br>
<br>
<strong>A:</strong> While making too many copies (memory bloat) is a problem, the fundamental danger of the <code>new</code> keyword is <strong>Tight Coupling</strong> (Hardcoding dependencies). If you write <code>new Application(new Router())</code> directly in your <code>index.php</code>, your <code>index.php</code> file is permanently glued to that exact <code>Router</code> class. If tomorrow you decide to use a <code>FasterRouter</code> class, you have to open <code>index.php</code> and rewrite the core code. By using the Container, we simply update our configuration map, and <code>index.php</code> never has to change. The Container gives us the flexibility to swap components dynamically!<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: If <code>$app-&gt;run()</code> is the trigger that actually processes the HTTP request, how does keeping the "bootstrap" phase separate from the "run" phase help us if we want to write a background cron job?</strong><br>
<br>
<strong>A:</strong> It allows us to perform other tasks entirely! A cron job (like a script that emails users at midnight) doesn't involve an HTTP request. Because bootstrap and execution are separate, our cron script can simply <code>require &#039;bootstrap.php&#039;</code> to get access to the database and services, and then execute its own specific code, entirely bypassing <code>$app-&gt;run()</code>.<br>
</blockquote>
<hr>
<h3>Chapter 2.4: Dual-Mode Kernels</h3>
<h4>Subject & Intent: HTTP vs. CLI Execution</h4>
<p>
Modern enterprise applications don't just respond to web browsers. They process asynchronous background jobs, run scheduled cron tasks, and execute database migrations.
</p>
<p>
If we tied all our bootstrapping logic (like session starting or header parsing) directly into the core, our background workers would crash, because a background CLI script does not have an HTTP Session or an IP Address!
</p>
<p>
To solve this, Magma implements <strong>Dual-Mode Kernels</strong>:
</p>
<ol class="syllabus-list">
<li><strong>The Application Kernel (<code>Application.php</code>):</strong> Resolves the HTTP Request, dispatches it through the Middleware Onion, and returns an HTTP Response. It is strictly for web traffic.</li>
<li><strong>The CLI Kernel (<code>CliKernel.php</code> or raw Bin Scripts):</strong> Initializes the exact same Dependency Container, but completely bypasses the Router and Middleware. It expects console arguments instead of HTTP Requests.</li>
</ol>
<blockquote class="syllabus-quote">
<strong class="alert-tag">Tip:</strong><br>
<strong>The Magma Analogy:</strong> Think of the underlying Dependency Container and Services as the engine of a car. The HTTP Kernel is the steering wheel, while the CLI Kernel is an autonomous driving script. Both interact with the exact same engine (the core domain), but their input mechanisms (drivers) are completely isolated.<br>
</blockquote>
</div>
<div class="chapter-module" id="module-3-the-dependency-injection-container-the-core"><h2 class="chapter-title">Module 3: The Dependency Injection Container (The Core)</h2>
<h3>Chapter 3.1: Understanding Dependency Injection</h3>
<h4>Subject & Intent: The Restaurant Analogy</h4>
<p>
Before we look at the Container itself, we must deeply understand the pattern it facilitates: <strong>Dependency Injection</strong>.
</p>
<p>
Imagine you are opening a restaurant. You hire a Head Chef.
</p>
<p>
<strong>The Bad Way (Creating Dependencies Internally):</strong>
<br>
If you tell the Head Chef, *"It is your job to build your own oven from scratch before you can cook,"* you have a problem. The Chef is now responsible for cooking *and* oven manufacturing. If the oven breaks, the Chef doesn't know how to fix it. This is the equivalent of using the <code>new</code> keyword inside a class:
</p>
<pre><code>class Chef 
{
    private Oven $oven;

    public function __construct() 
    {
        // Bad: The Chef is creating his own dependency!
        $this-&gt;oven = new GasOven(); 
    }
}
</code></pre>
<p>
<strong>The Good Way (Injecting Dependencies):</strong>
<br>
Instead, you buy a commercial oven, install it in the kitchen, and tell the Chef, *"Here is an oven. Use it."* The Chef doesn't care who built the oven or how it works, only that it has a <code>execute()</code> button. This is Dependency Injection:
</p>
<pre><code>class Chef 
{
    private ServiceInterface $oven;

    // Good: The Chef demands an oven is provided to him!
    public function __construct(ServiceInterface $oven) 
    {
        $this-&gt;oven = $oven;
    }
}
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
Dependency Injection forces our classes to be honest about what they need to function. It perfectly aligns with the <strong>Single Responsibility Principle (SRP)</strong>: The Chef's only responsibility is cooking. It is *not* his responsibility to construct ovens or establish database connections.
</p>
<p>
It also aligns perfectly with <strong>Testability</strong>. If we want to test the Chef class to make sure his recipe works, we don't need to hook him up to a real, expensive gas oven. Because he accepts any <code>ServiceInterface</code>, we can pass him a fake <code>EasyBakeOven</code> just for the test.
</p>
<h4>The Problem the Container Solves</h4>
<p>
Dependency Injection is fantastic, but it creates a massive logistical headache.
</p>
<p>
If every class requires its dependencies to be passed into its constructor, who actually builds them? If our <code>OrderController</code> requires an <code>OrderService</code>, and the <code>OrderService</code> requires a <code>DatabaseRepository</code>, and the <code>DatabaseRepository</code> requires a <code>MySQLConnection</code>... we would have to manually build this massive "tree" of objects by hand every single time a request comes in!
</p>
<p>
That is exactly the problem the <strong>DI Container</strong> solves. It is an automated factory that builds this tree for us.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Why is it architecturally safer for a class to ask for its dependencies via its constructor (Injection) rather than instantiating them itself internally?</strong><br>
<br>
<strong>A:</strong> It ensures the class is kept in its corner doing only what it is responsible for (Single Responsibility). If a class instantiates its own dependencies, it takes on the responsibility of *knowing how* to build them, creating tight coupling. By injecting them, we control the dependencies from the outside, only giving the class exactly what it is allowed to have.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: If the DI Container didn't exist, what would be the main negative impact on our code when trying to instantiate a high-level class like a <code>Controller</code> that has deeply nested dependencies?</strong><br>
<br>
<strong>A:</strong> We would have to write a massive amount of copied, nested <code>new</code> statements (e.g., <code>new Controller(new Service(new Repository(new Database())))</code>). This "manual wiring" is tedious, repetitive, and we would quickly get lost in the boilerplate code every time we needed to create an object.<br>
</blockquote>
<hr>
<h3>Chapter 3.2: Unmasking the Container: PHP Reflection</h3>
<h4>Subject & Intent: The Mirror of Code</h4>
<p>
In Chapter 3.1, we established that our goal is to build an automated factory (the Container) that can build complex objects for us, so we don't have to write thousands of nested <code>new</code> statements.
</p>
<p>
But how can a generic <code>Container</code> class possibly know how to build *your* specific <code>OrderController</code>? It didn't write the code. How does it know that the <code>OrderController</code> requires an <code>OrderService</code>?
</p>
<p>
The answer is an incredibly powerful, native PHP feature called <strong>Reflection</strong>.
</p>
<p>
Reflection is an API that allows PHP code to "look in the mirror" and analyze itself at runtime. With Reflection, you can write code that asks questions like: *"Hey PHP, what methods does this class have? What parameters does its constructor require? What types are those parameters?"*
</p>
<h4>The Theory: How Auto-Wiring Works</h4>
<p>
In the Magma Framework framework, we use an advanced container technique called <strong>Auto-Wiring</strong>. We don't manually tell the container how to build every single class. Instead, the container uses Reflection to figure it out on the fly.
</p>
<p>
Here is a simplified example of what the Magma Container is doing inside its <code>get()</code> method when you ask it for a class:
</p>
<pre><code>// You ask the container for the OrderController
$container-&gt;get(OrderController::class);
</code></pre>
<p>
Behind the scenes, the Container uses the <code>ReflectionClass</code>:
</p>
<pre><code>// 1. The Container reflects upon the requested class
$reflection = new ReflectionClass(OrderController::class);

// 2. It looks at the constructor
$constructor = $reflection-&gt;getConstructor();

// 3. It asks: &quot;What parameters do you require?&quot;
$parameters = $constructor-&gt;getParameters();

$dependencies = [];

// 4. It loops through the required parameters
foreach ($parameters as $parameter) {
    // It finds that the constructor needs an &quot;OrderService&quot; type!
    $dependencyType = $parameter-&gt;getType()-&gt;getName(); 
    
    // 5. RECURSION! The container calls ITSELF to build the OrderService!
    $dependencies[] = $this-&gt;get($dependencyType); 
}

// 6. Finally, it builds the OrderController, passing in the newly built dependencies.
return $reflection-&gt;newInstanceArgs($dependencies);
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
This is the moment the "Magic" becomes <strong>Science</strong>.
</p>
<p>
When a framework automatically injects dependencies into your controllers, it isn't using actual magic. It's just using <code>ReflectionClass</code> to read your constructor's type hints, building those dependencies recursively, and then handing you the fully assembled object.
</p>
<p>
Because we demand <strong>Strict Typing</strong> (Pillar 4), our constructors always look like this:
<br>
<code>public function __construct(OrderService $service)</code>
</p>
<p>
Because we strictly typed the <code>$service</code> parameter as an <code>OrderService</code>, the Reflection API can read that type hint and know exactly what to build. If we didn't use strict typing, Reflection would have no idea what to inject, and auto-wiring would fail entirely!
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
In older or simpler frameworks, you don't use auto-wiring. Instead, you have to write a massive configuration array manually defining how to build *every single class* in your application.
</p>
<pre><code>// The Old &quot;Manual Configuration&quot; Way (Tedious)
$container[&#039;OrderController&#039;] = function($c) {
    return new OrderController($c[&#039;OrderService&#039;]);
};
</code></pre>
<p>
By utilizing PHP Reflection, our Container becomes "smart." We don't have to configure 99% of our classes. The container just reads the constructors and figures it out. It gives us the convenience of modern frameworks without hiding the mechanism behind a black box.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Why is our architectural rule of Strict Typing absolutely mandatory if we want to use an Auto-Wiring Container?</strong><br>
<br>
<strong>A:</strong> It makes sure the reflection class understands exactly what it is looking for and doesn't get lost. If a constructor parameter is just <code>$service</code> without a type hint, Reflection only sees a <code>mixed</code> type. It has no idea what class to instantiate! The type hint is the physical map the Container follows.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Reflection forces PHP to analyze constructors while the application is running. What potential downside does this introduce every time a user makes a web request?</strong><br>
<br>
<strong>A:</strong> It takes a little longer to load the initial tree (a performance penalty). However, enterprise applications solve this by "compiling" or caching the Reflection tree during the deployment phase. This means in production, the app reads from a highly efficient cached map rather than running Reflection on every single request, giving us the best of both worlds.<br>
</blockquote>
<hr>
<h3>Chapter 3.3: Interface Binding (The Service Providers)</h3>
<h4>Subject & Intent: The Limits of Reflection</h4>
<p>
In Chapter 3.2, we saw that Auto-Wiring is incredibly smart. If a controller's constructor asks for an <code>OrderService</code>, the Container uses Reflection, finds the <code>OrderService</code> class, and builds it.
</p>
<p>
But what happens when we follow the Dependency Inversion Principle (Pillar 1) properly?
<br>
What if our constructor looks like this?
</p>
<pre><code>public function __construct(DatabaseInterface $database)
</code></pre>
<p>
<strong>Reflection hits a wall.</strong> It looks at <code>DatabaseInterface</code> and realizes it cannot instantiate an Interface. An interface is just a contract; it has no actual code. If Reflection tries to write <code>$db = new DatabaseInterface();</code>, PHP will throw a Fatal Error.
</p>
<p>
The Container needs to know *which specific concrete class* to use when someone asks for that interface.
</p>
<h4>File Walkthrough: The Service Providers</h4>
<p>
To solve this, we give the Container a manual "map." We say: *"Hey Container, whenever someone asks for <code>DatabaseInterface</code>, I want you to give them an instance of <code>PostgresConnection</code>."*
</p>
<p>
We call this <strong>Binding</strong>. In Magma Framework, we organize our bindings into <strong>Service Providers</strong>.
</p>
<p>
Let's look at <code>magma/core/providers/CoreServiceProvider.php</code>:
</p>
<pre><code>namespace core\providers;

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
        $container-&gt;bind(DatabaseInterface::class, PostgresConnection::class);
        
        // ... other bindings ...
    }
}
</code></pre>
<p>
If you recall Chapter 2.2 (Bootstrapping), our <code>bootstrap.php</code> file loops through these Service Providers and executes their <code>register()</code> methods right before the application runs.
</p>
<h4>Analyzing the Principles: The Power of Swappability</h4>
<p>
By separating the *request* for an object (in the constructor) from the *configuration* of that object (in the Service Provider), we achieve ultimate architectural flexibility.
</p>
<p>
This is the very essence of the <strong>Open/Closed Principle (OCP)</strong>.
</p>
<p>
Imagine Sandbox Corp wants to switch their cache system from Redis to Memcached.
</p>
<ol class="syllabus-list">
<li>We write a new <code>MemcachedCache</code> class that implements <code>CacheInterface</code>.</li>
<li>We go to our <code>CoreServiceProvider</code> and change one line of code:</li>
</ol>
<p>
<code>$container-&gt;bind(CacheInterface::class, MemcachedCache::class);</code>
</p>
<p>
<strong>That's it.</strong> We don't have to touch the <code>ProductService</code>, the <code>OrderController</code>, or any of the hundreds of files that use the Cache. The Container simply starts handing out the new Memcached object to anyone who asks for the Interface.
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
Some frameworks allow you to bind interfaces using configuration arrays (like YAML or XML files). We explicitly choose to use PHP classes (Service Providers) for our bindings.
</p>
<p>
Why? Because PHP code can be statically analyzed by your IDE. If you have a typo in an XML configuration file, your application will crash at runtime. If you have a typo in a PHP Service Provider, your editor will immediately underline it in red before you even run the code. We prefer explicit, type-safe PHP over abstract configuration files.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: If we bind <code>PaymentGatewayInterface</code> to <code>StripeGateway</code> in our Service Provider, and next year the business wants to switch to <code>PayPalGateway</code>, exactly what files in our core business logic (<code>OrderService</code>, <code>CheckoutController</code>) do we need to modify?</strong><br>
<br>
<strong>A:</strong> Absolutely none of them! We only change the binding in the Service Provider to point to the new <code>PayPalGateway</code>. Because the core business logic only depends on the generic <code>PaymentGatewayInterface</code>, it remains entirely untouched. This is the Open/Closed Principle in action.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: If Reflection is so smart, why can't it just automatically figure out which class to use when a constructor asks for an Interface?</strong><br>
<br>
<strong>A:</strong> Because of intent and control. If we have 5 different classes that all implement <code>PaymentGatewayInterface</code> (Stripe, PayPal, ApplePay, FakeTestingGateway, etc.), it is physically impossible for PHP to magically guess which one the developer *intends* to use for this specific environment. By strictly mapping out the connections, we explicitly control exactly what logic is used and where.<br>
</blockquote>
<hr>
<h3>Chapter 3.4: Defending Against Memory Leaks & O(1) Management</h3>
<p>
As the application grows, resolving hundreds of dependencies per request can become slow and consume massive amounts of RAM.
</p>
<p>
Magma's Container manages memory efficiently using a <strong>Singleton Cache</strong> for stateless services. When a <code>DatabaseConnectionManager</code> is resolved for the first time, the Container caches the instance. If five different Repositories ask for a <code>DatabaseConnectionManager</code>, they all receive the exact same instance reference in memory (O(1) memory overhead).
</p>
<p>
By preventing the redundant instantiation of heavy services, Magma keeps its memory footprint entirely flat, allowing it to serve thousands of requests concurrently on minimal hardware.
</p>
<h3>Chapter 3.5: Breaking Circular Dependency Deadlocks</h3>
<p>
A classic architectural bug occurs when <code>ServiceA</code> requires <code>ServiceB</code>, but <code>ServiceB</code> requires <code>ServiceA</code>. If left unchecked, the Reflection engine will enter an infinite loop, crashing the server with a Stack Overflow or an Out Of Memory (OOM) error.
</p>
<p>
Magma's <code>Container</code> physically protects against this by tracking resolution paths. If it detects a circular dependency (e.g., trying to resolve <code>ServiceA</code> while <code>ServiceA</code> is already in the 'resolving' stack), it immediately aborts and throws a <code>CircularDependencyException</code>.
</p>
</div>
<div class="chapter-module" id="module-4-routing-the-http-request"><h2 class="chapter-title">Module 4: Routing & The HTTP Request</h2>
<h3>Chapter 4.1: The Router - Mapping URLs to Controllers</h3>
<h4>Subject & Intent: The Traffic Cop</h4>
<p>
When a user types <code>sandbox.local/products</code> into their browser, an HTTP <code>GET</code> request is sent to our server. As we know from Module 2, this request hits our Front Controller (<code>www/index.php</code>) and triggers <code>$app-&gt;run()</code>.
</p>
<p>
But how does the application know that <code>/products</code> should execute the code that fetches modules from the database, while <code>/checkout</code> should execute the payment code?
</p>
<p>
The answer is the <strong>Router</strong>.
</p>
<p>
The Router is the "Traffic Cop" of the application. Its sole responsibility is to look at the incoming URL (the URI) and the HTTP Method (GET, POST, PUT, DELETE), and match it against a predefined list of "Routes" to determine which <strong>Controller</strong> should handle the request.
</p>
<h4>The Theory: Defining Routes</h4>
<p>
Before the Router can route traffic, we have to give it a map. In the Magma Framework framework, we explicitly define this map in a dedicated routes file.
</p>
<p>
A typical route definition looks like this:
</p>
<pre><code>// If the user makes a GET request to &#039;/products&#039;, 
// execute the &#039;index&#039; method on the &#039;ProductController&#039; class.

$router-&gt;get(&#039;/products&#039;, [ProductController::class, &#039;index&#039;]);

// If they make a POST request to &#039;/checkout&#039; (submitting a form),
// execute the &#039;process&#039; method on the &#039;CheckoutController&#039; class.

$router-&gt;post(&#039;/checkout&#039;, [CheckoutController::class, &#039;process&#039;]);
</code></pre>
<h4>File Walkthrough: The Execution Flow</h4>
<p>
Let's look at exactly what happens inside the <code>Application::run()</code> method when that request comes in:
</p>
<ol class="syllabus-list">
<li><strong>Capture the Request:</strong> The application creates an <code>HttpRequest</code> object containing all the details from the user's browser (the URL, the POST data, headers).</li>
<li><strong>Hand it to the Router:</strong> The application passes this request object to the <code>Router</code>.</li>
<li><strong>Find the Match:</strong> The <code>Router</code> loops through all the registered routes (like the ones we defined above). It sees the user is asking for <code>GET /products</code>, and it finds a matching route!</li>
<li><strong>Resolve the Controller:</strong> The Router sees that this route points to <code>ProductController::class</code>. It asks the <strong>DI Container</strong> (from Module 3) to build the <code>ProductController</code>.</li>
<li><strong>Execute the Method:</strong> The Router takes the fully built <code>ProductController</code> and calls the <code>index()</code> method on it.</li>
</ol>
<pre><code>// A simplified view of what the Router does internally:
$controllerInstance = $container-&gt;get($route-&gt;getControllerClass());
return $controllerInstance-&gt;{$route-&gt;getMethodName()}();
</code></pre>
<h4>Analyzing the Principles: Separation of Concerns</h4>
<p>
You might wonder: *Why do we have a dedicated Router class? Why can't we just put a giant <code>if/else</code> statement inside <code>index.php</code>?*
</p>
<pre><code>// The Bad Way (Violating SRP)
if ($_SERVER[&#039;REQUEST_URI&#039;] === &#039;/products&#039;) {
    $controller = new ProductController();
    $controller-&gt;index();
} elseif ($_SERVER[&#039;REQUEST_URI&#039;] === &#039;/checkout&#039;) {
    // ...
}
</code></pre>
<p>
This violates the <strong>Single Responsibility Principle (SRP)</strong>. If we put routing logic inside the <code>index.php</code> or <code>Application</code> class, those files would grow to thousands of lines long as the application scales. By extracting routing into its own dedicated <code>Router</code> object, we keep our code modular, testable, and extremely clean.
</p>
<p>
Furthermore, a dedicated Router can handle complex scenarios like dynamic parameters:
<br>
<code>$router-&gt;get(&#039;/products/{id}&#039;, [ProductController::class, &#039;show&#039;]);</code>
<br>
Here, the Router is smart enough to extract <code>{id}</code> from the URL (like <code>/products/5</code>) and pass the <code>5</code> as an argument directly into the controller's <code>show($id)</code> method!
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine a junior developer adds a new route, but instead of just returning the mapped controller, they write code inside the Router class to query the database to check if the user is an admin. Why is this a severe architectural violation?</strong><br>
<br>
<strong>A:</strong> It forces the router class to handle more than one task, violently breaking the Single Responsibility Principle. The Router is suddenly responsible for mapping URLs *and* executing authorization logic. This means the Router is no longer highly testable or easily extendable, and that admin-checking logic will likely have to be copied and pasted elsewhere because it is trapped in the wrong layer of the application.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: We map URLs using specific HTTP methods (<code>GET</code> vs <code>POST</code>). What would go horribly wrong if the framework didn't care about the HTTP method, and allowed a user to trigger the <code>CheckoutController::process()</code> method (which charges a card) via a simple GET request in the browser?</strong><br>
<br>
<strong>A:</strong> The site's code could be hacked to perform actions not requested! <code>GET</code> requests are intended to be "safe" (read-only). If a destructive action like charging a card was allowed via <code>GET</code>, an attacker could simply trick a user into clicking a link (<code>sandbox.local/checkout</code>) or embed an invisible image tag that loads the URL, instantly charging the user's card without their consent. Enforcing <code>POST</code> for destructive actions is a fundamental security requirement.<br>
</blockquote>
<hr>
<h3>Chapter 4.2: Middleware - Border Security (The Onion)</h3>
<h4>Subject & Intent: Filtering the Traffic</h4>
<p>
In Chapter 2.1, we talked about "bag checks" at the festival entrance. In modern web architecture, these bag checks are called <strong>Middleware</strong>.
</p>
<p>
If the Router is the Traffic Cop pointing cars to their destinations, Middleware are the toll booths and security checkpoints along the highway.
</p>
<p>
We can visualize Middleware as an <strong>Onion</strong>.
</p>
<ul class="syllabus-list">
<li>The <strong>Core</strong> of the onion is our Controller (where the actual business logic happens).</li>
<li>The <strong>Layers</strong> of the onion are our Middleware.</li>
<li>An incoming HTTP Request must pass *inward* through every layer of the onion to reach the Core.</li>
<li>The resulting HTTP Response must pass *outward* through every layer before returning to the user's browser.</li>
</ul>
<h4>The Theory: The Contract of Middleware</h4>
<p>
Every single Middleware class in the Magma framework follows a strict contract. It receives the incoming Request, and a special function called <code>$next</code>.
</p>
<p>
The Middleware has absolute power to decide what to do:
</p>
<ol class="syllabus-list">
<li><strong>Pass:</strong> It can inspect the Request, decide everything is fine, and call <code>$next($request)</code> to pass the request deeper into the onion.</li>
<li><strong>Reject:</strong> It can inspect the Request, find a problem, and instantly return a redirect or an Error Page, <strong>completely blocking the request from ever reaching the controller</strong>.</li>
</ol>
<h4>File Walkthrough: <code>SessionTimeoutMiddleware</code></h4>
<p>
Let's look at a concrete example. We want to automatically log out users if they have been inactive for 30 minutes.
</p>
<p>
We *could* write this check at the top of every single Controller, but that violates DRY (Don't Repeat Yourself) and SRP. Instead, we write a single Middleware:
</p>
<pre><code>namespace core\middleware;

use core\http\Request;
use core\http\Response;

class SessionTimeoutMiddleware 
{
    public function handle(Request $request, callable $next): Response 
    {
        // 1. Inspect the Request context (the session)
        $lastActivity = $_SESSION[&#039;last_activity&#039;] ?? time();
        $timeoutLimit = 1800; // 30 minutes in seconds

        if (time() - $lastActivity &gt; $timeoutLimit) {
            // 2. REJECT! We do NOT call $next. 
            // The Controller is never reached. We immediately return a redirect.
            session_destroy();
            return Response::redirect(&#039;/login?error=timeout&#039;);
        }

        // 3. Update the activity timer
        $_SESSION[&#039;last_activity&#039;] = time();

        // 4. PASS! Hand the request to the next layer of the onion
        return $next($request);
    }
}
</code></pre>
<p>
If you remember from <code>index.php</code>, we register this globally: <code>$app-&gt;addMiddleware(SessionTimeoutMiddleware::class);</code>
</p>
<p>
Because this is registered globally, our <code>ProductController</code> doesn't have to know that session timeouts even exist. It can safely assume that if a request reached it, the session is active.
</p>
<h4>Analyzing the Principles: The Power of the Pipeline</h4>
<p>
This pattern is formally known as the <strong>Pipeline Pattern</strong>.
</p>
<p>
It gives us immense flexibility. We can create highly specific Middleware for specific routes. For example, the <code>AdminMiddleware</code> might only be attached to routes that start with <code>/admin</code>.
</p>
<p>
It perfectly satisfies the <strong>Open/Closed Principle (OCP)</strong>. If the business asks us to implement a new feature—for example, "Block all IP addresses coming from outside the UK"—we do not touch a single line of existing code. We simply write a <code>GeoIPMiddleware</code> class, add it to our pipeline in <code>index.php</code>, and the entire application instantly inherits the new security rule.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: If a Middleware class detects a session timeout, it returns a Redirect response immediately without calling <code>$next</code>. Why is it highly efficient that the Middleware can abort the journey without ever letting the Request reach the Controller?</strong><br>
<br>
<strong>A:</strong> The Controller does not need to know that everything is okay; it only needs to do its job. By letting the middleware block invalid requests, it saves the server from doing unnecessary work (like database queries or complex logic inside the controller) because the request is aborted early!<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: We described Middleware as an onion. What might be a real-world example of a Middleware that does its job by modifying the *Response* on the way out, rather than inspecting the *Request* on the way in?</strong><br>
<br>
<strong>A:</strong> Modifying the response format is a great example, such as appending seasonal information. Other common real-world examples include adding CORS (Cross-Origin Resource Sharing) headers to the response, compressing the HTML (like gzip) to save bandwidth before sending it to the browser, or stripping out whitespace to minify the output.<br>
</blockquote>
<hr>
<h3>Chapter 4.3: The Request & Response Objects</h3>
<h4>Subject & Intent: Encapsulating the Web</h4>
<p>
In raw, native PHP, when you want to see what the user typed into a form or check what their IP address is, you look at superglobal arrays like <code>$_POST</code>, <code>$_GET</code>, <code>$_COOKIE</code>, and <code>$_SERVER</code>.
</p>
<p>
When you want to send data back to the user, you use functions like <code>echo &quot;Hello&quot;;</code> or <code>header(&#039;Location: /login&#039;);</code>.
</p>
<p>
In the Magma framework, <strong>we absolutely forbid the use of these raw global variables and functions.</strong>
</p>
<p>
Instead, we encapsulate everything into two strict objects: the <code>HttpRequest</code> object and the <code>HttpResponse</code> object.
</p>
<h4>The Theory: Why Encapsulation Matters</h4>
<p>
A fundamental rule of Object-Oriented Programming is that you should not rely on global state.
</p>
<p>
If your <code>OrderController</code> looks directly at <code>$_POST[&#039;quantity&#039;]</code>, it is relying on a global variable. This creates a massive problem for <strong>Testability</strong>. If you want to write a unit test for your controller, you have to artificially inject fake data into the <code>$_POST</code> array before running the test, which is clunky and can accidentally bleed into other tests.
</p>
<p>
Furthermore, <code>$_POST</code> is untyped. Is the quantity an integer <code>5</code>, or the string <code>&quot;5&quot;</code>, or an array?
</p>
<h4>File Walkthrough: The <code>Request</code> Object</h4>
<p>
At the very beginning of the Front Controller lifecycle, our application gathers all the global variables, packages them into a <code>Request</code> object, and then destroys the global variables (figuratively speaking) so no one else can use them.
</p>
<p>
This <code>Request</code> object is what gets passed through the Middleware and eventually into the Controller.
</p>
<pre><code>namespace core\http;

class Request 
{
    private array $postData;
    private array $queryData;

    public function __construct(array $post, array $query) 
    {
        $this-&gt;postData = $post;
        $this-&gt;queryData = $query;
    }

    // We control exactly how the data is accessed!
    public function getPostString(string $key, string $default = &#039;&#039;): string 
    {
        $value = $this-&gt;postData[$key] ?? $default;
        return is_string($value) ? htmlspecialchars($value) : $default;
    }

    public function getPostInt(string $key, int $default = 0): int 
    {
        return (int) ($this-&gt;postData[$key] ?? $default);
    }
}
</code></pre>
<p>
Look at how powerful this is! By forcing the Controller to use <code>$request-&gt;getPostInt(&#039;quantity&#039;)</code>, we guarantee that the Controller receives an actual integer, completely satisfying our strict typing requirements. We can also build automatic security features (like <code>htmlspecialchars()</code>) directly into the object.
</p>
<h4>File Walkthrough: The <code>Response</code> Object</h4>
<p>
Similarly, a Controller should never use <code>echo</code>. Why? Because <code>echo</code> immediately flushes data to the browser. If a Middleware layer on the "way out" wanted to compress that data, it's too late! The data is already gone.
</p>
<p>
Instead, a Controller always returns a <code>Response</code> object.
</p>
<pre><code>namespace core\http;

class Response 
{
    private string $content;
    private int $statusCode;

    public function __construct(string $content, int $statusCode = 200) 
    {
        $this-&gt;content = $content;
        $this-&gt;statusCode = $statusCode;
    }

    // The ONLY place in the application where output actually happens
    public function send(): void 
    {
        http_response_code($this-&gt;statusCode);
        echo $this-&gt;content;
    }
}
</code></pre>
<p>
The Application kernel receives this <code>Response</code> object from the Controller, passes it outward through the Middleware (allowing them to modify <code>$response-&gt;content</code> if they wish), and finally, the kernel calls <code>$response-&gt;send()</code> at the very end of the lifecycle.
</p>
<h4>Analyzing the Principles</h4>
<p>
By utilizing Request and Response objects, we enforce <strong>Immutability and Control</strong>. The flow of data is no longer a chaotic free-for-all of global variables. It is a strictly typed, trackable package moving through an organized pipeline.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: How does requiring a Request object (rather than looking directly at <code>$_POST</code>) make writing automated unit tests for a Controller significantly easier and cleaner?</strong><br>
<br>
<strong>A:</strong> If everything passes through our Request object checkpoint, we can easily test the controller without a real browser. We simply write <code>$request = new Request([&#039;quantity&#039; =&gt; 5], []);</code> and pass it to our Controller. We don't have to hack the global <code>$_POST</code> array, meaning our tests are perfectly isolated, predictable, and we can test the data against any rule we choose from one location.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: If a controller uses <code>echo &quot;Hello&quot;;</code>, the text is sent to the user instantly. If a controller instead returns <code>new Response(&quot;Hello&quot;);</code>, how does the "Middleware Onion" architecture benefit from this delay?</strong><br>
<br>
<strong>A:</strong> It allows the framework to intercept the object on the way out! Because the output is delayed until the very end, we can perform any operation on the response before providing it to the user.<br>
</blockquote>
<hr>
<h3>Chapter 4.4: The Evolution to O(1) Regex Routing</h3>
<p>
Many frameworks evaluate routes sequentially. If you have 1,000 routes, the framework might run a regex check 1,000 times until it finds a match. This is an O(N) operation, meaning routing gets slower as your application grows.
</p>
<p>
Magma evolved to utilize a highly optimized <strong>PCRE (Perl Compatible Regular Expressions) compiled route strategy</strong>.
</p>
<p>
Instead of checking routes one by one, Magma compiles all registered routes into a single, massive Regular Expression map (<code>routes.cache.php</code>). When a request comes in, the entire routing table is evaluated in a single, lightning-fast O(1) operation.
</p>
<blockquote class="syllabus-quote">
<strong class="alert-tag">Tip:</strong><br>
<strong>Performance Note:</strong> If you add a new route, you MUST run <code>php bin/cache_routes.php</code>. Because Magma reads from the compiled cache rather than the raw file, this ensures absolute maximum performance in production environments.<br>
</blockquote>
</div>
<div class="chapter-module" id="module-5-controllers-services-the-business-logic"><h2 class="chapter-title">Module 5: Controllers & Services (The Business Logic)</h2>
<h3>Chapter 5.1: The Controller - The Traffic Cop (Redux)</h3>
<h4>Subject & Intent: The Delegator</h4>
<p>
The word "Controller" in the MVC (Model-View-Controller) pattern is somewhat dangerous. It implies that the controller should *control* how the application works.
</p>
<p>
This historically led to what developers call "Fat Controllers." A fat controller is a file with 1,000 lines of code that handles file uploads, writes to the database, calculates taxes, and sends emails all in one giant method.
</p>
<p>
In the Magma Framework architecture, we view the Controller strictly as a <strong>Delegator</strong>. It is a middle-manager. It does not do the hard work; it simply organizes the work.
</p>
<h4>The Theory: The Three Rules of a Controller</h4>
<p>
To prevent "Fat Controllers," we enforce three absolute rules for any Controller class:
</p>
<ol class="syllabus-list">
<li><strong>Never write Business Logic:</strong> A controller should not calculate tax. It should not check inventory.</li>
<li><strong>Never query the Database:</strong> A controller should never contain SQL or interact directly with a database connection.</li>
<li><strong>Always return a Response:</strong> The controller's only purpose is to take the incoming <code>Request</code>, give the data to a "Service," and wrap the result in a <code>Response</code>.</li>
</ol>
<h4>File Walkthrough: A Magma Controller</h4>
<p>
Let's look at what a perfectly clean Controller looks like when a customer submits an order:
</p>
<pre><code>namespace magma\controllers;

use core\http\Request;
use core\http\Response;
use magma\services\OrderService;

class CheckoutController 
{
    private OrderService $orderService;

    // 1. Dependency Injection: The Container provides the Service
    public function __construct(OrderService $orderService) 
    {
        $this-&gt;orderService = $orderService;
    }

    public function process(Request $request): Response 
    {
        // 2. Extract the clean data from the Request object
        $productId = $request-&gt;getPostInt(&#039;product_id&#039;);
        $quantity = $request-&gt;getPostInt(&#039;quantity&#039;);

        try {
            // 3. Delegate the actual work to the Service!
            $success = $this-&gt;orderService-&gt;placeOrder($productId, $quantity);

            // 4. Return the appropriate Response
            return new Response(&quot;Order Placed Successfully!&quot;);

        } catch (\Exception $e) {
            // 5. Handle failures gracefully
            return new Response(&quot;Error: &quot; . $e-&gt;getMessage(), 400);
        }
    }
}
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
This class perfectly adheres to the <strong>Single Responsibility Principle (SRP)</strong>. Its *only* responsibility is translating HTTP traffic into PHP method calls.
</p>
<p>
Notice how small the <code>process()</code> method is. If the logic for calculating tax on an order changes tomorrow, we do not need to touch the <code>CheckoutController</code>. The Controller doesn't care how an order is placed, it only cares *that* an order is placed.
</p>
<p>
By pushing the complex logic down into the <code>OrderService</code> (which we will cover next), we keep our HTTP layer incredibly thin and easy to read.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine Sandbox Corp decides to launch a mobile app next year that communicates with our server via an API, not a web browser. If we had written all of our tax calculation and database logic directly inside the <code>CheckoutController</code>, why would building this new API be incredibly painful?</strong><br>
<br>
<strong>A:</strong> Because we would have to rewrite and duplicate every single business rule (like calculating taxes) for the API! By keeping the Controller as just a "Traffic Cop" and pushing the logic into a Service, our new <code>ApiController</code> can simply inject the exact same <code>OrderService</code> and reuse 100% of the business logic.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: In our example, the Controller asks the <code>OrderService</code> to do the work. If the <code>OrderService</code> discovers that a module is out of stock, should the <code>OrderService</code> be the one to generate the <code>new Response(&quot;Out of stock&quot;, 400)</code> object?</strong><br>
<br>
<strong>A:</strong> No! This is a critical distinction in Separation of Concerns. A <code>Response</code> is an HTTP concept (it belongs to the web layer). The <code>OrderService</code> is pure business logic; it doesn't know what the internet is. If it's out of stock, the Service should throw an <code>OutOfStockException</code> (or return a boolean/result object). The *Controller* catches that exception and builds the HTTP <code>Response</code>. This keeps the Service layer perfectly isolated from the web layer!<br>
</blockquote>
<hr>
<h3>Chapter 5.2: The Service Layer - Where the Work Happens</h3>
<h4>Subject & Intent: The Brain of the Application (The Evolutionary Starting Point)</h4>
<p>
If the Controller is the Traffic Cop, the <strong>Service</strong> is the highly-trained mechanic inside the garage.
</p>
<p>
*A Historical Note on Architecture:* When the initial framework was first built, the Service Layer was where 100% of your business rules lived. If a developer asked, *"How does Sandbox Corp calculate tax?"*, they would open the relevant Service class. This pattern is known as the <strong>Transaction Script</strong>.
</p>
<p>
We teach this pattern here because it is crucial to understand *how* logic is isolated from Controllers and Databases. However, as you will see in <strong>Module 10 (Domain-Driven Design)</strong>, this approach eventually breaks down as an application grows into an enterprise platform. The code examples below represent the *starting point* of our framework's evolution, not its final destination.
</p>
<h4>The Theory: Services Orchestrate</h4>
<p>
A Service's job is <strong>Orchestration</strong>. It rarely does everything by itself. Instead, it coordinates other specialized classes.
</p>
<p>
For example, when a user places an order, the <code>OrderService</code> must:
</p>
<ol class="syllabus-list">
<li>Ask the <code>InventoryRepository</code> if the module is in stock.</li>
<li>Ask the <code>PricingService</code> to calculate the tax and total.</li>
<li>Ask the <code>PaymentGateway</code> to charge the credit card.</li>
<li>Ask the <code>OrderRepository</code> to save the final receipt to the database.</li>
<li>Ask the <code>MailerService</code> to email the customer.</li>
</ol>
<p>
Because the Service is doing all this heavy lifting, it is *heavily* dependent on Dependency Injection.
</p>
<h4>File Walkthrough: The <code>OrderService</code></h4>
<p>
Let's look at what the <code>placeOrder</code> method actually looks like inside <code>magma/services/OrderService.php</code>:
</p>
<pre><code>namespace magma\services;

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
        if (!$this-&gt;inventoryRepo-&gt;hasStock($productId, $quantity)) {
            // Notice: We throw an exception, NOT an HTTP Response!
            throw new OutOfStockException(&quot;Module is sold out.&quot;);
        }

        // 2. Charge the card
        $paymentSuccess = $this-&gt;paymentGateway-&gt;charge($creditCardToken, 50.00);
        
        if (!$paymentSuccess) {
            return false;
        }

        // 3. Save to Database
        $orderId = $this-&gt;orderRepo-&gt;createOrder($productId, $quantity);

        // 4. Send Email
        $this-&gt;mailer-&gt;sendReceipt($orderId);

        return true;
    }
}
</code></pre>
<h4>Analyzing the Principles: The Power of Isolation</h4>
<p>
Look closely at that file. Notice what is missing?
</p>
<ul class="syllabus-list">
<li><strong>No <code>$_POST</code> or <code>Request</code> objects.</strong> The Service doesn't know it's a web app. It just takes standard PHP variables (<code>int $productId</code>).</li>
<li><strong>No <code>Response</code> objects.</strong> It returns a boolean (<code>true</code>/<code>false</code>) or throws an Exception.</li>
<li><strong>No <code>SQL</code> strings.</strong> It asks the Repositories to handle the database saving.</li>
<li><strong>No <code>new</code> keywords.</strong> It uses interfaces (<code>MailerInterface</code>, <code>PaymentGatewayInterface</code>) so we can swap providers easily.</li>
</ul>
<p>
Because the <code>OrderService</code> is perfectly isolated, we can write a CLI script (a terminal command) that uses this exact same service to create test orders, and it works perfectly!
</p>
<p>
This isolation makes <strong>Unit Testing</strong> a dream. We can test the <code>placeOrder</code> logic by passing a "fake" <code>PaymentGateway</code> into the constructor that always returns <code>true</code>. We can instantly verify that the email logic triggers correctly without actually charging a real credit card.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine we wrote a command-line script to automatically generate 10 test orders for our developers. Why is it structurally impossible for that CLI script to use the <code>CheckoutController</code> to create the orders, and why does it *have* to use the <code>OrderService</code>?</strong><br>
<br>
<strong>A:</strong> The Controller only deals with HTTP concepts; it requires an <code>HttpRequest</code> object and returns an <code>HttpResponse</code> object. A command-line script doesn't have an HTTP request! By using the <code>OrderService</code> directly, the CLI script bypasses the web layer entirely and utilizes the pure PHP logic.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: The <code>OrderService</code> asks the <code>MailerInterface</code> to send an email. Why didn't we just write the <code>mail()</code> logic directly inside the <code>OrderService</code>?</strong><br>
<br>
<strong>A:</strong> It violates the Single Responsibility Principle (SRP). The <code>OrderService</code> is responsible for orchestrating the order flow, not for dealing with SMTP protocols, email headers, and connection timeouts. By delegating to a <code>MailerInterface</code>, the service remains clean and focused.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Would <code>OrderService</code> eventually become monolithic and unwieldy? Eventually, will this class have to be extrapolated into multiple services that handle an order?</strong><br>
<br>
<strong>A:</strong> Absolutely correct! This is a phenomenon known as the "God Class" anti-pattern. If <code>OrderService</code> grows to 2,000 lines because it handles taxes, fraud detection, shipping calculations, and inventory reservations, it violates SRP again.<br>
To solve this, enterprise applications extrapolate logic into smaller services (e.g., injecting an <code>OrderTaxService</code> and a <code>FraudDetectionService</code> into the <code>OrderService</code>). Alternatively, they use <strong>Domain Events</strong>—instead of the <code>OrderService</code> calling the Mailer directly, it simply broadcasts an event: <code>&quot;OrderPlaced&quot;</code>. The Mailer, completely separately, listens for that event and sends the email without the <code>OrderService</code> even knowing it exists!<br>
</blockquote>
<hr>
<h3>Chapter 5.3: Declarative Auto-Wiring & FormRequests</h3>
<p>
How do we validate data without cluttering the controller? We use <strong>FormRequests</strong>.
</p>
<p>
When the Router detects that a controller method requires a <code>ProductCreateRequest</code>, it intercepts the pipeline *before* the controller is executed.
</p>
<ol class="syllabus-list">
<li>The Router instantiates the <code>ProductCreateRequest</code>.</li>
<li>It executes the declarative validation rules (e.g., <code>&#039;price&#039; =&gt; &#039;numeric|min:0&#039;</code>).</li>
<li>If validation fails, it automatically throws a <code>ValidationException</code>, bouncing the user back with error messages.</li>
<li>If it succeeds, the beautifully clean, perfectly validated <code>ProductCreateRequest</code> object is injected straight into the Controller's method signature (Method Injection).</li>
</ol>
<p>
The Controller never even sees invalid data. It operates with absolute trust.
</p>
</div>
<div class="chapter-module" id="module-6-data-persistence-multi-tenancy"><h2 class="chapter-title">Module 6: Data Persistence & Multi-Tenancy</h2>
<h3>Chapter 6.1: The Repository Pattern - Protecting the Database</h3>
<h4>Subject & Intent: The SQL Quarantine Zone</h4>
<p>
In legacy applications, you often find raw SQL queries (<code>SELECT * FROM orders WHERE ...</code>) scattered everywhere. They are in the controllers, in the templates, and in the services.
</p>
<p>
This creates three massive problems:
</p>
<ol class="syllabus-list">
<li><strong>Vendor Lock-in:</strong> If you have MySQL-specific queries hardcoded in 500 different files, migrating to PostgreSQL is nearly impossible.</li>
<li><strong>Duplication:</strong> You will inevitably write the same "Find user by ID" query in dozens of different places.</li>
<li><strong>Security (Our biggest concern):</strong> If SQL is everywhere, it is incredibly easy for a developer to accidentally forget to add a critical <code>WHERE</code> clause, exposing data they shouldn't.</li>
</ol>
<p>
In the Magma framework, we use the <strong>Repository Pattern</strong>.
</p>
<p>
A Repository acts as an intermediary collection. To the <code>OrderService</code>, the <code>OrderRepository</code> just looks like an array in memory. The Service says *"Give me Order #5"*, and the Repository goes and gets it. The Service has no idea if the order came from a MySQL database, a JSON file, or an external API.
</p>
<p>
<strong>We enforce a strict rule: 100% of SQL must live inside a Repository class. SQL is illegal everywhere else.</strong>
</p>
<h4>The Theory: The Contract of the Repository</h4>
<p>
Because we want to protect against Vendor Lock-in (Problem 1), we don't just inject a concrete Repository into our Service. We inject an <strong>Interface</strong>.
</p>
<pre><code>namespace core\interfaces;

interface OrderRepositoryInterface 
{
    public function findById(int $orderId): ?array;
    public function createOrder(int $productId, int $quantity): int;
}
</code></pre>
<p>
By defining this contract, our <code>OrderService</code> knows *what* it can ask for, without caring *how* it gets done.
</p>
<h4>File Walkthrough: The Concrete Implementation</h4>
<p>
Now, let's look at the actual class that executes the SQL, located in <code>magma/repositories/PostgresOrderRepository.php</code>.
</p>
<p>
Notice how we inject the generic <code>DatabaseInterface</code> into the Repository!
</p>
<pre><code>namespace magma\repositories;

use core\interfaces\OrderRepositoryInterface;
use core\interfaces\DatabaseInterface;

class PostgresOrderRepository implements OrderRepositoryInterface 
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db) 
    {
        $this-&gt;db = $db;
    }

    public function findById(int $orderId): ?array 
    {
        // This is the ONLY place in the app where this SQL exists.
        $sql = &quot;SELECT * FROM orders WHERE id = :id LIMIT 1&quot;;
        
        $result = $this-&gt;db-&gt;fetchOne($sql, [&#039;id&#039; =&gt; $orderId]);
        return $result ?: null;
    }

    public function createOrder(int $productId, int $quantity): int 
    {
        // ... implementation
    }
}
</code></pre>
<h4>Analyzing the Principles: The Multi-Tenancy Shield</h4>
<p>
Let's return to the most critical business requirement of the Magma framework: <strong>Multi-Tenancy</strong> (supporting multiple vendors on one platform).
</p>
<p>
If we share one database table for *all* vendors, the biggest risk is that "Vendor A" logs in and accidentally sees "Vendor B's" orders. This is a catastrophic failure.
</p>
<p>
If developers are allowed to write SQL in controllers or services, it is almost guaranteed that someone will write <code>SELECT * FROM orders</code> and forget to add <code>WHERE vendor_id = 1</code>.
</p>
<p>
Because we use the Repository Pattern, we can enforce Multi-Tenancy centrally. We can inject the <code>TenantContext</code> directly into the Repository, and the Repository can *automatically* append the <code>vendor_id</code> to every single query it runs!
</p>
<p>
The developer writing the <code>OrderService</code> literally cannot make a mistake and query another vendor's data, because the Repository intercepts and scopes the query automatically.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Our <code>OrderService</code> requires an <code>OrderRepositoryInterface</code>. If we want to unit-test the <code>OrderService</code> without actually connecting to a real database, how does the Repository Pattern allow us to do that? Do we just write test SQL?</strong><br>
<br>
<strong>A:</strong> We don't write any SQL to test it at all! Because the Service only requires the <code>Interface</code>, we can write a fake class (like an <code>InMemoryOrderRepository</code>) that simply returns hardcoded PHP arrays. We inject that fake class into our Service during the test, completely bypassing the database layer. The Service never knows the difference, and our tests run instantly.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: If a new privacy law passes requiring us to "soft delete" orders (mark them as <code>deleted = 1</code> rather than actually dropping the row from the database), how many files in our codebase would we have to modify to ensure that <code>findById()</code> no longer returns deleted orders?</strong><br>
<br>
<strong>A:</strong> Just one: The Repository! Because all SQL is quarantined inside the repository, we update the query there, and every Service and Controller across the entire application instantly respects the new privacy law.<br>
</blockquote>
<hr>
<h3>Chapter 6.2: Tenant Context - The Invisible Shield</h3>
<h4>Subject & Intent: The Greatest Risk in SaaS</h4>
<p>
As we mentioned in Chapter 6.1, the absolute greatest risk when building a Multi-Tenant platform (where many vendors share one database) is <strong>Cross-Tenant Data Leakage</strong>.
</p>
<p>
If "Sandbox Corp" logs into their dashboard, and due to a coding error, they see "Client B's" orders, you have a massive legal and security breach on your hands.
</p>
<p>
If we rely on developers to manually type <code>WHERE vendor_id = X</code> in every single repository method they ever write, human error *will* eventually cause a data leak. We need a systematic, invisible shield that protects the data automatically.
</p>
<h4>The Theory: The Context Object</h4>
<p>
To build this shield, we use a concept called a <strong>Context Object</strong>.
</p>
<p>
When a user logs into the application, or when we determine which vendor's subdomain is currently being accessed (e.g., <code>client.sandboxplatform.com</code>), our early Middleware creates a <code>TenantContext</code> object.
</p>
<p>
This object holds the immutable ID of the current vendor.
</p>
<pre><code>namespace core\context;

class TenantContext 
{
    private int $vendorId;

    public function __construct(int $vendorId) 
    {
        $this-&gt;vendorId = $vendorId;
    }

    public function getVendorId(): int 
    {
        return $this-&gt;vendorId;
    }
}
</code></pre>
<h4>File Walkthrough: Injecting the Shield</h4>
<p>
The magic happens when we combine this <code>TenantContext</code> with the Dependency Injection Container and our Repositories.
</p>
<p>
Instead of passing the vendor ID around manually to every function, we inject the <code>TenantContext</code> directly into the Repository's constructor.
</p>
<pre><code>namespace magma\repositories;

use core\interfaces\OrderRepositoryInterface;
use core\interfaces\DatabaseInterface;
use core\context\TenantContext;

class PostgresOrderRepository implements OrderRepositoryInterface 
{
    private DatabaseInterface $db;
    private TenantContext $tenant;

    public function __construct(DatabaseInterface $db, TenantContext $tenant) 
    {
        $this-&gt;db = $db;
        $this-&gt;tenant = $tenant;
    }

    public function getLatestOrders(): array 
    {
        // The developer doesn&#039;t have to &#039;remember&#039; the vendor ID.
        // It is inherently part of the repository&#039;s state!
        
        $sql = &quot;SELECT * FROM orders WHERE vendor_id = :vendor_id ORDER BY created_at DESC&quot;;
        
        return $this-&gt;db-&gt;fetchAll($sql, [
            &#039;vendor_id&#039; =&gt; $this-&gt;tenant-&gt;getVendorId()
        ]);
    }
}
</code></pre>
<h4>Analyzing the Principles: Secure by Default</h4>
<p>
Why is this architecture superior?
</p>
<ol class="syllabus-list">
<li><strong>Secure by Default:</strong> The <code>OrderService</code> simply calls <code>$this-&gt;orderRepo-&gt;getLatestOrders()</code>. The Service does not know about the <code>vendor_id</code>. The Repository handles it automatically. The data is fundamentally isolated at the lowest level.</li>
<li><strong>Immutability:</strong> Because the <code>TenantContext</code> is injected via the constructor, a malicious or buggy script cannot easily "swap" the tenant ID halfway through execution. The repository is locked to that tenant for the duration of the request.</li>
<li><strong>No Global State:</strong> We didn't use <code>$_SESSION[&#039;vendor_id&#039;]</code> inside the repository. Using the session superglobal inside a repository would make it impossible to use that repository in a background cron job (which doesn't have a session). By injecting a formal <code>TenantContext</code> object, our cron job can just manually create a <code>new TenantContext(1)</code> and use the exact same secure repository.</li>
</ol>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: We write a terminal command that runs every night at midnight to generate a sales report for *all* vendors on the platform. If our Repositories are strictly locked to a single injected <code>TenantContext</code>, how would a single script generate reports for multiple different vendors?</strong><br>
<br>
<strong>A:</strong> The script would write a loop to fetch each tenant. For every iteration of the loop, it creates a *brand new* <code>TenantContext</code> object and uses it to build a new repository instance specifically for that tenant. We don't try to use a "master key" or bypass the security; we just sequentially put on the "hat" of each vendor, ensuring the strict data isolation is never broken.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Some ORMs use "Global Scopes," where the framework magically intercepts the SQL behind the scenes and adds <code>WHERE vendor_id = 1</code> before it hits the database. Based on our philosophy in Module 1, why might we prefer explicitly writing the <code>WHERE</code> clause in the Repository over using a framework's magical global scope?</strong><br>
<br>
<strong>A:</strong> You have to know what is going on behind the scenes to properly code new functions. Relying on "magic" can backfire entirely if you don't actually know where the data you are receiving is coming from, or if you encounter an edge case where you *need* to bypass the magic but can't figure out how. By explicitly injecting the context and writing the <code>WHERE</code> clause in the repository, the mechanism is completely transparent, readable, and debuggable.<br>
</blockquote>
<hr>
<h3>Chapter 6.3: The Evolution to CQRS & SERIALIZABLE ACID Compliance</h3>
<p>
The most profound architectural evolution in the Magma Framework is its advanced approach to persistence. As systems scale, a unified Repository often becomes a bottleneck. Magma evolved to use <strong>CQRS (Command Query Responsibility Segregation)</strong>.
</p>
<p>
In Magma, read operations and write operations are physically and conceptually segregated:
</p>
<ul class="syllabus-list">
<li><strong>AbstractQueryRepository:</strong> Used purely for fetching data. It is injected with a <strong>Read-Replica</strong> PDO connection. It returns Data Transfer Objects (DTOs), preventing any accidental writes or lazy-loading side effects in the presentation layer.</li>
<li><strong>AbstractCommandRepository:</strong> Used purely for mutations (Insert, Update, Delete). It is injected with the <strong>Write-Master</strong> database connection.</li>
</ul>
<h4>Extreme ACID Compliance (Eliminating Phantom Reads)</h4>
<p>
Data integrity is the absolute highest priority. PostgreSQL defaults to the <code>READ COMMITTED</code> isolation level. While fast, it is vulnerable to phantom reads under extreme concurrency.
</p>
<p>
Magma's <code>DatabaseTransactionManager</code> forces the connection into <code>SET TRANSACTION ISOLATION LEVEL SERIALIZABLE</code>. This mathematically guarantees that concurrent transactions behave as if they were executed sequentially, completely eliminating race conditions.
</p>
<h4>Write-Master Redirection</h4>
<p>
Crucially, when a transaction begins, the <code>DatabaseTransactionManager</code> intercepts the read-replica connection and routes *all* active queries during that transaction to the write-master. This avoids replication-lag bugs! If you create a user on the master and immediately query them from a replica inside the same transaction, they might not exist yet. Dynamic routing solves this.
</p>
<h4>The Liskov Substitution Principle (LSP) Firewall</h4>
<p>
If an abstract base class declares <code>protected function update(string $table, array $data)</code>, any concrete subclass trying to implement a domain interface with <code>public function update(int $id, array $data)</code> will crash PHP with a signature mismatch.
</p>
<p>
Magma solves this by strictly segregating internal framework methods (e.g., <code>executeInsert</code>) from common domain CRUD terminologies. The base classes act as an <strong>LSP Firewall</strong>, ensuring domain repositories can freely implement <code>create()</code>, <code>update()</code>, and <code>delete()</code> without colliding with the underlying SQL engines.
</p>
</div>
<div class="chapter-module" id="module-7-views-and-the-template-engine"><h2 class="chapter-title">Module 7: Views and the Template Engine</h2>
<h3>Chapter 7.1: The Final Boundary - HTML & Logic</h3>
<h4>Subject & Intent: The Sin of "Spaghetti Code"</h4>
<p>
If you look at PHP written 15 years ago, you will almost certainly find files that look like this:
</p>
<pre><code>&lt;!-- The Bad Old Days (Spaghetti Code) --&gt;
&lt;html&gt;
&lt;body&gt;
    &lt;h1&gt;Latest Modules&lt;/h1&gt;
    &lt;?php
        $db = new PDO(&quot;mysql:host=localhost;dbname=sandbox&quot;, &quot;root&quot;, &quot;password&quot;);
        $stmt = $db-&gt;query(&quot;SELECT * FROM modules&quot;);
        while ($module = $stmt-&gt;fetch()) {
            if ($module[&#039;price&#039;] &gt; 20) {
                echo &quot;&lt;div class=&#039;expensive&#039;&gt;&quot; . $module[&#039;name&#039;] . &quot;&lt;/div&gt;&quot;;
            } else {
                echo &quot;&lt;div class=&#039;cheap&#039;&gt;&quot; . $module[&#039;name&#039;] . &quot;&lt;/div&gt;&quot;;
            }
        }
    ?&gt;
&lt;/body&gt;
&lt;/html&gt;
</code></pre>
<p>
This is called <strong>Spaghetti Code</strong> because the HTML (View), the Database Connection (Repository), and the Business Logic (Checking if the price is > 20) are all tangled together in one massive, unreadable knot.
</p>
<p>
If a front-end designer wants to change the CSS class from <code>expensive</code> to <code>premium</code>, they have to open a file full of SQL queries and risk breaking the entire application.
</p>
<p>
In the Magma Framework framework, we fiercely enforce <strong>Separation of Concerns</strong>. The View layer is the absolute final boundary.
</p>
<h4>The Theory: Dumb Views</h4>
<p>
Our philosophy is that <strong>Views should be incredibly "dumb".</strong>
</p>
<p>
A View is simply an HTML template with "holes" cut out of it. It does not calculate anything. It does not query the database. It is entirely passive. It simply waits for the Controller to hand it a pre-packaged array of variables, and then it blindly loops through those variables and outputs them into the HTML holes.
</p>
<p>
If there is a business rule (e.g., "Is this module considered expensive?"), that rule belongs in the <code>PricingService</code> or the <code>CakeEntity</code> itself, *never* in the View.
</p>
<h4>File Walkthrough: Passing Data to the View</h4>
<p>
Let's trace how data actually reaches the View. Remember our Controller from Module 5? Let's look at it again, focusing on the return statement:
</p>
<pre><code>namespace magma\controllers;

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
        $modules = $this-&gt;orderService-&gt;getAvailableCakes();

        // 2. Render the View, passing the data as a clean array!
        $html = $this-&gt;view-&gt;render(&#039;storefront/index.html.php&#039;, [
            &#039;modules&#039; =&gt; $modules,
            &#039;pageTitle&#039; =&gt; &#039;Welcome to Sandbox Corp&#039;
        ]);

        // 3. Return the fully baked HTML inside the HTTP Response
        return new Response($html);
    }
}
</code></pre>
<p>
Notice that the Controller does not echo the HTML! It asks the <code>ViewRenderer</code> to compile the HTML string using the provided data, and then it wraps that compiled string in the standard <code>Response</code> object.
</p>
<h4>The Template Engine (The Compiler)</h4>
<p>
What does that <code>storefront/index.html.php</code> file actually look like?
</p>
<p>
In our framework, we use a basic, native PHP templating engine. We intentionally limit what PHP functions can be used inside these template files.
</p>
<pre><code>&lt;!-- magma/views/storefront/index.html.php --&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;&lt;?= htmlspecialchars($pageTitle) ?&gt;&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;Menu&lt;/h1&gt;
    
    &lt;ul&gt;
        &lt;!-- The view only loops and displays. No logic! --&gt;
        &lt;?php foreach ($modules as $module): ?&gt;
            &lt;li&gt;
                &lt;?= htmlspecialchars($module-&gt;getName()) ?&gt; - 
                £&lt;?= number_format($module-&gt;getPrice(), 2) ?&gt;
            &lt;/li&gt;
        &lt;?php endforeach; ?&gt;
    &lt;/ul&gt;
&lt;/body&gt;
&lt;/html&gt;
</code></pre>
<h4>Analyzing the Principles: The XSS Vulnerability</h4>
<p>
You might have noticed something critical in the template above: we wrap everything we output in <code>htmlspecialchars()</code>.
</p>
<p>
This is the most critical security rule of the View layer. <strong>Cross-Site Scripting (XSS)</strong> occurs when an attacker types malicious JavaScript into a form (e.g., setting their username to <code>&lt;script&gt;stealCookies();&lt;/script&gt;</code>). If the View layer blindly outputs that username directly into the HTML using <code>echo $username;</code>, the browser will execute the attacker's script!
</p>
<p>
By wrapping every variable in <code>htmlspecialchars()</code>, we neutralize the threat. It converts the literal <code>&lt;</code> bracket into the safe HTML entity <code>&amp;lt;</code>, rendering it harmless text on the screen.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine Sandbox Corp hires a junior Front-End Web Designer who only knows HTML and CSS, and does not know PHP. How does enforcing the "Dumb View" architecture make their job significantly safer and easier compared to the "Spaghetti Code" era?</strong><br>
<br>
<strong>A:</strong> The data received is sanitized and empty of any logic that might harm or break the app. It provides simply the necessary information, ready for the view. The designer can just write HTML without worrying about accidentally deleting a database table or breaking complex backend routines.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: The business wants to display a red "SALE!" badge next to any module that costs less than £10. A developer wants to write <code>&lt;?php if ($module-&gt;getPrice() &lt; 10) { echo &quot;&lt;span class=&#039;sale&#039;&gt;SALE!&lt;/span&gt;&quot;; } ?&gt;</code> directly into the <code>index.html.php</code> View file. Why does this violate the "Dumb View" philosophy, and where *should* that "less than 10" logic actually live?</strong><br>
<br>
<strong>A:</strong> Business logic should not be in the View. However, it shouldn't be in the Controller either! (Controllers are just Traffic Cops). That logic belongs deep in the <strong>Service Layer</strong> or the <strong>Domain Model</strong>. For instance, the <code>Module</code> object itself should have a method <code>$module-&gt;isOnSale()</code>. The View simply asks <code>if ($module-&gt;isOnSale())</code>—keeping the actual calculation ("is it less than £10?") safely hidden in the backend.<br>
</blockquote>
<hr>
<h3>Chapter 7.2: Multi-Directory Fallback & Resolution Caching</h3>
<p>
Large SaaS applications store views across multiple directory structures (<code>views/layouts</code>, <code>views/partials</code>, <code>modules/Inventory/views</code>).
</p>
<p>
Magma's <code>TemplateEngine</code> intelligently falls back across these directories to resolve layouts. However, under heavy load, checking <code>file_exists()</code> across multiple directories for every partial creates severe disk I/O bottlenecks.
</p>
<p>
To prevent this, the resolution paths are cached in-memory. The engine guarantees that the disk is only queried once per layout or partial per request lifecycle.
</p>
<h3>Chapter 7.3: Big-O DOM Interpolation Optimization</h3>
<p>
When parsing highly nested templates or loops, standard DOM interpolation suffers from O(N*M) Big-O time complexity as the engine redundantly scans child nodes.
</p>
<p>
Magma's frontend JavaScript <code>TemplateEngine</code> solves this by temporarily detaching nested <code>[data-loop]</code> nodes via comment placeholders before evaluating outer directives. This flattens the execution curve to strict O(N) complexity, allowing massive, data-heavy UIs to render instantly.
</p>
</div>
<div class="chapter-module" id="module-8-error-handling-logging"><h2 class="chapter-title">Module 8: Error Handling & Logging</h2>
<h3>Chapter 8.1: Failing Gracefully</h3>
<h4>Subject & Intent: The White Screen of Death</h4>
<p>
In default PHP, when a fatal error occurs (like trying to connect to a database that is offline), the application either prints the raw, ugly stack trace to the browser (revealing your directory structure and sometimes passwords to the user!), or it fails silently and returns a completely blank white page ("The White Screen of Death").
</p>
<p>
Both of these are unacceptable in a professional application.
</p>
<p>
In the Magma Framework framework, we enforce a global <strong>Exception Handler</strong>.
</p>
<h4>The Theory: Catching Everything</h4>
<p>
Instead of sprinkling <code>try/catch</code> blocks randomly throughout every single file, we register a global listener at the very start of the application lifecycle (inside <code>bootstrap.php</code>).
</p>
<p>
If an Exception is thrown anywhere in the application—whether deep inside a Repository or high up in a Middleware—and it is *not* caught by the local code, it bubbles all the way up to our global Error Handler.
</p>
<h4>File Walkthrough: The Error Handler</h4>
<p>
Here is a simplified version of our core Error Handler:
</p>
<pre><code>namespace core\error;

use Throwable;
use core\http\Response;

class ErrorHandler 
{
    public function __construct(private LoggerInterface $logger) {}

    public function handle(Throwable $e): void 
    {
        // 1. ALWAYS log the exact technical error securely.
        $this-&gt;logger-&gt;error($e-&gt;getMessage() . &quot;\n&quot; . $e-&gt;getTraceAsString());

        // 2. Decide what to show the user based on the Environment
        if (ENVIRONMENT === &#039;development&#039;) {
            // In Dev: Show the ugly, detailed stack trace so we can fix it!
            $response = new Response(&quot;&lt;h1&gt;Fatal Error&lt;/h1&gt;&lt;pre&gt;&quot; . $e-&gt;getTraceAsString() . &quot;&lt;/pre&gt;&quot;, 500);
        } else {
            // In Production: NEVER show technical details. Show a polite apology.
            $response = new Response(&quot;&lt;h1&gt;Whoops!&lt;/h1&gt;&lt;p&gt;Something went wrong on our end. We&#039;ve been notified.&lt;/p&gt;&quot;, 500);
        }

        // 3. Send the final response
        $response-&gt;send();
        exit(); // Immediately halt all further execution
    }
}
</code></pre>
<h4>Analyzing the Principles: Security and UX</h4>
<p>
This architecture perfectly separates concerns based on the environment.
</p>
<p>
By tying the output to the <code>ENVIRONMENT</code> constant (which we defined way back in <code>index.php</code>), we ensure that an attacker can never purposefully crash the site just to read the stack trace to find vulnerabilities.
</p>
<p>
Simultaneously, we ensure that the development team gets notified immediately via the <code>LoggerInterface</code>. Because we are injecting a <code>LoggerInterface</code>, we can swap where those logs go! In development, it might write to a text file. In production, it might send a Slack message or an alert to a service like Sentry or Datadog.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: If we didn't have a global Error Handler configured in <code>bootstrap.php</code>, and a developer forgot to write a <code>try/catch</code> block around a database query that failed, what are the *two* completely different ways the application might behave depending on the server's default configuration, and why are *both* terrible for a production site?</strong><br>
<br>
<strong>A:</strong> It could show a full stack trace which looks ugly and presents a massive security risk (exposing file paths and potentially credentials), or it could show the White Screen of Death that tells the user absolutely nothing, providing a terrible User Experience (UX).<br>
</blockquote>
<hr>
</div>
<div class="chapter-module" id="module-9-the-final-polish-dtos-data-transfer-objects"><h2 class="chapter-title">Module 9: The Final Polish - DTOs (Data Transfer Objects)</h2>
<h3>Chapter 9.1: Crossing the Boundary Safely</h3>
<h4>Subject & Intent: The Strict Typing Friction</h4>
<p>
Let's return to a problem we discussed in Pillar 4 (Strict Typing).
</p>
<p>
Our <code>OrderService</code> is incredibly strict:
<br>
<code>public function placeOrder(int $productId, int $quantity): bool</code>
</p>
<p>
But the web browser submits forms using text. The <code>Request</code> object catches this text. If we just pull the text out and pass it to the service, the application crashes because <code>&quot;5&quot;</code> (string) is not <code>5</code> (integer).
</p>
<p>
We need a translator.
</p>
<h4>The Theory: The DTO</h4>
<p>
A Data Transfer Object (DTO) is a simple, dumb class. Its only job is to take the messy, untyped data from the outside world, validate it, and convert it into a strict, strongly-typed object that our core business logic can safely consume.
</p>
<h4>File Walkthrough: The Bouncer</h4>
<p>
Let's look at <code>magma/dto/OrderRequestDTO.php</code>:
</p>
<pre><code>namespace magma\dto;

class OrderRequestDTO 
{
    public readonly int $productId;
    public readonly int $quantity;

    // The constructor forces strict typing immediately!
    public function __construct(int $productId, int $quantity) 
    {
        if ($quantity &lt;= 0) {
            throw new \InvalidArgumentException(&quot;Quantity must be at least 1.&quot;);
        }

        $this-&gt;productId = $productId;
        $this-&gt;quantity = $quantity;
    }

    // A static factory method to build the DTO from the messy Request
    public static function fromRequest(\core\http\Request $request): self 
    {
        return new self(
            $request-&gt;getPostInt(&#039;product_id&#039;),
            $request-&gt;getPostInt(&#039;quantity&#039;)
        );
    }
}
</code></pre>
<p>
Now, look at how beautiful and safe our Controller becomes:
</p>
<pre><code>public function process(Request $request): Response 
{
    try {
        // 1. The DTO translates the messy request into a strict object
        $orderData = OrderRequestDTO::fromRequest($request);

        // 2. The Service now only accepts the strict DTO!
        $this-&gt;orderService-&gt;placeOrder($orderData);

        return new Response(&quot;Success!&quot;);
    } catch (\InvalidArgumentException $e) {
        // The DTO validation failed! (e.g. quantity was 0)
        return new Response($e-&gt;getMessage(), 400);
    }
}
</code></pre>
<p>
By using DTOs, our <code>OrderService</code> never has to worry about receiving a string when it expected an integer. The DTO acts as the bouncer at the club, ensuring only properly dressed data gets through the door.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Our <code>OrderRequestDTO</code> has a validation check: <code>if ($quantity &lt;= 0) throw Exception;</code>. Why is it architecturally better to put this specific validation inside the DTO, rather than putting it inside the <code>OrderService</code> or the <code>CheckoutController</code>?</strong><br>
<br>
<strong>A:</strong> The DTO's job is to make sure everything matches the required type and format. By keeping "grammar" validation (like ensuring a quantity is a positive number, or an email looks like an email) inside the DTO, the Service can focus purely on cleaner *business logic* (like checking if we actually have enough flour in stock to fulfill that quantity).<br>
</blockquote>
<hr>
<h3>Chapter 9.2: Engine-Enforced Immutability</h3>
<p>
If a developer accidentally mutates a DTO mid-flight (e.g., <code>$dto-&gt;price = 0;</code>), it can introduce catastrophic, hard-to-trace bugs.
</p>
<p>
Magma eliminates this entirely by utilizing PHP 8.2's native <code>readonly class</code> modifiers for all DTOs.
</p>
<p>
Once a DTO is instantiated, its properties are locked down perfectly at the engine layer. Any attempt to modify a property will throw a fatal engine error. This prevents rogue scripts or dynamic property injections from mutating state mid-flight, eliminating an entire class of side-effect bugs.
</p>
</div>
<div class="chapter-module" id="module-10-the-evolution-domain-driven-design-ddd"><h2 class="chapter-title">Module 10: The Evolution - Domain-Driven Design (DDD)</h2>
<h3>Chapter 10.1: Transaction Scripts vs. Rich Domain Models</h3>
<h4>Subject & Intent: The Breaking Point of Services</h4>
<p>
In Module 5, we introduced the <strong>Service Layer</strong> and the <strong>Transaction Script Pattern</strong>. We built an <code>OrderService</code> that was incredibly smart: it pulled primitive data from the database, performed all calculations, and saved data back.
</p>
<p>
The data itself (the modules, the orders) were just "dumb" arrays or basic objects without any methods. They were purely structural.
</p>
<p>
While Transaction Scripts are excellent for medium-sized applications, as Magma Framework scaled into an enterprise platform, we hit a breaking point. The Services became bloated "God Classes" because they held *all* the rules. We needed to evolve the architecture.
</p>
<p>
To solve this, our framework migrated to the current standard: <strong>Domain-Driven Design (DDD)</strong> and the creation of <strong>Rich Domain Models</strong>. This is how the Magma framework operates today.
</p>
<h4>The Theory: Behavior Belongs to the Data</h4>
<p>
In a Rich Domain Model, the objects that represent your business (Entities) are no longer "dumb." They contain the business logic that directly pertains to them.
</p>
<p>
<strong>The Transaction Script Way (Dumb Data, Smart Service):</strong>
</p>
<pre><code>// The Service holds all the knowledge
class PricingService 
{
    public function applyDiscount(OrderData $order): void 
    {
        if ($order-&gt;total &gt; 100) {
            $order-&gt;discount = 10;
            $order-&gt;total = $order-&gt;total - 10;
        }
    }
}
</code></pre>
<p>
<strong>The Rich Domain Model Way (Smart Data, Thin Service):</strong>
</p>
<pre><code>// The Entity protects its own state and knows its own rules!
class Order 
{
    private float $total;
    private float $discount = 0;

    // The order object itself decides how discounts are applied
    public function applyLoyaltyDiscount(): void 
    {
        if ($this-&gt;total &gt; 100) {
            $this-&gt;discount = 10;
            $this-&gt;total -= 10;
        }
    }
    
    // We cannot change the total directly from the outside. 
    // We must use the entity&#039;s methods.
}

// The Service becomes just an Orchestrator again
class OrderService 
{
    public function finalizeCheckout(int $orderId): void 
    {
        $order = $this-&gt;orderRepository-&gt;find($orderId);
        
        // The service just tells the order to apply the discount.
        // It doesn&#039;t know *how* the discount is calculated!
        $order-&gt;applyLoyaltyDiscount(); 
        
        $this-&gt;orderRepository-&gt;save($order);
    }
}
</code></pre>
<h4>Analyzing the Principles: Encapsulation</h4>
<p>
Rich Domain Models perfectly embody the Object-Oriented principle of <strong>Encapsulation</strong>.
</p>
<p>
An <code>Order</code> object shouldn't let a random <code>Service</code> change its <code>total</code> property manually. If the <code>total</code> changes, the <code>Order</code> needs to ensure taxes are recalculated and statuses are updated. By forcing the outside world to call <code>$order-&gt;applyLoyaltyDiscount()</code>, the <code>Order</code> object protects its internal state from becoming corrupted by a buggy Service.
</p>
<h4>The Migration Path: "Pragmatic DDD" (The Hybrid Approach)</h4>
<p>
Why didn't we start with a full, strict Rich Domain Model in the Magma framework?
</p>
<p>
Because strict Enterprise DDD requires you to completely and perfectly map your business rules (the Ubiquitous Language) *before* you write the code. When a project is young, business rules are still evolving. Trying to build a rigid Domain Model too early leads to Analysis Paralysis.
</p>
<p>
Instead, we use <strong>Pragmatic DDD</strong> as our architectural standard as we build out the site's functions:
</p>
<ol class="syllabus-list">
<li><strong>Build Entities As You Go:</strong> When you interact with an Order, you create an <code>Order</code> object. At first, it might just hold data. But the moment you need to change its state, you put the logic *inside* the <code>Order</code> class, not the Service.</li>
<li><strong>Behavior Belongs With The Data:</strong> If you need to map an incoming array into a format for the database, or if you need to calculate a tax rate, that logic belongs inside the Entity.</li>
<li><strong>Services Remain Orchestrators:</strong> Services are forbidden from making decisions about a data object's internal state. The Service's only job is to fetch the Entity, tell the Entity to do something (<code>$order-&gt;markAsPaid()</code>), and save the Entity.</li>
</ol>
<p>
By using this hybrid approach, we prevent our Services from becoming bloated "God Classes", while retaining the flexibility to build our Domain Models organically as we discover what the business actually needs.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: A customer is trying to apply a promo code to their <code>Order</code> entity. To know if the code is valid, we must query the <code>promotions</code> table in the database. In Pragmatic DDD, should the <code>Order</code> entity receive the database connection to run the SQL query itself, or should the <code>CheckoutService</code> handle the database query and pass the result into the <code>Order</code> entity?</strong><br>
<br>
<strong>A:</strong> The <code>Order</code> entity absolutely must run its own logic, but the <code>CheckoutService</code> must remain the orchestrator that touches the database! If we give the <code>Order</code> entity a database connection, it becomes tightly coupled to our infrastructure (the "Active Record" anti-pattern). Instead, the Service runs the SQL (via a Repository), fetches the pure data (like a <code>PromoCode</code> entity), and passes that pure data into the <code>Order</code> entity (<code>$order-&gt;applyPromo($promoCode)</code>). The service runs the SQL but doesn't care what the data is; the data is passed to the one that does care—the entity.<br>
</blockquote>
<hr>
<h3>Chapter 10.2: 100% Pure Domain Entities</h3>
<p>
Many architectures corrupt their domain models by passing HTTP-specific objects (like a <code>Request</code> or a framework-specific DTO) directly into them.
</p>
<p>
Magma enforces <strong>100% Pure Domain Entities</strong>.
</p>
<p>
Entities are completely agnostic of the application layer. They only accept raw scalars (strings, integers, booleans) or other pure domain Value Objects in their constructors. A <code>Product</code> entity has no idea that it lives on a web server, that it is being saved to PostgreSQL, or that an HTTP request triggered its creation.
</p>
</div>
<div class="chapter-module" id="module-11-decoupling-with-event-driven-architecture"><h2 class="chapter-title">Module 11: Decoupling with Event-Driven Architecture</h2>
<h3>Chapter 11.1: The Pub/Sub Pattern (Publish/Subscribe)</h3>
<h4>Subject & Intent: Breaking Apart the "God Class"</h4>
<p>
Even if we move business rules into Domain Models (Module 10), our Services can still become tangled. When a user places an order, the <code>CheckoutService</code> orchestrates the flow. But what happens if completing an order requires sending an email, deducting inventory, awarding loyalty points, notifying the fulfillment center, and updating the accounting ledger?
</p>
<p>
If the <code>CheckoutService</code> explicitly calls five different systems, it is tightly coupled to all of them.
</p>
<p>
To solve this, enterprise applications use <strong>Event-Driven Architecture</strong>.
</p>
<h4>The Theory: Shouting into the Void</h4>
<p>
Instead of the <code>CheckoutService</code> explicitly telling the Mailer to send an email, it simply announces to the system: *"Hey everyone, an order was just completed!"* (Publish).
</p>
<p>
Other systems in the application (like the Mailer or the Inventory system) "listen" for that announcement and react accordingly (Subscribe). The <code>CheckoutService</code> doesn't know who is listening, and it doesn't care.
</p>
<h4>The Synergy: Dispatching Rich Domain Models</h4>
<p>
This pattern becomes exceptionally powerful when combined with our <strong>Pragmatic DDD</strong> approach (Module 10).
</p>
<p>
When our application was using simple Transaction Scripts, an event might look like <code>new OrderPlacedEvent([&#039;order_id&#039; =&gt; 5, &#039;total&#039; =&gt; 100])</code>. The listeners would have to parse arrays.
</p>
<p>
Now, because we have Rich Domain Entities, the Event simply carries the entity itself:
<br>
<code>$dispatcher-&gt;dispatch(new OrderPlacedEvent($order))</code>
</p>
<p>
Because the <code>$order</code> entity already knows how to calculate its taxes, validate its status, and format its data, any Listener (like the Mailer) that catches the event instantly has access to all of those rich business rules. The Service orchestrates the flow, the Domain Model handles the rules, and the Event Dispatcher handles the side-effects.
</p>
<h4>Analyzing the Principles</h4>
<p>
By implementing an Event Dispatcher, you achieve ultimate <strong>Separation of Concerns</strong>. The checkout logic is completely decoupled from the notification logic, making it vastly easier to add new features later without touching the core checkout flow.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: In "magical" frameworks like Laravel, you can configure the framework so that any time a database row is inserted, it *magically* and invisibly fires an event behind the scenes. Based on our philosophy of avoiding "Magic", why do we actively reject this, and instead force the developer to manually type <code>$this-&gt;dispatcher-&gt;dispatch(new OrderPlacedEvent($order))</code> inside the Service?</strong><br>
<br>
<strong>A:</strong> Because we need absolute control over *what* events are fired and *when* they are fired. Magic functions are hidden and may do things we don't want them to do. For example, if we run a bulk data import script that inserts 10,000 historic orders into the database, a "magical" framework might invisible fire 10,000 "OrderPlaced" events and accidentally spam 10,000 emails to customers from five years ago! By making the dispatch explicit in our Services, the code is transparent, safe, and entirely under our control.<br>
</blockquote>
<hr>
</div>
<div class="chapter-module" id="module-12-asynchronous-background-workers"><h2 class="chapter-title">Module 12: Asynchronous Background Workers</h2>
<h3>Chapter 12.1: Keeping the Web Fast</h3>
<h4>Subject & Intent: Offloading the Heavy Lifting</h4>
<p>
You've already built the foundation for this in your application (e.g., <code>bin/worker.php</code>). When a user submits an action via the web browser, they expect an instant response. If your PHP script connects to an external API (like an SMTP email server) and that server is slow, the user is left staring at a loading spinner.
</p>
<h4>The Theory: Queues and Workers</h4>
<p>
To solve this, we use an <strong>Asynchronous Queue</strong>.
<br>
When a slow task needs to be done, the web server doesn't do it. Instead, it writes a small "Job" note (e.g., "Send a welcome email to User ID 5") and pushes it into a Redis list. Writing to Redis takes 1 millisecond. The web server immediately returns a success page to the user.
</p>
<p>
Meanwhile, a separate PHP process running continuously in the background (the Worker Daemon) constantly checks that Redis list. When it sees the note, it picks it up and spends the 3 seconds required to actually send the email.
</p>
<h4>Analyzing the Principles</h4>
<p>
This ensures the web tier remains lightning fast and completely isolated from the performance bottlenecks of third-party APIs or heavy background calculations.
</p>
<hr>
<h3>Chapter 12.2: The Transactional Outbox Pattern</h3>
<h4>The Dual-Write Problem</h4>
<p>
Synchronous background tasks kill web performance. The standard solution is to dispatch to a message queue. However, this introduces the <strong>Dual-Write Problem</strong>: What happens if the database transaction commits successfully, but the network connection to Redis fails? The system is now in an inconsistent state.
</p>
<p>
Magma completely solves this using the <strong>Transactional Outbox Pattern</strong>.
</p>
<p>
Instead of dispatching directly to a queue, the <code>OutboxJobRepository</code> records domain events *atomically* within the exact same database transaction that created the entity. If the transaction commits, the job is guaranteed to be in the database. If it rolls back, the job vanishes. Absolute consistency is guaranteed.
</p>
<h4>FOR UPDATE SKIP LOCKED</h4>
<p>
A continuous background daemon polls the outbox table to execute the jobs. If you run multiple parallel workers, they will race each other to grab the same job.
</p>
<p>
Magma relies on PostgreSQL's native <code>FOR UPDATE SKIP LOCKED</code> locking primitive. When a worker selects a job, it locks the row. If a second worker queries the table at the exact same millisecond, the database seamlessly *skips* the locked row and hands it the next available job. This guarantees exactly-once delivery with zero lock-contention CPU churn.
</p>
</div>
<div class="chapter-module" id="module-13-end-of-cycle-considerations-automated-testing"><h2 class="chapter-title">Module 13: End of Cycle Considerations - Automated Testing</h2>
<h3>Chapter 13.1: Reaping the Rewards of Architecture</h3>
<h4>Subject & Intent: Testing is a Byproduct of Design</h4>
<p>
We placed testing at the very end of this textbook because true, robust automated testing is only possible *after* you have built a clean architecture.
</p>
<p>
If you write "Spaghetti Code" where controllers contain <code>new SmtpMailer()</code> or direct <code>$_POST</code> references, unit testing is nearly impossible because you cannot isolate the code from the real world.
</p>
<p>
Because we built the Magma framework using strict <strong>Dependency Injection (Module 3)</strong> and <strong>Encapsulated Requests (Module 4)</strong>, testing becomes trivial.
</p>
<h4>The Theory: Mocks and Fakes</h4>
<p>
If you want to test the <code>OrderService</code>, you do not need a database. You simply write a test script that injects a fake <code>InMemoryOrderRepository</code> into the service. You can instantly verify the logic is flawless without ever booting up PostgreSQL. This is the ultimate validation of our architectural choices!
</p>
</div>
<div class="chapter-module" id="module-14-frontend-architecture-deep-freeze-css-layers"><h2 class="chapter-title">Module 14: Frontend Architecture: Deep Freeze & CSS Layers</h2>
<h3>Chapter 14.1: Deeply Immutable Reactive State Store</h3>
<p>
The client-side Vanilla ES6 architecture is as robust as the backend. Rather than relying on heavy frameworks like React or Vue, Magma implements a proprietary, lightweight <code>ObservableStore.js</code>.
</p>
<p>
It employs a recursive <code>_deepFreeze()</code> algorithm that physically locks deeply nested state objects from rogue frontend mutations. Developers must dispatch explicit actions to mutate state, enforcing strict unidirectional data flow.
</p>
<h3>Chapter 14.2: Defensive Garbage Collection</h3>
<p>
In dynamic single-page applications, DOM elements are frequently created and destroyed. Standard event listeners create "zombie" memory leaks.
</p>
<p>
Magma's global event delegators utilize defensive <code>isConnected</code> checks to gracefully unbind themselves if their target component is ripped from the DOM dynamically, ensuring perfect garbage collection.
</p>
<h3>Chapter 14.3: CSS Cascade Layers</h3>
<p>
Specificity wars destroy maintainability. Magma enforces native CSS Cascade Layers (<code>@layer reset, tokens, components, utilities, states;</code>). This permanently structures CSS precedence regardless of file inclusion order.
</p>
</div>
<div class="chapter-module" id="module-15-security-big-o-analytics"><h2 class="chapter-title">Module 15: Security & Big-O Analytics</h2>
<h3>Chapter 15.1: Static AST Boundary Auditing</h3>
<p>
Human code reviews are fallible. A developer might accidentally use a global <code>$_POST</code> variable inside a deeply nested Domain Service.
</p>
<p>
Magma provides a powerful static analysis linter (<code>bin/audit_schema.php</code>) that parses the <strong>Abstract Syntax Tree (AST)</strong> of the codebase. It actively verifies that:
</p>
<ol class="syllabus-list">
<li>Multi-tenant foreign keys are correctly indexed.</li>
<li>Direct superglobal usage is statically prohibited inside business services.</li>
</ol>
<p>
If boundaries are breached, the linter fails the CI/CD pipeline.
</p>
<h3>Chapter 15.2: Constant-Time B-Tree Keyset Pagination</h3>
<p>
Standard SQL pagination (<code>LIMIT 100 OFFSET 10000</code>) degrades linearly in performance (O(N)).
</p>
<p>
Magma utilizes <strong>Keyset Seeking</strong> (<code>WHERE id &gt; :cursor_last_id</code>). By leveraging B-Tree indexes, the database jumps instantly to the correct row, delivering instantaneous O(1) performance regardless of table size.
</p>
<h3>Chapter 15.3: Memory-Streaming Generators</h3>
<p>
Repositories returning large collections do not load the resulting array into memory. Instead, Magma streams the records directly from the database driver using PHP generators (<code>yield</code>). This keeps RAM consumption entirely flat, preventing OOM crashes during heavy analytical workloads.
</p>
</div>
        </div>
    </div>
</main>
        <footer class="welcome-footer">
            <p>Magma Framework &bull; Educational Architecture Core &bull; Debian Linux &bull; Strict SOLID Architecture</p>
        </footer>
    </div>
</body>
</html>