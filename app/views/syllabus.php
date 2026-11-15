<?php
/**
 * Title: Syllabus View
 *
 * Purpose:
 * - Renders the 16-module Masterclass Textbook (`/syllabus`) as a standalone HTML curriculum view.
 *
 * Teaching notes:
 * - Generated from `textbook.md` and rendered standalone without wrapping in a global application layout.
 * - Uses scoped CSS classes (`/css/app.css` and `/css/components/syllabus.css`) with zero static inline styles.
 *
 * @var array{title?: string|null} $data Encapsulated view data payload.
 */
$pageTitle = $data['title'] ?? 'Architectural Syllabus';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/components/syllabus.css">
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
        <div class="syllabus-sidebar"><details class="syllabus-toc" open><summary class="toc-summary"><h3>Chapters</h3></summary><ul><li><a href="#module-1-introduction-philosophy"><span class="toc-num">1</span> <span class="toc-title">Introduction & Philosophy</span></a></li><li><a href="#module-2-the-request-lifecycle-front-controller"><span class="toc-num">2</span> <span class="toc-title">The Request Lifecycle & Front Controller</span></a></li><li><a href="#module-3-the-dependency-injection-container-the-core"><span class="toc-num">3</span> <span class="toc-title">The Dependency Injection Container (The Core)</span></a></li><li><a href="#module-4-routing-the-http-request"><span class="toc-num">4</span> <span class="toc-title">Routing & The HTTP Request</span></a></li><li><a href="#module-5-controllers-services-the-business-logic"><span class="toc-num">5</span> <span class="toc-title">Controllers & Services (The Business Logic)</span></a></li><li><a href="#module-6-data-persistence-multi-tenancy"><span class="toc-num">6</span> <span class="toc-title">Data Persistence & Multi-Tenancy</span></a></li><li><a href="#module-7-views-and-the-template-engine"><span class="toc-num">7</span> <span class="toc-title">Views and the Template Engine</span></a></li><li><a href="#module-8-error-handling-logging"><span class="toc-num">8</span> <span class="toc-title">Error Handling & Logging</span></a></li><li><a href="#module-9-the-final-polish-dtos-data-transfer-objects"><span class="toc-num">9</span> <span class="toc-title">The Final Polish - DTOs (Data Transfer Objects)</span></a></li><li><a href="#module-10-the-evolution-domain-driven-design-ddd"><span class="toc-num">10</span> <span class="toc-title">The Evolution - Domain-Driven Design (DDD)</span></a></li><li><a href="#module-11-decoupling-with-event-driven-architecture"><span class="toc-num">11</span> <span class="toc-title">Decoupling with Event-Driven Architecture</span></a></li><li><a href="#module-12-asynchronous-background-workers"><span class="toc-num">12</span> <span class="toc-title">Asynchronous Background Workers</span></a></li><li><a href="#module-13-end-of-cycle-considerations-automated-testing"><span class="toc-num">13</span> <span class="toc-title">End of Cycle Considerations - Automated Testing</span></a></li><li><a href="#module-14-frontend-architecture-deep-freeze-css-layers"><span class="toc-num">14</span> <span class="toc-title">Frontend Architecture: Deep Freeze & CSS Layers</span></a></li><li><a href="#module-15-security-big-o-analytics"><span class="toc-num">15</span> <span class="toc-title">Security & Big-O Analytics</span></a></li><li><a href="#module-16-the-lava-hardening-phase-enterprise-quality-control"><span class="toc-num">16</span> <span class="toc-title">The Lava Hardening Phase - Enterprise Quality Control</span></a></li></ul></details></div>
        <div class="syllabus-content">
            <h1 class="syllabus-title">Magma Framework: The Masterclass Textbook</h1>
<div class="chapter-module" id="module-1-introduction-philosophy"><h2 class="chapter-title">Module 1: Introduction & Philosophy</h2>
<h3>Chapter 1.1: The Domain Context & The Platform Vision</h3>
<h4>Subject & Intent: Understanding the "Why" Before the "How"</h4>
<p>
In software engineering, code is simply a tool used to solve a specific human problem. Before we write a single line of PHP, we must intimately understand the <strong>Domain</strong>—the business environment in which our software will operate. If we build an elegant architecture that solves the wrong business problem, we have failed as engineers.
</p>
<p>
In our case, the Magma Framework is a generic, robust software platform located in the cloud. We specialize in enterprise-grade architecture, specifically distinguishing ourselves with a "no magic" approach.
</p>
<p>
This gives us immediate clues about our domain entities and data structures:
</p>
<ul class="syllabus-list">
<li><strong>Data Structures:</strong> We aren't dealing with simple, unstructured arrays. We use strict Data Transfer Objects (DTOs) and Domain Models.</li>
<li><strong>Services & Repositories:</strong> Business logic is decoupled from data access. State isn't just a database row; it's managed through strict, isolated layers.</li>
<li><strong>Requests & Responses:</strong> We are dealing with clear HTTP lifecycles, robust middleware pipelines, and structured input/output.</li>
</ul>
<p>
However, the defining characteristic of our architecture is the <strong>Platform Vision</strong>.
</p>
<p>
While a single organization might be our *first* client (our "Tenant"), our intent is to design this system from the ground up as a platform capable of supporting *multiple* distinct vendors in the future. This concept is known as <strong>Multi-Tenancy</strong>.
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
<li><strong>Agnostic Core Domain:</strong> The core application doesn't care about a specific tenant's business. It only understands abstract concepts: <code>Vendors</code>, <code>Products</code>, <code>Orders</code>, and <code>Inventory</code>. The specifics are data, not code.</li>
<li><strong>Configuration over Hardcoding:</strong> If a vendor has a specific rule, we abstract this into a configurable business rule associated with the vendor's profile, rather than burying it in <code>if/else</code> statements within our services.</li>
</ol>
<p>
<strong>Conceptual OOP Illustration: Hardcoding vs. Abstraction</strong>
</p>
<p>
Imagine a hypothetical <code>OrderService</code> where a specific vendor ("Vendor A") does not allow orders to be placed on Sundays:
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
            throw new Exception(&quot;Vendor A is closed on Sundays.&quot;);
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
In the Magma Framework architecture, we demand <strong>Explicit Dependencies</strong>. If a core controller or service needs an authentication service or cache, it must declare it explicitly in its constructor (or method signature for application/module controllers); never instantiate hidden infrastructure inside business logic.
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Modules\Reviews\controllers;

use Magma\http\Response;
use Magma\http\RedirectResponse;
use Modules\Reviews\requests\ReviewRequest;
use Modules\Reviews\interfaces\ReviewSubmissionServiceInterface;

// The Explicit &quot;Magma&quot; Way (from modules/Reviews/controllers/ReviewController.php)
class ReviewController
{
    public function submitReview(
        ReviewRequest $reviewRequest,
        \Magma\security\TenantContext $tenantContext,
        ReviewSubmissionServiceInterface $reviewSubmissionService,
        \Magma\http\SessionInterface $session
    ): Response {
        $dto = $reviewRequest-&gt;toDTO();
        $reviewSubmissionService-&gt;submit($dto, $tenantContext-&gt;getTenantId());

        $session-&gt;set(
            \App\constants\AppConstants::SESSION_SUCCESS_MESSAGE,
            \Modules\Reviews\constants\ReviewConstants::MSG_REVIEW_SUBMITTED
        );
        return new RedirectResponse(&#039;/&#039;);
    }
}
</code></pre>
<p>
<strong>The Danger of the <code>new</code> Keyword (Tight Coupling)</strong>
<br>
You asked earlier why using <code>$mailer = new SmtpMailer();</code> inside a controller is almost as bad as using a Facade.
</p>
<p>
The answer is <strong>Tight Coupling</strong>. If you write <code>new SmtpMailer()</code> directly inside your code, your controller is permanently glued to that exact SMTP class. If tomorrow the business says, "We are switching to another mail transport," you have to open the controller and rewrite the code.
</p>
<p>
If instead you injected a contract like <code>ReviewSubmissionServiceInterface</code> or <code>CacheInterface</code> (<code>Magma\interfaces\CacheInterface</code>), you would simply change the binding in your Service Provider (which we will cover in Module 3), and the controller *never has to change*. The <code>new</code> keyword prevents your code from being flexible!
</p>
<p>
<strong>The Comparison Summary:</strong>
<br>
Why do we choose explicit injection?
</p>
<ul class="syllabus-list">
<li><strong>Readability:</strong> The signature acts as an honest contract. Anyone looking at <code>ReviewController::submitReview()</code> instantly knows every subsystem it relies on.</li>
<li><strong>Testability:</strong> To test code depending on <code>CacheInterface</code>, we simply inject an in-memory <code>ArrayCache</code> instead of a live <code>RedisCache</code>. No global state or complex mocking frameworks required.</li>
<li><strong>Swappability:</strong> Because we ask for an <code>Interface</code>, we can swap <code>RedisCache</code> for <code>ArrayCache</code> in configuration without changing a single line of business logic.</li>
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

use Magma\Application;
use Magma\middleware\CsrfMiddleware;
use Magma\middleware\UTMTrackerMiddleware;
use Magma\middleware\ViewShareMiddleware;
use Magma\middleware\SessionTimeoutMiddleware;
use Magma\middleware\SecurityHeadersMiddleware;

try {
    // 1. Leave the public directory immediately
    require_once __DIR__ . &#039;/../magma/config/bootstrap.php&#039;;

    // 2. Define our environment
    define(&#039;ENVIRONMENT&#039;, \Magma\config\Config::get(&#039;APP_ENV&#039;, &#039;production&#039;));

    // 3. Resolve the application and add security layers (first in, outermost layer)
    $app = $container-&gt;get(Application::class);
    $app-&gt;addMiddleware(\Magma\middleware\TenantSecurityMiddleware::class);
    $app-&gt;addMiddleware(CsrfMiddleware::class);
    $app-&gt;addMiddleware(UTMTrackerMiddleware::class);
    $app-&gt;addMiddleware(ViewShareMiddleware::class);
    $app-&gt;addMiddleware(SessionTimeoutMiddleware::class);
    $app-&gt;addMiddleware(SecurityHeadersMiddleware::class);

    // 4. Execute the request!
    $app-&gt;run();
} catch (\Throwable $e) {
    error_log(&quot;CRITICAL BOOT FAILURE: &quot; . $e-&gt;getMessage());
    http_response_code(500);
    echo &quot;A critical system error occurred during initialization.&quot;;
    exit(1);
}
</code></pre>
<h4>Analyzing the Principles: The Public Boundary</h4>
<p>
The most crucial line inside the boot block is:
<br>
<code>require_once __DIR__ . &#039;/../magma/config/bootstrap.php&#039;;</code>
</p>
<p>
Notice the directory structure. The web server (like Nginx or Apache) is configured to serve files *only* from the <code>www/</code> directory. However, all of our actual code, business logic, and configuration files live in <code>magma/</code>, <code>app/</code>, and <code>modules/</code>, which sit completely outside the document root.
</p>
<p>
By executing that <code>require_once</code> statement, we immediately "jump" out of the public folder and into our secure application container.
</p>
<p>
This is the ultimate Separation of Concerns regarding security. Even if the web server suffers a catastrophic misconfiguration and accidentally serves <code>.php</code> files as raw text instead of executing them, an attacker could only see the contents of <code>www/index.php</code>. They cannot reach our database credentials or <code>.env</code> configuration because those files simply do not exist within the server's public path.
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
Both Laravel (<code>public/index.php</code>) and Symfony (<code>public/index.php</code>) use this exact pattern, and for good reason. It provides a centralized location to enforce rules.
</p>
<p>
Because we add <code>TenantSecurityMiddleware</code>, <code>CsrfMiddleware</code>, <code>SessionTimeoutMiddleware</code>, and <code>SecurityHeadersMiddleware</code> directly into <code>$app</code> right here at the gateway, we absolutely guarantee that no controller deep inside the application can ever be reached without passing those security checks first. If we used the old multiple-file pattern, we could never make that guarantee.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: What is the security risk of having the core application folder (e.g., <code>magma/</code> or the root <code>.env</code> file) inside the public <code>www/</code> directory?</strong><br>
<br>
<strong>A:</strong> If those files are in the public domain, the database credentials and secret keys become easily accessible. If a web server misconfiguration occurs, an attacker could navigate directly to <code>website.com/.env</code> and download your passwords as plain text. Keeping them strictly outside the document root eliminates this vector entirely.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Looking at <code>www/index.php</code>, why does it make sense to attach "global" middleware (like Tenant Security, CSRF, Session Timeout, and Security Headers) here at the entrance, rather than waiting until the Router decides which Controller to execute?</strong><br>
<br>
<strong>A:</strong> Think of it like a bag check at the entrance of the festival gates, rather than having one at every stall. By performing the security check once at the absolute perimeter, we guarantee that no malicious payload even reaches the inner workings of our application. We enforce security by default, rather than relying on individual controllers to remember to check it.<br>
</blockquote>
<hr>
<h3>Chapter 2.2: Bootstrapping the Application</h3>
<h4>Subject & Intent: Waking Up the Application</h4>
<p>
If <code>www/index.php</code> is the front door, <code>magma/config/bootstrap.php</code> is the process of turning on the lights, booting up the computers, and unlocking the cash register before the customers arrive.
</p>
<p>
When execution jumps out of the <code>www/</code> directory via that <code>require_once</code> statement, it lands in <code>magma/config/bootstrap.php</code>. This file is responsible for preparing the environment so that our business logic has everything it needs to execute.
</p>
<h4>File Walkthrough: <code>magma/config/bootstrap.php</code></h4>
<p>
Let's look at the critical sections of this file:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

// 1. The Autoloader
require_once __DIR__ . &#039;/autoload.php&#039;;

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

$container-&gt;set(Container::class, function () use ($container) {
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
    $provider-&gt;register($container);
}
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
<strong>1. The PSR-4 Autoloader</strong>
<br>
Before PHP 5.3, if you wanted to use 10 different classes in a file, you had to write 10 <code>require &#039;path/to/class.php&#039;;</code> statements at the top of your file. It was a nightmare.
<br>
Today, we use an <strong>Autoloader</strong> (specifically adhering to the PSR-4 standard, implemented in <code>magma/config/autoload.php</code>). We require the autoloader *once* here in the bootstrap file. From then on, whenever PHP sees <code>new Magma\container\Container()</code>, the autoloader intercepts it, maps the <code>Magma\</code> namespace prefix to <code>magma/</code> (and <code>App\</code> to <code>app/</code>, <code>Modules\</code> to <code>modules/</code>), and includes <code>magma/container/Container.php</code> automatically behind the scenes.
</p>
<p>
<strong>2. Centralized Configuration</strong>
<br>
<code>Config::initialize()</code> parses our hidden <code>.env</code> file (which contains our database passwords and API keys). Crucially, it loads them into a centralized <code>\Magma\config\Config</code> class (and wraps them behind <code>ConfigInterface</code> via <code>ConfigWrapper</code>). We do this early so the rest of the application can simply ask <code>Config</code> for settings, rather than interacting with the raw filesystem or server globals.
</p>
<p>
<strong>3. The Service Providers</strong>
<br>
To keep our bootstrap file from becoming 5,000 lines long, we use the <strong>Service Provider Pattern</strong>. We group our setup logic into logical chunks (<code>CoreServiceProvider</code>, <code>InfrastructureServiceProvider</code>, <code>DatabaseServiceProvider</code>, <code>RoutingServiceProvider</code>, <code>RepositoryServiceProvider</code>, <code>DomainServiceProvider</code>, <code>HttpServiceProvider</code>, <code>EventServiceProvider</code>, and <code>AppServiceProvider</code>). The bootstrap file simply loops over them and tells them to register themselves with the Dependency Injection Container.
</p>
<h4>The "Why" & Framework Comparison</h4>
<p>
Why keep <code>www/index.php</code> and <code>magma/config/bootstrap.php</code> separate? Why not just put all the bootstrap logic directly in <code>www/index.php</code>?
</p>
<p>
<strong>Testing and CLI access.</strong>
<br>
If we put all the boot logic inside <code>www/index.php</code>, we couldn't run automated tests or command-line (CLI) scripts without faking an HTTP web request. By isolating the boot sequence into <code>magma/config/bootstrap.php</code>, our web gateway (<code>www/index.php</code>), our CLI worker daemon (<code>bin/worker.php</code>), and our PHPUnit test suite on the <code>lava</code> branch can all simply <code>require &#039;magma/config/bootstrap.php&#039;</code> to wake up the application securely and consistently!
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
If we look back at the final lines of our <code>magma/config/bootstrap.php</code> file, we see this:
</p>
<pre><code>// magma/config/bootstrap.php

// The Container is instantiated
$container = new Container();

// Providers are given the container so they can register their services
foreach ($providers as $provider) {
    $provider-&gt;register($container);
}
</code></pre>
<p>
At this exact millisecond, the <code>$container</code> object holds a complete "map" of every interface in our application and the concrete class or factory closure it should use (for example, <code>TransactionManagerInterface::class</code> -> <code>DatabaseTransactionManager</code>, or <code>CacheInterface::class</code> -> <code>RedisCache</code>). We will dive deeply into *how* the container works in Module 3, but for now, just understand that it is our master factory.
</p>
<p>
Now, execution returns to <code>www/index.php</code> for the finale:
</p>
<pre><code>// www/index.php

// 1. We ask the container to build the Application Kernel for us
$app = $container-&gt;get(Application::class);

// 2. We attach our security middleware (outermost to innermost)
$app-&gt;addMiddleware(\Magma\middleware\TenantSecurityMiddleware::class);
$app-&gt;addMiddleware(CsrfMiddleware::class);
$app-&gt;addMiddleware(UTMTrackerMiddleware::class);
$app-&gt;addMiddleware(ViewShareMiddleware::class);
$app-&gt;addMiddleware(SessionTimeoutMiddleware::class);
$app-&gt;addMiddleware(SecurityHeadersMiddleware::class);

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
<li><strong>The Application Kernel (<code>magma/Application.php</code>):</strong> Resolves the HTTP Request, dispatches it through the Middleware Onion, and returns an HTTP Response. It is strictly for web traffic.</li>
<li><strong>CLI Entrypoints (<code>bin/worker.php</code>, <code>bin/outbox_publisher.php</code>, <code>bin/cache_routes.php</code>):</strong> Boot the exact same <code>magma/config/bootstrap.php</code> Dependency Container, but completely bypass the HTTP Router and HTTP Middleware. They execute long-running daemons or build-time tasks directly against domain services and repositories.</li>
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
In the Magma Framework, we use an advanced container technique called <strong>Auto-Wiring</strong> (<code>magma/container/Container.php</code>). We don't manually tell the container how to build every single class. Instead, the container uses Reflection to figure it out on the fly.
</p>
<p>
Here is a simplified example of what <code>Magma\container\Container</code> is doing inside its <code>get()</code> / <code>resolve()</code> methods when you ask it for a real framework class such as <code>Magma\controllers\LoginController</code>:
</p>
<pre><code>// You ask the container for LoginController
$container-&gt;get(\Magma\controllers\LoginController::class);
</code></pre>
<p>
Behind the scenes, the Container uses PHP's <code>ReflectionClass</code>:
</p>
<pre><code>// 1. The Container reflects upon the requested class
$reflection = new ReflectionClass(\Magma\controllers\LoginController::class);

// 2. It looks at the constructor
$constructor = $reflection-&gt;getConstructor();

// 3. It asks: &quot;What parameters do you require?&quot;
// LoginController demands: AuthenticationService, HtmlResponseBuilderInterface, PostLoginRedirectResolver
$parameters = $constructor-&gt;getParameters();

$dependencies = [];

// 4. It loops through the required parameters
foreach ($parameters as $parameter) {
    // It inspects the type hint (e.g., AuthenticationService::class)
    $dependencyType = $parameter-&gt;getType()-&gt;getName();

    // 5. RECURSION! The container calls ITSELF to build each dependency!
    $dependencies[] = $this-&gt;get($dependencyType);
}

// 6. Finally, it builds LoginController, passing in the newly built dependencies.
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
Because we demand <strong>Strict Typing</strong> (Pillar 4), our constructors look like <code>Magma\controllers\LoginController::__construct</code>:
<br>
<code>public function __construct(private readonly AuthenticationService $authService, private readonly HtmlResponseBuilderInterface $html, private readonly PostLoginRedirectResolver $redirectResolver)</code>
</p>
<p>
Because we strictly typed every parameter, the Reflection API can read those type hints and know exactly what to build (or look up via interface bindings). If we didn't use strict typing, Reflection would have no idea what to inject, and auto-wiring would fail entirely!
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
In Chapter 3.2, we saw that Auto-Wiring is incredibly smart. If <code>LoginController</code>'s constructor asks for <code>AuthenticationService</code>, the Container uses Reflection to inspect its dependencies and build it.
</p>
<p>
But what happens when we follow the Dependency Inversion Principle (Pillar 1) properly?
<br>
What if a constructor looks like <code>Magma\middleware\SessionTimeoutMiddleware::__construct</code> or <code>Magma\services\RegistrationService::__construct</code>?
</p>
<pre><code>public function __construct(
    UserCommandInterface $userCommandRepository,
    EventDispatcherInterface $dispatcher,
    TransactionManagerInterface $transactionManager
)
</code></pre>
<p>
<strong>Reflection hits a wall.</strong> It looks at <code>TransactionManagerInterface</code> and realizes it cannot instantiate an Interface. An interface is just a contract; it has no actual code. If Reflection tries to instantiate an interface directly, PHP throws an error (<code>Target class [...] is not instantiable</code>).
</p>
<p>
The Container needs to know *which specific concrete class* to use when someone asks for that interface.
</p>
<h4>File Walkthrough: The Service Providers</h4>
<p>
To solve this, we give the Container a manual "map." We say: *"Hey Container, whenever someone asks for <code>TransactionManagerInterface</code>, give them <code>DatabaseTransactionManager</code>; whenever someone asks for <code>RolePolicyInterface</code>, give them <code>ConfigRolePolicy</code>; whenever someone asks for <code>ConfigInterface</code>, give them <code>ConfigWrapper</code>."*
</p>
<p>
We call this <strong>Binding</strong>. In Magma Framework, we organize our bindings into <strong>Service Providers</strong> implementing <code>Magma\container\ServiceProviderInterface</code>.
</p>
<p>
Let's look at real bindings from <code>magma/providers/DatabaseServiceProvider.php</code> and <code>magma/providers/CoreServiceProvider.php</code>:
</p>
<pre><code>&lt;?php

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
        $container-&gt;bind(TransactionManagerInterface::class, DatabaseTransactionManager::class);
    }
}
</code></pre>
<p>
And in <code>magma/providers/CoreServiceProvider.php</code>:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\providers;

// Excerpt from CoreServiceProvider::register(Container $container)
$container-&gt;set(\Magma\config\ConfigInterface::class, function ($c) {
    return new \Magma\config\ConfigWrapper();
});

$container-&gt;bind(
    \Magma\contracts\RolePolicyInterface::class,
    \Magma\security\ConfigRolePolicy::class
);
</code></pre>
<p>
If you recall Chapter 2.2 (Bootstrapping), <code>magma/config/bootstrap.php</code> loops through these Service Providers and executes their <code>register($container)</code> methods right before the application runs.
</p>
<h4>Analyzing the Principles: The Power of Swappability</h4>
<p>
By separating the *request* for an object (in the constructor) from the *configuration* of that object (in the Service Provider), we achieve ultimate architectural flexibility.
</p>
<p>
This is the very essence of the <strong>Open/Closed Principle (OCP)</strong>.
</p>
<p>
Imagine a service depends on <code>CacheInterface</code> (<code>magma/interfaces/CacheInterface.php</code>).
</p>
<ol class="syllabus-list">
<li>In production, <code>InfrastructureServiceProvider</code> binds <code>CacheInterface::class</code> to <code>RedisCache</code>.</li>
<li>In isolated unit tests without Redis, we swap one binding line to <code>ArrayCache</code> (<code>magma/cache/ArrayCache.php</code>):</li>
</ol>
<p>
<code>$container-&gt;bind(CacheInterface::class, ArrayCache::class);</code>
</p>
<p>
<strong>That's it.</strong> We don't have to touch any domain service or controller that uses <code>CacheInterface</code>. The Container simply starts handing out the <code>ArrayCache</code> instance to anyone who asks for the Interface.
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
Magma's Container manages memory efficiently using a <strong>Singleton Cache</strong> (<code>$this-&gt;instances</code>) for explicit factory definitions registered via <code>$container-&gt;set(...)</code> (while leaving auto-wired classes transient by default so request-scoped objects/DTOs do not leak across daemon iterations). When <code>DatabaseConnectionManager</code> is resolved for the first time via <code>DatabaseServiceProvider</code>, the Container caches the instance. If five different Repositories ask for <code>DatabaseConnectionManager</code>, they all receive the exact same instance reference in memory (O(1) memory overhead), and constructor reflection metadata is memoized in <code>self::$reflectionCache</code>.
</p>
<p>
By preventing the redundant instantiation of heavy services, Magma keeps its memory footprint entirely flat, allowing it to serve thousands of requests concurrently on minimal hardware.
</p>
<h3>Chapter 3.5: Breaking Circular Dependency Deadlocks</h3>
<p>
A classic architectural bug occurs when <code>ServiceA</code> requires <code>ServiceB</code>, but <code>ServiceB</code> requires <code>ServiceA</code>. If left unchecked, the Reflection engine will enter an infinite loop, crashing the server with a Stack Overflow or an Out Of Memory (OOM) error.
</p>
<p>
Magma's <code>Container</code> (<code>magma/container/Container.php</code>) physically protects against this by tracking resolution paths in <code>$this-&gt;resolving</code>. If it detects a circular dependency (e.g., trying to resolve <code>ServiceA</code> while <code>ServiceA</code> is already in the <code>$this-&gt;resolving</code> stack), it immediately aborts and throws a <code>\RuntimeException(&quot;Circular dependency detected while resolving [{$id}].&quot;)</code>.
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
Before the Router can route traffic, we have to give it a map. In the Magma Framework, we explicitly define this map using fluent <code>RouteDefinition</code> Value Object builders inside dedicated routes files (such as <code>magma/config/routes.php</code>, <code>app/routes.php</code>, and <code>modules/Reviews/routes.php</code>).
</p>
<p>
Here is a real excerpt from <code>magma/config/routes.php</code>:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

use Magma\controllers\LoginController;
use Magma\middleware\RateLimitMiddleware;
use Magma\routing\RouteDefinition;

return [
    // GET &#039;/login&#039; -&gt; LoginController::login()
    RouteDefinition::get(&#039;/login&#039;, [LoginController::class, &#039;login&#039;]),

    // POST &#039;/login&#039; -&gt; LoginController::authenticate() with parameterized rate-limit middleware
    RouteDefinition::post(&#039;/login&#039;, [LoginController::class, &#039;authenticate&#039;])
        -&gt;middleware([RateLimitMiddleware::class, 5, 60]),
];
</code></pre>
<h4>File Walkthrough: The Execution Flow</h4>
<p>
Let's look at what happens inside <code>Magma\Application::run()</code> (<code>magma/Application.php</code>) and <code>Magma\routing\Router::dispatch()</code> (<code>magma/routing/Router.php</code>) when that request arrives:
</p>
<ol class="syllabus-list">
<li><strong>Capture the Request:</strong> <code>Application::run()</code> resolves <code>RequestInterface</code> from the DI Container (bound in <code>CoreServiceProvider</code> to <code>Request::createFromGlobals()</code>).</li>
<li><strong>Hand it to the Router:</strong> <code>Application::handle()</code> passes the <code>RequestInterface</code> and global middleware list to <code>RouterInterface::dispatch($request, $this-&gt;middleware)</code>.</li>
<li><strong>Find the Match:</strong> <code>Router::dispatch()</code> checks <code>matchStaticRoute()</code> first (an O(1) hash lookup), then falls back to <code>matchDynamicRoute()</code> using the compiled PCRE mega-regex. If no route matches, it checks for <code>405 Method Not Allowed</code> or throws <code>RouteNotFoundException</code>.</li>
<li><strong>Resolve the Controller & Middleware:</strong> <code>RouteDispatcher::dispatch()</code> builds the middleware onion and asks the <strong>DI Container</strong> (from Module 3) to instantiate <code>LoginController</code>.</li>
<li><strong>Execute the Method:</strong> <code>RouteParameterResolver</code> auto-wires any method-injected parameters (including <code>FormRequest</code> validation) and invokes the controller method, returning a <code>Response</code>.</li>
</ol>
<h4>Analyzing the Principles: Separation of Concerns</h4>
<p>
You might wonder: *Why do we have a dedicated Router class? Why can't we just put a giant <code>if/else</code> statement inside <code>www/index.php</code>?*
</p>
<pre><code>// The Bad Way (Violating SRP)
if ($_SERVER[&#039;REQUEST_URI&#039;] === &#039;/login&#039;) {
    $controller = new LoginController(...);
    $controller-&gt;login($request);
} elseif ($_SERVER[&#039;REQUEST_URI&#039;] === &#039;/reviews&#039;) {
    // ...
}
</code></pre>
<p>
This violates the <strong>Single Responsibility Principle (SRP)</strong>. If we put routing logic inside <code>www/index.php</code> or <code>Application</code>, those files would grow to thousands of lines long as the application scales. By extracting routing into <code>Magma\routing\Router</code>, <code>RouteCollection</code>, <code>RouteCompiler</code>, and <code>RouteDispatcher</code>, we keep our code modular, testable, and extremely clean.
</p>
<p>
Furthermore, <code>RouteDefinition</code> can handle dynamic parameters with regex constraints:
<br>
<code>RouteDefinition::get(&#039;/users/{id}&#039;, [UserController::class, &#039;show&#039;])-&gt;where(&#039;id&#039;, &#039;\d+&#039;);</code>
<br>
Here, the Router extracts <code>{id}</code> from the URL (like <code>/users/5</code>), validates it against <code>\d+</code>, and passes it directly into the controller's method parameters!
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
Every single Middleware class in the Magma framework implements <code>Magma\middleware\MiddlewareInterface</code> (<code>magma/middleware/MiddlewareInterface.php</code>):
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\Request;
use Magma\http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, callable $next): Response;
}
</code></pre>
<p>
It receives the incoming <code>Request</code> (or <code>RequestInterface</code> via parameter contravariance) and a closure <code>$next</code>. The Middleware has absolute power to decide what to do:
</p>
<ol class="syllabus-list">
<li><strong>Pass:</strong> It can inspect the Request, decide everything is fine, and call <code>$next($request)</code> to pass the request deeper into the onion.</li>
<li><strong>Reject:</strong> It can inspect the Request, find a problem, and instantly return a <code>RedirectResponse</code> or a <code>401</code> JSON <code>Response</code>, <strong>completely blocking the request from ever reaching the controller</strong>.</li>
</ol>
<h4>File Walkthrough: <code>AuthMiddleware</code> & <code>SessionTimeoutMiddleware</code></h4>
<p>
Let's look at a concrete example from <code>magma/middleware/AuthMiddleware.php</code>. Instead of repeating session authentication checks at the top of every protected Controller, we enforce them once in a single Middleware:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\middleware;

use Magma\http\RequestInterface;
use Magma\http\Response;
use Magma\http\RedirectResponse;
use Magma\http\SessionInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public const INTENDED_URL_SESSION_KEY = &#039;intended_url&#039;;

    public function __construct(
        private SessionInterface $session,
        private \Magma\interfaces\cqrs\UserQueryInterface $userRepository
    ) {}

    public function process(RequestInterface $request, callable $next): Response
    {
        $user = $this-&gt;session-&gt;get(&#039;user&#039;);

        if (!is_array($user)) {
            // REJECT! The Controller is never reached.
            return $this-&gt;handleUnauthorized($request);
        }

        // Verify password timestamp against DB to invalidate stale sessions
        $loginTime = $this-&gt;session-&gt;get(&#039;login_time&#039;);
        $userId = $user[&#039;id&#039;] ?? null;

        if (is_numeric($userId) &amp;&amp; is_numeric($loginTime)) {
            $passwordChangedAt = $this-&gt;userRepository-&gt;getPasswordChangedAt((int) $userId);
            if ($passwordChangedAt &amp;&amp; $passwordChangedAt &gt; $loginTime) {
                $this-&gt;session-&gt;destroy();
                return $this-&gt;handleUnauthorized($request);
            }
        }

        // PASS! Hand the request to the next layer of the onion
        return $next($request);
    }

    private function handleUnauthorized(RequestInterface $request): Response
    {
        if ($request-&gt;isJsonExpected() || $request-&gt;expectsJson()) {
            $payload = json_encode([
                &#039;success&#039; =&gt; false,
                &#039;error&#039;   =&gt; &#039;Unauthenticated access.&#039;,
                &#039;code&#039;    =&gt; 401,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            return new Response($payload, 401, [&#039;Content-Type&#039; =&gt; &#039;application/json; charset=utf-8&#039;]);
        }

        return new RedirectResponse(&#039;/login&#039;);
    }
}
</code></pre>
<p>
Similarly, <code>Magma\middleware\SessionTimeoutMiddleware</code> (<code>magma/middleware/SessionTimeoutMiddleware.php</code>) implements <code>MiddlewareInterface::process(Request $request, callable $next): Response</code> using injected <code>SessionInterface</code>, <code>ClockInterface</code>, and <code>RolePolicyInterface</code>, returning <code>new RedirectResponse(&#039;/login&#039;)</code> (with a cleared <code>remember_user</code> cookie) when inactivity exceeds the role's configured timeout. Because <code>SessionTimeoutMiddleware</code> is registered globally in <code>www/index.php</code> (<code>$app-&gt;addMiddleware(SessionTimeoutMiddleware::class);</code>), individual controllers don't have to know that session timeouts even exist.
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
Instead, we encapsulate everything into two strict objects: <code>Magma\http\Request</code> (<code>magma/http/Request.php</code>) and <code>Magma\http\Response</code> / <code>Magma\http\RedirectResponse</code> (<code>magma/http/Response.php</code>, <code>magma/http/RedirectResponse.php</code>).
</p>
<h4>The Theory: Why Encapsulation Matters</h4>
<p>
A fundamental rule of Object-Oriented Programming is that you should not rely on global state.
</p>
<p>
If your controller looks directly at <code>$_POST[&#039;email&#039;]</code>, it is relying on a global variable. This creates a massive problem for <strong>Testability</strong>. If you want to write a unit test for your controller, you have to artificially inject fake data into the <code>$_POST</code> array before running the test, which is clunky and can accidentally bleed into other tests.
</p>
<p>
Furthermore, <code>$_POST</code> is untyped and ignores JSON request bodies.
</p>
<h4>File Walkthrough: The <code>Request</code> Object</h4>
<p>
At the very beginning of the Front Controller lifecycle, <code>CoreServiceProvider</code> binds <code>RequestInterface::class</code> to <code>Request::createFromGlobals()</code> (<code>magma/http/Request.php</code>), which gathers <code>$_GET</code>, <code>$_POST</code>, <code>$_COOKIE</code>, <code>$_FILES</code>, <code>$_SERVER</code>, and <code>php://input</code> via <code>Request::build(...)</code>.
</p>
<p>
This <code>Request</code> object is what gets passed through the Middleware and eventually into the Controller.
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\http;

// Excerpt from magma/http/Request.php
class Request implements RequestInterface
{
    public static function createFromGlobals(): self
    {
        if (self::$cachedRawBody === null) {
            $input = file_get_contents(&#039;php://input&#039;);
            self::$cachedRawBody = $input !== false ? $input : null;
        }
        return self::build($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, self::$cachedRawBody);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this-&gt;get : ($this-&gt;get[$key] ?? $default);
    }

    public function request(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this-&gt;requestData : ($this-&gt;requestData[$key] ?? $default);
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this-&gt;cookies : ($this-&gt;cookies[$key] ?? $default);
    }
}
</code></pre>
<p>
Look at how clean this boundary is! By accessing <code>$request-&gt;query(&#039;sso&#039;)</code>, <code>$request-&gt;request(&#039;email&#039;)</code>, or <code>$request-&gt;cookie(&#039;remember_user&#039;)</code>, controllers and middleware never touch PHP superglobals directly—and <code>$request-&gt;request()</code> seamlessly unifies <code>application/json</code> payloads and standard form POST data! (Typed casting and validation are then enforced by <code>FormRequest</code> and <code>readonly</code> DTOs, covered in Module 9.)
</p>
<h4>File Walkthrough: The <code>Response</code> & <code>RedirectResponse</code> Objects</h4>
<p>
Similarly, a Controller should never use <code>echo</code> or call <code>header(&#039;Location: ...&#039;)</code> directly. Why? Because <code>echo</code> immediately flushes data to the browser. If a Middleware layer on the "way out" wanted to attach security headers or cookies, it's too late—headers are already sent!
</p>
<p>
Instead, a Controller always returns a <code>Response</code> (or <code>RedirectResponse</code>) object.
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\http;

// Excerpt from magma/http/Response.php
class Response
{
    public function __construct(
        private string $content = &#039;&#039;,
        private int $statusCode = 200,
        private array $headers = []
    ) {}

    // The ONLY place in the application where output actually happens
    public function send(): void
    {
        http_response_code($this-&gt;statusCode);

        foreach ($this-&gt;headers as $name =&gt; $value) {
            header(&quot;{$name}: {$value}&quot;, false);
        }

        // ... emits buffered cookies via setcookie(...) ...
        echo $this-&gt;content;
    }
}
</code></pre>
<p>
And for redirects, <code>Magma\http\RedirectResponse</code> (<code>magma/http/RedirectResponse.php</code>) extends <code>Response</code>:
</p>
<pre><code>// Excerpt from magma/http/RedirectResponse.php
class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct(&#039;&#039;, $status, [&#039;Location&#039; =&gt; $url]);
    }
}
</code></pre>
<p>
The <code>Application</code> kernel receives this <code>Response</code> (or <code>RedirectResponse</code>) object from the <code>Router</code>, passes it outward through the Middleware (allowing layers like <code>SecurityHeadersMiddleware</code> to add headers or <code>AppliesAuthenticationCookiesTrait</code> to attach cookies), and finally calls <code>$response-&gt;send()</code> at the very end of the lifecycle.
</p>
<h4>Analyzing the Principles</h4>
<p>
By utilizing Request and Response objects, we enforce <strong>Immutability and Control</strong>. The flow of data is no longer a chaotic free-for-all of global variables. It is a strictly typed, trackable package moving through an organized pipeline.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: How does requiring a Request object (rather than looking directly at <code>$_POST</code>) make writing automated unit tests for a Controller significantly easier and cleaner?</strong><br>
<br>
<strong>A:</strong> If everything passes through our Request object checkpoint, we can easily test the controller without a real browser. We simply write <code>$request = Request::build(post: [&#039;email&#039; =&gt; &#039;user@example.com&#039;], server: [&#039;REQUEST_METHOD&#039; =&gt; &#039;POST&#039;]);</code> and pass it to our Controller. We don't have to hack the global <code>$_POST</code> array, meaning our tests are perfectly isolated and predictable.<br>
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
<h3>Chapter 4.5: The Dynamic Route Discovery Engine</h3>
<p>
As applications scale, dumping hundreds of application routes alongside core framework routes inside <code>magma/config/routes.php</code> creates severe architectural pollution. The framework's core configuration files should be treated as pristine, untouched infrastructure, completely agnostic of the downstream application's features (e.g. <code>HomeController</code> or <code>ReviewController</code>).
</p>
<p>
To solve this, Magma implements the <strong>Dynamic Route Discovery Engine</strong>. Instead of hardcoding all application logic into a single central file, the engine dynamically discovers, loads, and merges routes from across the project:
</p>
<ol class="syllabus-list">
<li><strong>Core Framework Routes:</strong> (<code>magma/config/routes.php</code>) - e.g., default authentication scaffolding or health checks.</li>
<li><strong>Application Routes:</strong> (<code>app/routes.php</code>) - Primary downstream app controllers.</li>
<li><strong>Module Routes:</strong> (<code>modules/*/routes.php</code>) - Discovering fully decoupled module routes via deterministic <code>scandir()</code> traversal.</li>
</ol>
<p>
<strong>Why <code>scandir()</code> instead of <code>glob()</code>?</strong>
<br>
In enterprise systems, functions like <code>glob()</code> can behave inconsistently across different OS environments or specific filesystem configurations. By utilizing standard <code>scandir()</code> loops, Magma ensures highly predictable, cross-platform deterministic directory traversals.
</p>
<p>
Crucially, because this Route Discovery Engine executes _before_ the O(1) Route Compiler runs (<code>bin/cache_routes.php</code>), the dynamic discovery overhead never impacts HTTP runtime. In production, the application still executes against the lightning-fast <code>routes.cache.php</code> manifest!
</p>
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
<h4>The Theory: Magma's Dual Dependency Injection Strategy & The Three Rules of a Controller</h4>
<p>
Magma enforces a deliberate <strong>Dual Dependency Injection Strategy</strong> across its architectural layers:
</p>
<ul class="syllabus-list">
<li><strong>Core Framework Controllers (<code>/magma/controllers/*</code>, e.g., <code>Magma\controllers\LoginController</code>):</strong> Use <strong>Constructor Injection</strong> (<code>__construct(AuthenticationService $authService, HtmlResponseBuilderInterface $html, PostLoginRedirectResolver $redirectResolver)</code>) to enforce rigid instantiation contracts inside the framework kernel.</li>
<li><strong>Application & Module Controllers (<code>/app/</code> and <code>/modules/</code>, e.g., <code>App\controllers\HomeController</code>, <code>Modules\Reviews\controllers\ReviewController</code>):</strong> Use <strong>Method Injection</strong> so the Router's <code>RouteParameterResolver</code> resolves only the services needed for the matched route action, preventing constructor bloat as a controller grows.</li>
</ul>
<p>
To prevent "Fat Controllers," we enforce three absolute rules for any Controller class:
</p>
<ol class="syllabus-list">
<li><strong>Never write Business Logic:</strong> A controller should not hash passwords, calculate taxes, or enforce domain invariants.</li>
<li><strong>Never query the Database:</strong> A controller should never contain SQL or interact directly with a PDO connection.</li>
<li><strong>Always return a Response:</strong> The controller's only purpose is to accept the validated <code>Request</code>/<code>FormRequest</code>, delegate to a Service, and return a <code>Response</code> (or <code>RedirectResponse</code>).</li>
</ol>
<h4>File Walkthrough: Real Magma Controllers</h4>
<p>
First, look at a downstream application controller in <code>app/controllers/HomeController.php</code>:
</p>
<pre><code>&lt;?php
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
        $diagnostics = $diagnosticsService-&gt;getDiagnostics();

        return $html-&gt;render(&#039;welcome&#039;, [
            &#039;title&#039;       =&gt; \App\constants\AppConstants::HOME_TITLE,
            &#039;diagnostics&#039; =&gt; $diagnostics,
        ]);
    }
}
</code></pre>
<p>
And look at a modular feature controller in <code>modules/Reviews/controllers/ReviewController.php</code>:
</p>
<pre><code>&lt;?php

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
        $dto = $reviewRequest-&gt;toDTO();
        $tenantId = $tenantContext-&gt;getTenantId();
        $reviewSubmissionService-&gt;submit($dto, $tenantId);

        $session-&gt;set(
            \App\constants\AppConstants::SESSION_SUCCESS_MESSAGE,
            \Modules\Reviews\constants\ReviewConstants::MSG_REVIEW_SUBMITTED
        );
        return new RedirectResponse(&#039;/&#039;);
    }
}
</code></pre>
<h4>Analyzing the Principles</h4>
<p>
Both controllers perfectly adhere to the <strong>Single Responsibility Principle (SRP)</strong>. Their *only* responsibility is translating HTTP traffic into typed domain calls and returning a <code>Response</code>. Notice how <code>HomeController::index()</code> delegates diagnostics to <code>SystemDiagnosticsServiceInterface</code> and HTML rendering to <code>HtmlResponseBuilderInterface</code>, while <code>ReviewController::submitReview()</code> delegates input validation to <code>ReviewRequest</code> and business logic to <code>ReviewSubmissionServiceInterface</code>.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine we launch a mobile app or JSON API next year alongside our web routes. If we had written all of our review validation and persistence rules directly inside <code>ReviewController</code>, why would building this new API be painful?</strong><br>
<br>
<strong>A:</strong> Because we would have to duplicate every single business rule for the API! By keeping the Controller as just a "Traffic Cop" and pushing orchestration into <code>ReviewSubmissionService</code> (<code>modules/Reviews/services/ReviewSubmissionService.php</code>), a new API controller can inject the exact same <code>ReviewSubmissionServiceInterface</code> and reuse 100% of the business logic.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: In our architecture, a Controller delegates work to a Service. If <code>RegistrationService</code> discovers that an email is already registered, should <code>RegistrationService</code> return <code>new RedirectResponse(&#039;/register&#039;)</code>?</strong><br>
<br>
<strong>A:</strong> No! This is a critical distinction in Separation of Concerns. A <code>Response</code> or <code>RedirectResponse</code> is an HTTP concept (it belongs to the web layer). <code>RegistrationService</code> is pure business logic; it throws <code>ValidationException</code> (caught at the HTTP boundary by <code>ErrorHandler</code>). This keeps the Service layer completely isolated from the web layer!<br>
</blockquote>
<hr>
<h3>Chapter 5.2: The Service Layer - Where the Work Happens</h3>
<h4>Subject & Intent: The Brain of the Application (The Evolutionary Starting Point)</h4>
<p>
If the Controller is the Traffic Cop, the <strong>Service</strong> is the highly-trained mechanic inside the garage.
</p>
<p>
*A Historical Note on Architecture:* When an application first starts, the Service Layer often holds most of the business rules (a pattern known as the <strong>Transaction Script</strong>). We teach this pattern here because it is crucial to understand *how* orchestration is isolated from Controllers and Databases. As you will see in <strong>Module 10 (Domain-Driven Design)</strong>, domain invariants evolve inward into Rich Domain Entities, keeping Services focused purely on orchestration.
</p>
<h4>The Theory: Services Orchestrate</h4>
<p>
A Service's job is <strong>Orchestration</strong>. It coordinates specialized collaborators:
</p>
<ol class="syllabus-list">
<li>Constructing or mutating domain objects (<code>UserRegistration</code>, <code>Review</code>).</li>
<li>Wrapping database writes inside atomic transactions (<code>TransactionManagerInterface::transactional()</code>).</li>
<li>Calling Command/Query repository interfaces (<code>UserCommandInterface</code>, <code>SiteReviewCommandInterface</code>).</li>
<li>Dispatching Domain Events (<code>EventDispatcherInterface::dispatch()</code>) so side-effects like emails happen decoupled from the core workflow.</li>
</ol>
<h4>File Walkthrough: <code>RegistrationService</code> (<code>magma/services/RegistrationService.php</code>)</h4>
<p>
Let's look at the actual <code>registerUser()</code> workflow in <code>magma/services/RegistrationService.php</code>:
</p>
<pre><code>&lt;?php

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
        $this-&gt;userCommandRepository = $userCommandRepository;
        $this-&gt;dispatcher = $dispatcher;
        $this-&gt;transactionManager = $transactionManager;
    }

    public function registerUser(\Magma\dto\RegistrationDTO $dto): int
    {
        $hashedPassword = password_hash($dto-&gt;password, PASSWORD_ARGON2ID, [
            &#039;memory_cost&#039; =&gt; 65536,
            &#039;time_cost&#039;   =&gt; 4,
            &#039;threads&#039;     =&gt; 1,
        ]);

        $registration = new \Magma\domain\UserRegistration($dto-&gt;name, $dto-&gt;email, $hashedPassword);

        $result = $this-&gt;transactionManager-&gt;transactional(function () use ($registration) {
            try {
                $userId = $this-&gt;userCommandRepository-&gt;create($registration);

                if ($userId) {
                    $this-&gt;dispatcher-&gt;dispatch(new UserRegisteredEvent($registration, $userId));
                }

                return $userId;
            } catch (\Magma\domain\exceptions\DuplicateResourceException $e) {
                throw new ValidationException([&#039;email&#039; =&gt; &#039;This email is already registered.&#039;]);
            }
        });

        return is_scalar($result) ? (int) $result : 0;
    }
}
</code></pre>
<h4>Analyzing the Principles: The Power of Isolation</h4>
<p>
Look closely at <code>RegistrationService</code>. Notice what is missing?
</p>
<ul class="syllabus-list">
<li><strong>No <code>$_POST</code>, <code>Request</code>, or <code>Session</code> objects.</strong> The Service doesn't know it's a web app. It takes a strictly typed <code>RegistrationDTO</code>.</li>
<li><strong>No <code>Response</code> objects.</strong> It returns an <code>int</code> user ID or throws a <code>ValidationException</code>.</li>
<li><strong>No <code>SQL</code> strings.</strong> It delegates persistence to <code>UserCommandInterface</code>.</li>
<li><strong>No concrete infrastructure couplings.</strong> It uses interfaces (<code>UserCommandInterface</code>, <code>EventDispatcherInterface</code>, <code>TransactionManagerInterface</code>) so we can swap or test implementations easily, and delegates welcome emails to listeners subscribed to <code>UserRegisteredEvent</code>.</li>
</ul>
<p>
Because <code>RegistrationService</code> is perfectly isolated, a CLI seeder script can invoke the exact same service to create users without needing an HTTP request!
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine we write a CLI script to provision users from the terminal. Why is it structurally impossible for that CLI script to use a web Controller directly, and why does it use a Service instead?</strong><br>
<br>
<strong>A:</strong> Controllers deal with HTTP concepts (<code>Request</code>, <code>FormRequest</code>, <code>Response</code>, <code>RedirectResponse</code>). A command-line script doesn't have an HTTP request! By using a Service (<code>RegistrationService</code>) directly with a DTO, the CLI script bypasses the web layer entirely and reuses the pure PHP business logic.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Why does <code>RegistrationService</code> dispatch <code>UserRegisteredEvent</code> via <code>EventDispatcherInterface</code> instead of directly calling <code>MailerService</code> to send a welcome email?</strong><br>
<br>
<strong>A:</strong> It protects the Single Responsibility Principle (SRP). <code>RegistrationService</code> orchestrates user registration and transaction boundaries; it should not be coupled to SMTP transports or email templates. Listeners subscribed to <code>UserRegisteredEvent</code> handle welcome emails cleanly without bloating the registration workflow.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: How does Magma prevent a Service from becoming a monolithic "God Class" as features multiply?</strong><br>
<br>
<strong>A:</strong> First, we split distinct workflows into cohesive services (e.g., <code>RegistrationService</code>, <code>AuthenticationService</code>, <code>PasswordResetRequestService</code>, <code>ReviewSubmissionService</code>). Second, we push data invariants into pure Domain Entities (Module 10) and offload side-effects via <strong>Domain Events</strong> (<code>EventDispatcherInterface</code>) and background jobs (Modules 11–12).<br>
</blockquote>
<hr>
<h3>Chapter 5.3: Declarative Auto-Wiring & FormRequests</h3>
<p>
How do we validate data without cluttering the controller? We use <strong>FormRequests</strong> (<code>Magma\validation\FormRequest</code>).
</p>
<p>
When <code>RouteParameterResolver</code> detects that a controller method requires a <code>FormRequest</code> subclass such as <code>Modules\Reviews\requests\ReviewRequest</code> (<code>modules/Reviews/requests/ReviewRequest.php</code>) or <code>Magma\requests\LoginRequest</code> (<code>magma/requests/LoginRequest.php</code>), it intercepts the pipeline *before* the controller is executed:
</p>
<ol class="syllabus-list">
<li>The Router instantiates the <code>FormRequest</code> (e.g., <code>ReviewRequest</code>) with the current <code>Request</code> and <code>Validator</code>.</li>
<li>It executes the declarative <code>rules()</code> validation automatically (for <code>LoginRequest</code>: <code>&#039;email&#039; =&gt; &#039;required|email&#039;</code>, <code>&#039;password&#039; =&gt; &#039;required|max:100&#039;</code>).</li>
<li>If validation fails, it automatically throws a <code>ValidationException</code>, which <code>ErrorHandler</code> catches to return JSON errors (<code>422</code>) or flash errors/old input and redirect back.</li>
<li>If it succeeds, the validated <code>FormRequest</code> object is injected straight into the Controller's method signature (Method Injection), ready for <code>$reviewRequest-&gt;toDTO()</code> or <code>$loginRequest-&gt;toDTO()</code>.</li>
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
<p>
For example, in the Reviews module (<code>modules/Reviews/interfaces/cqrs/SiteReviewQueryInterface.php</code> and <code>SiteReviewCommandInterface.php</code>), read and write contracts are separated cleanly:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces\cqrs;

use Modules\Reviews\domain\Review;

interface SiteReviewQueryInterface
{
    public const DEFAULT_LIMIT = 10;

    /** @return iterable&lt;\Modules\Reviews\dto\ReviewDTO&gt; */
    public function getApprovedReviews(int $tenantId, int $limit = self::DEFAULT_LIMIT, ?int $lastId = null): iterable;
}

interface SiteReviewCommandInterface
{
    public function addReview(Review $review): bool;
}
</code></pre>
<p>
By defining these contracts, <code>ReviewSubmissionService</code> knows *what* it can ask for without caring *how* PostgreSQL executes the SQL.
</p>
<h4>File Walkthrough: Concrete CQRS Implementations</h4>
<p>
Now let's look at the actual read repository in <code>modules/Reviews/repositories/SiteReviewQueryRepository.php</code> (extending <code>Magma\models\AbstractQueryRepository</code> in <code>magma/models/AbstractQueryRepository.php</code>):
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Modules\Reviews\repositories;

use Modules\Reviews\interfaces\cqrs\SiteReviewQueryInterface;
use Magma\models\AbstractQueryRepository;
use Modules\Reviews\dto\ReviewDTO;
use Modules\Reviews\domain\Review;

class SiteReviewQueryRepository extends AbstractQueryRepository implements SiteReviewQueryInterface
{
    public const TABLE_NAME = &#039;site_reviews&#039;;

    public function getApprovedReviews(int $tenantId, int $limit = SiteReviewQueryInterface::DEFAULT_LIMIT, ?int $lastId = null): iterable
    {
        $pagination = new \Magma\dto\PaginationDTO(limit: $limit, lastId: $lastId);

        $sql = &quot;SELECT id, comment, author, rating FROM \&quot;&quot; . self::TABLE_NAME . &quot;\&quot; WHERE status = :status AND tenant_id = :tenant_id&quot;;
        $params = [
            &#039;:status&#039; =&gt; Review::STATUS_APPROVED,
            &#039;:tenant_id&#039; =&gt; $tenantId,
        ];

        $result = $this-&gt;cursorPaginate($sql, $params, $pagination, &#039;id&#039;, &#039;DESC&#039;);

        foreach ($result[&#039;items&#039;] as $row) {
            yield new ReviewDTO(
                author: $row[&#039;author&#039;],
                comment: $row[&#039;comment&#039;],
                rating: (int) $row[&#039;rating&#039;],
                id: (int) $row[&#039;id&#039;]
            );
        }
    }
}
</code></pre>
<p>
Meanwhile, write repositories extend <code>Magma\models\AbstractCommandRepository</code> (<code>magma/models/AbstractCommandRepository.php</code>) to route mutations to the Primary Write connection and inject <code>Magma\contracts\ClockInterface</code> for deterministic timestamps instead of SQL <code>NOW()</code>. Look at <code>magma/repositories/UserCommandRepository.php</code>:
</p>
<pre><code>&lt;?php

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
        $this-&gt;clock = $clock;
    }

    public function create(UserRegistration $registration): int
    {
        $now = $this-&gt;clock-&gt;now()-&gt;format(&#039;Y-m-d H:i:s&#039;);
        $data = [
            &#039;name&#039; =&gt; $registration-&gt;getName(),
            &#039;email&#039; =&gt; $registration-&gt;getEmail(),
            &#039;password&#039; =&gt; $registration-&gt;getHashedPassword(),
            &#039;created_at&#039; =&gt; $now,
            &#039;updated_at&#039; =&gt; $now,
        ];
        $tenantId = $this-&gt;getTenantId();
        if ($tenantId !== null) {
            $data[&#039;tenant_id&#039;] = $tenantId;
        }
        return $this-&gt;insertAndGetId(&#039;users&#039;, $data);
    }
}
</code></pre>
<h4>Analyzing the Principles: The Multi-Tenancy Shield</h4>
<p>
Let's return to the most critical business requirement of the Magma framework: <strong>Multi-Tenancy</strong> (supporting multiple tenants on one shared PostgreSQL database).
</p>
<p>
If we share one database table for *all* tenants, the biggest risk is that "Tenant A" logs in and accidentally sees "Tenant B's" data. This is a catastrophic failure.
</p>
<p>
If developers are allowed to write SQL in controllers or services, it is almost guaranteed that someone will write <code>SELECT * FROM site_reviews</code> and forget to add <code>WHERE tenant_id = :tenant_id</code>.
</p>
<p>
Because we use the Repository Pattern paired with <code>Magma\security\TenantContext</code>, both <code>AbstractQueryRepository</code> and <code>AbstractCommandRepository</code> receive <code>$tenantContext</code> in their constructors (<code>$this-&gt;getTenantId()</code>). Every SQL query lives inside the repository and explicitly binds <code>tenant_id</code> in its <code>WHERE</code> clause or <code>INSERT</code> payload.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Our <code>ReviewSubmissionService</code> requires a <code>SiteReviewCommandInterface</code>. If we want to unit-test the service without actually connecting to PostgreSQL, how does the Repository Pattern allow us to do that?</strong><br>
<br>
<strong>A:</strong> We don't write any SQL to test it at all! Because the Service depends only on the <code>Interface</code>, we can inject an in-memory test double implementing <code>SiteReviewCommandInterface</code> during unit tests, completely bypassing the database layer.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: If a new privacy law passes requiring us to filter out archived reviews in <code>getApprovedReviews()</code>, how many files in our codebase do we modify?</strong><br>
<br>
<strong>A:</strong> Just one: <code>SiteReviewQueryRepository</code>! Because all SQL is quarantined inside the repository, we update the query there, and every Service and Controller across the application respects the rule immediately.<br>
</blockquote>
<hr>
<h3>Chapter 6.2: Tenant Context - The Invisible Shield</h3>
<h4>Subject & Intent: The Greatest Risk in SaaS</h4>
<p>
As we mentioned in Chapter 6.1, the absolute greatest risk when building a Multi-Tenant platform (where many tenants share one database) is <strong>Cross-Tenant Data Leakage</strong>.
</p>
<p>
If "Sandbox Corp" logs into their dashboard, and due to a coding error, they see "Client B's" records, you have a massive legal and security breach on your hands.
</p>
<p>
If we rely on controllers to pass user-supplied <code>tenant_id</code> values around, human error or parameter tampering *will* eventually cause a data leak. We need a systematic, request-scoped context bound early in the pipeline.
</p>
<h4>The Theory: The Context Object</h4>
<p>
To build this shield, Magma uses <code>Magma\security\TenantContext</code> (<code>magma/security/TenantContext.php</code>). Early in the request lifecycle, <code>TenantSecurityMiddleware</code> resolves the tenant and binds it into this shared context object:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\security;

class TenantContext
{
    private ?int $tenantId = null;
    private ?int $venueId = null;
    private ?TenantContextProviderInterface $provider;

    public function __construct(?TenantContextProviderInterface $provider = null)
    {
        $this-&gt;provider = $provider;
    }

    public function setTenantId(int $tenantId): void
    {
        $this-&gt;tenantId = $tenantId;
    }

    public function getTenantId(): int
    {
        if ($this-&gt;tenantId === null) {
            throw new \RuntimeException(
                &#039;Tenant ID is not set in the current tenant context. This usually indicates unauthenticated access, missing tenant scoping middleware, or a cross-tenant boundary breach.&#039;
            );
        }
        return $this-&gt;tenantId;
    }

    public function hasTenantId(): bool
    {
        return $this-&gt;tenantId !== null;
    }
}
</code></pre>
<p>
Notice the fail-closed invariant: calling <code>getTenantId()</code> when no tenant has been bound immediately throws a <code>\RuntimeException</code> instead of silently returning <code>0</code> or <code>null</code>.
</p>
<h4>File Walkthrough: Consuming the Shield & Auth Tenant Modes</h4>
<p>
When a repository extends <code>AbstractQueryRepository</code> or <code>AbstractCommandRepository</code>, <code>DatabaseConnectionManager</code> and <code>TenantContext</code> are injected via the constructor, exposing <code>$this-&gt;getTenantId()</code> safely:
</p>
<pre><code>$tenantId = $this-&gt;getTenantId();
if ($tenantId !== null) {
    $data[&#039;tenant_id&#039;] = $tenantId;
}
return $this-&gt;insertAndGetId(&#039;users&#039;, $data);
</code></pre>
<p>
In addition, <code>TenantSecurityMiddleware</code> supports two distinct operating modes via the backed/unit enum <code>Magma\security\AuthTenantMode</code> (<code>magma/security/AuthTenantMode.php</code>):
</p>
<ul class="syllabus-list">
<li><code>AuthTenantMode::TenantScoped</code> (default for white-label tenant domains): validates that an authenticated user belongs to the resolved domain's <code>tenant_id</code>.</li>
<li><code>AuthTenantMode::GlobalGateway</code> (for central portal gateways): rebinds <code>TenantContext</code> authoritatively from the authenticated user's <code>tenant_id</code>.</li>
</ul>
<h4>Analyzing the Principles: Secure by Default</h4>
<p>
Why is this architecture superior?
</p>
<ol class="syllabus-list">
<li><strong>Fail-Closed Isolation:</strong> <code>TenantContext::getTenantId()</code> refuses to proceed if no tenant has been established by middleware.</li>
<li><strong>Scoped by Construction:</strong> Both <code>AbstractQueryRepository</code> and <code>AbstractCommandRepository</code> accept <code>TenantContext</code> directly in their constructors, keeping tenant awareness available to every repository without touching <code>$_SESSION</code> or <code>$_POST</code>.</li>
<li><strong>CLI & Worker Portability:</strong> Background scripts (<code>bin/worker.php</code>) don't have an HTTP session, so they can bind <code>$tenantContext-&gt;setTenantId($id)</code> explicitly for each job and reuse the exact same CQRS repositories safely.</li>
</ol>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: We write a CLI job that needs to process data across *all* tenants on the platform. If our Repositories rely on an injected <code>TenantContext</code>, how can a single script process multiple tenants safely?</strong><br>
<br>
<strong>A:</strong> The worker iterates over the list of tenants, calls <code>$container-&gt;flushInstances()</code> between jobs (or creates a fresh <code>TenantContext</code> / binds the active <code>$tenantId</code>), and executes the same CQRS repositories for each tenant sequentially—preserving strict isolation on every iteration.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: Some ORMs use "Global Scopes," where the framework magically intercepts SQL behind the scenes and injects <code>WHERE tenant_id = 1</code> before it hits the database. Why does Magma require explicit <code>WHERE tenant_id = :tenant_id</code> clauses in repositories instead?</strong><br>
<br>
<strong>A:</strong> You have to know what is going on behind the scenes to debug and audit security boundaries safely. Relying on hidden SQL rewriting can backfire when you encounter joins, subqueries, or global gateway queries. By explicitly injecting <code>TenantContext</code> and writing <code>WHERE tenant_id = :tenant_id</code> in each repository, the isolation boundary is transparent, auditable, and crystal clear.<br>
</blockquote>
<h4>Domain-Based Tenant Resolution (White Labeling)</h4>
<p>
As a platform grows, tenants may want their own dedicated URLs (e.g., <code>client.sandboxplatform.com</code> or custom white-label domains). To support this without polluting business logic, <code>TenantSecurityMiddleware</code> delegates host resolution to <code>DomainTenantContextProvider</code> (<code>TenantContextProviderInterface</code>), which resolves the incoming host via <code>TenantQueryInterface</code> (backed by the read-replica and cache) and binds the resulting <code>TenantContext</code>. The rest of Magma remains agnostic to whether the tenant came from a custom domain or an authenticated session.
</p>
<hr>
<h3>Chapter 6.3: The Evolution to CQRS & SERIALIZABLE ACID Compliance</h3>
<p>
The most profound architectural evolution in the Magma Framework is its persistence layer. As systems scale, a unified Repository becomes a bottleneck. Magma evolved to use <strong>CQRS (Command Query Responsibility Segregation)</strong>.
</p>
<p>
In Magma, read operations and write operations are physically and conceptually segregated:
</p>
<ul class="syllabus-list">
<li><strong><code>AbstractQueryRepository</code> (<code>magma/models/AbstractQueryRepository.php</code>):</strong> Used purely for fetching data via <code>$this-&gt;dbManager-&gt;getReadConnection()</code>. It returns DTOs (or yields them via Generators), preventing accidental writes or lazy-loading side effects in the presentation layer.</li>
<li><strong><code>AbstractCommandRepository</code> (<code>magma/models/AbstractCommandRepository.php</code>):</strong> Used purely for mutations (<code>INSERT</code>, <code>UPDATE</code>, <code>DELETE</code>) via <code>$this-&gt;dbManager-&gt;getWriteConnection()</code>.</li>
</ul>
<h4>Extreme ACID Compliance (Eliminating Phantom Reads)</h4>
<p>
Data integrity is the absolute highest priority. PostgreSQL defaults to <code>READ COMMITTED</code>, which can allow phantom reads under high concurrency.
</p>
<p>
Magma's <code>DatabaseTransactionManager</code> (<code>magma/database/DatabaseTransactionManager.php</code>) executes <code>SET TRANSACTION ISOLATION LEVEL SERIALIZABLE</code> when starting transactions, guaranteeing that concurrent transactions behave as if executed serially and eliminating race conditions.
</p>
<h4>Write-Master Redirection</h4>
<p>
Crucially, when a transaction begins, <code>DatabaseConnectionManager</code> / <code>DatabaseTransactionManager</code> ensures queries inside an active transaction see the primary write-master state rather than lagging read replicas—eliminating replication-lag read-your-own-writes bugs.
</p>
<h4>The Liskov Substitution Principle (LSP) Firewall</h4>
<p>
If an abstract base class declares <code>protected function update(string $table, array $data)</code>, any concrete subclass trying to implement a domain interface with <code>public function update(int $id, array $data)</code> will crash PHP with a signature mismatch.
</p>
<p>
Magma solves this by naming internal base helpers <code>insertAndGetId()</code>, <code>executeUpdate()</code>, <code>executeDelete()</code>, and <code>execute()</code> inside <code>AbstractCommandRepository</code>, acting as an <strong>LSP Firewall</strong> so concrete repositories can freely implement domain method names (<code>create()</code>, <code>update()</code>, <code>delete()</code>, <code>addReview()</code>) without colliding with base class signatures.
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
If there is a business rule (e.g., validating a review status or calculating diagnostic status), that rule belongs in a Service or Domain Entity such as <code>Modules\Reviews\domain\Review</code>, *never* in the View.
</p>
<h4>File Walkthrough: Passing Data to the View</h4>
<p>
Let's trace how data reaches the View in the real <code>app/controllers/HomeController.php</code>:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace App\controllers;

use App\services\SystemDiagnosticsServiceInterface;

class HomeController
{
    public function index(
        SystemDiagnosticsServiceInterface $diagnosticsService,
        \Magma\view\HtmlResponseBuilderInterface $html
    ): \Magma\http\Response {
        $diagnostics = $diagnosticsService-&gt;getDiagnostics();

        return $html-&gt;render(&#039;welcome&#039;, [
            &#039;title&#039;       =&gt; \App\constants\AppConstants::HOME_TITLE,
            &#039;diagnostics&#039; =&gt; $diagnostics,
        ]);
    }
}
</code></pre>
<p>
Notice that <code>HomeController</code> receives <code>\Magma\view\HtmlResponseBuilderInterface</code> (<code>magma/view/HtmlResponseBuilder.php</code>) via Method Injection and does not <code>echo</code> any HTML! Under the hood, <code>HtmlResponseBuilder::render()</code> automatically injects <code>csrfField</code> and <code>csrfToken</code>, delegates rendering to <code>Magma\view\TemplateEngine</code> (<code>magma/view/TemplateEngine.php</code>), and wraps the compiled string inside a <code>Magma\http\Response</code>. Meanwhile, <code>ViewShareMiddleware</code> (<code>magma/middleware/ViewShareMiddleware.php</code>) shares request-wide globals (<code>tenant</code>, <code>tagline</code>, <code>user</code>, <code>errors</code>, <code>old</code>), and <code>AssetVersionManager</code> (<code>magma/assets/AssetVersionManager.php</code>) provides cache-busted asset URLs.
</p>
<h4>The Template Engine (The Compiler)</h4>
<p>
Every view file in <code>app/views/</code> (like <code>app/views/404.php</code> or <code>app/views/welcome.php</code>) starts with a mandatory docblock specifying <code>Purpose</code>, <code>Teaching notes</code>, and <code>@var</code> parameter types, and escapes all dynamic values explicitly:
</p>
<pre><code>&lt;?php
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
 * @var array{theme?: array&lt;string, string|null&gt;, tenant?: \Magma\dto\TenantDTO|null} $data
 */
$tenant = $data[&#039;tenant&#039;] ?? null;
$appName = $tenant-&gt;name ?? (getenv(&#039;APP_NAME&#039;) ?: &#039;Magma Framework&#039;);
?&gt;
&lt;!DOCTYPE html&gt;
&lt;html lang=&quot;en&quot;&gt;
&lt;head&gt;
    &lt;meta charset=&quot;UTF-8&quot;&gt;
    &lt;title&gt;404 Not Found | Magma&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;&lt;?= htmlspecialchars((string) ($code ?? 404), ENT_QUOTES, &#039;UTF-8&#039;) ?&gt;&lt;/h1&gt;
    &lt;p&gt;&lt;?= htmlspecialchars($appName, ENT_QUOTES, &#039;UTF-8&#039;) ?&gt;&lt;/p&gt;
&lt;/body&gt;
&lt;/html&gt;
</code></pre>
<h4>Analyzing the Principles: The XSS Vulnerability</h4>
<p>
You might have noticed something critical in the template above: we wrap dynamic text in <code>htmlspecialchars(..., ENT_QUOTES, &#039;UTF-8&#039;)</code>.
</p>
<p>
This is the most critical security rule of the View layer. <strong>Cross-Site Scripting (XSS)</strong> occurs when an attacker types malicious JavaScript into a form (e.g., setting their username to <code>&lt;script&gt;stealCookies();&lt;/script&gt;</code>). If the View layer blindly outputs that username directly into the HTML using <code>echo $username;</code>, the browser will execute the attacker's script!
</p>
<p>
By wrapping every variable in <code>htmlspecialchars(..., ENT_QUOTES, &#039;UTF-8&#039;)</code>, we neutralize the threat. It converts literal <code>&lt;</code> and quote characters into safe HTML entities, rendering them harmless text on the screen.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Imagine Sandbox Corp hires a junior Front-End Web Designer who only knows HTML and CSS, and does not know PHP. How does enforcing the "Dumb View" architecture make their job significantly safer and easier compared to the "Spaghetti Code" era?</strong><br>
<br>
<strong>A:</strong> The data received is sanitized and empty of any logic that might harm or break the app. It provides simply the necessary information, ready for the view. The designer can focus on HTML and CSS without worrying about accidentally executing database queries or breaking complex backend routines.<br>
</blockquote>
<blockquote class="syllabus-quote">
<strong>Q: The business wants to highlight reviews that are approved with a green badge. A developer wants to write raw SQL or status-transition calculations directly inside <code>app/views/welcome.php</code>. Why does this violate the "Dumb View" philosophy, and where *should* that logic live?</strong><br>
<br>
<strong>A:</strong> Business logic should not be in the View or in the Controller! (Controllers are just Traffic Cops). Domain rules belong in the <strong>Service Layer</strong> or the <strong>Domain Model</strong> (such as <code>Modules\Reviews\domain\Review</code> or a dedicated DTO property). The View simply checks the prepared boolean/state or renders the pre-formatted DTO field safely.<br>
</blockquote>
<hr>
<h3>Chapter 7.2: Multi-Directory Fallback & Resolution Caching</h3>
<p>
Large SaaS applications store views across multiple directory structures (<code>app/views</code> and namespaced <code>modules/*/views</code> directories automatically registered by <code>LocalFileViewLoader</code> inside <code>magma/providers/CoreServiceProvider.php</code>).
</p>
<p>
Magma's <code>TemplateEngine</code> intelligently falls back across these directories to resolve layouts. However, under heavy load, checking <code>file_exists()</code> across multiple directories for every partial creates severe disk I/O bottlenecks.
</p>
<p>
To prevent this, the resolution paths are cached in-memory (<code>$resolvedLayoutCache</code>). The engine guarantees that the disk is only queried once per layout or partial per request lifecycle.
</p>
<p>
Furthermore, to support fully decoupled, multi-tenant components without relying on legacy assumptions, Magma forces explicit layout rendering. The framework does not default to searching for a <code>&#039;default&#039;</code> layout wrapper; components render completely standalone unless a layout is specifically requested in the controller.
</p>
<h3>Chapter 7.3: Native PHP Stacks for Modular Assets</h3>
<p>
Large applications require child views to inject their own specific assets (like unique CSS files or JavaScript modules) up into the master layout's <code>&lt;head&gt;</code> or before the closing <code>&lt;/body&gt;</code> tag.
</p>
<p>
Magma's <code>TemplateEngine</code> provides native PHP stacks (similar to Laravel's <code>@push</code> and <code>@stack</code>) using <code>push(string $name)</code>, <code>endPush()</code>, and <code>stack(string $name)</code>. This uses nested output buffering (<code>$activeStacks</code> and <code>$stacks</code>) to seamlessly capture and bubble modular assets up to the master layout without polluting global variables.
</p>
<h3>Chapter 7.4: Big-O DOM Interpolation Optimization</h3>
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
In the Magma Framework, we enforce a global <strong>Exception Handler</strong> and absolute <strong>Exception Boundaries</strong> at the infrastructure level.
<br>
For example, our base <code>AbstractQueryRepository</code> and <code>AbstractCommandRepository</code> explicitly catch <code>PDOException</code> natively at the execution layer, translating them into <code>DatabaseException</code>. Similarly, our storage adapter (<code>LocalStorageService</code> in <code>magma/infrastructure/storage/LocalStorageService.php</code>) throws <code>StorageException</code> upon disk permission failures or invalid paths, preventing silent boolean data loss. This mathematically guarantees raw database credentials, SQL syntax errors, or filesystem secrets never bleed into the domain or HTTP application layers.
</p>
<p>
To further elevate our Site Reliability Engineering (SRE), deep within our <code>ErrorHandler</code> and <code>NativeLogger</code> (<code>magma/logging/NativeLogger.php</code>), we protect sensitive payloads from hitting logs and utilize <code>CorrelationIdProvider</code> (<code>magma/logging/CorrelationIdProvider.php</code>) to attach a unique Trace ID to every request cycle, allowing developers to correlate events across boundaries.
</p>
<p>
Furthermore, we enforce <strong>Pre-Kernel Boot Safety Nets</strong>. If a fatal error occurs in <code>www/index.php</code> or <code>bin/worker.php</code> *before* the application container and <code>ErrorHandler</code> are fully registered, an outermost <code>try/catch</code> wrapper intercepts the failure to emit a clean 500 status code, protecting the system from 0-day <code>.env</code> or container misconfiguration leaks.
</p>
<h4>The Theory: Catching Everything</h4>
<p>
Instead of sprinkling <code>try/catch</code> blocks randomly throughout every single file, we register <code>ErrorHandlerInterface</code> (<code>Magma\error\ErrorHandler</code>) inside <code>Application::run()</code> (booted via <code>magma/config/bootstrap.php</code> and <code>CoreServiceProvider</code>).
</p>
<p>
If a <code>\Throwable</code> is thrown anywhere in the application—whether deep inside a Repository or high up in a Middleware—and it is *not* caught locally, it bubbles all the way up to <code>ErrorHandler::handleException()</code>.
</p>
<h4>File Walkthrough: The Error Handler</h4>
<p>
Here is the real constructor and flow in <code>magma/error/ErrorHandler.php</code>:
</p>
<pre><code>&lt;?php

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
        // ... resolves $this-&gt;debug from $debug or APP_DEBUG / APP_ENV / ENVIRONMENT
    }

    public function handleException(\Throwable $e, ?RequestInterface $request = null): Response
    {
        // 1. Flush any partial output buffers (ob_end_clean)
        // 2. Return 422 JSON or flash ValidationException errors &amp; redirect
        // 3. Log technical details securely
        // 4. Content-negotiate JSON via JsonErrorPresenterInterface when expected
        // 5. If $this-&gt;debug is true, render magma/error/templates/debug_error.php via DebugErrorPresenterInterface
        // 6. Otherwise call $this-&gt;renderError($code, $message, null, $request) -&gt; app/views/404.php or 500.php
    }
}
</code></pre>
<h4>Analyzing the Principles: Security, Content Negotiation, and Observability</h4>
<p>
This architecture separates concerns across environments and client types:
</p>
<p>
By tying debug diagnostics to <code>APP_DEBUG</code> and <code>ENVIRONMENT</code> (and isolating output rendering inside <code>DebugErrorPresenter</code> -> <code>magma/error/templates/debug_error.php</code> vs. production <code>app/views/404.php</code> and <code>app/views/500.php</code>), an attacker can never purposefully crash the site in production just to read a stack trace.
</p>
<p>
Meanwhile, API clients (<code>Accept: application/json</code> or <code>/api/*</code> routes) automatically receive structured JSON payloads from <code>JsonErrorPresenterInterface</code>, while <code>NativeLogger</code> (<code>magma/logging/NativeLogger.php</code>) and <code>CorrelationIdProvider</code> (<code>magma/logging/CorrelationIdProvider.php</code>) preserve full technical diagnostics for engineers.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: If we didn't have a global <code>ErrorHandler</code> configured for <code>Application::run()</code>, and a developer forgot to write a <code>try/catch</code> block around a failing database query, what are the *two* completely different ways the application might behave depending on <code>php.ini</code>, and why are *both* terrible for production?</strong><br>
<br>
<strong>A:</strong> It could print a full raw stack trace (exposing file paths and internal queries—a severe security risk), or it could emit a blank White Screen of Death (a terrible user experience). With <code>Magma\error\ErrorHandler</code>, production users always receive a clean, tenant-themed error page or JSON payload while the full failure is logged safely.<br>
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
Our domain services are strictly typed: for example, <code>AuthenticationService::attempt(string $email, string $password, bool $rememberMe)</code> or <code>ReviewSubmissionService::submit(ReviewDTO $dto, int $tenantId)</code>.
</p>
<p>
But the web browser submits forms as strings inside HTTP POST arrays. If we pass raw <code>$_POST</code> arrays directly into services, we lose type safety, risk missing keys, and couple business logic to HTTP payloads.
</p>
<p>
We need a translator and an immutable boundary object.
</p>
<h4>The Theory: The FormRequest + DTO Pair</h4>
<p>
A <strong>FormRequest</strong> validates incoming HTTP inputs, and a <strong>Data Transfer Object (DTO)</strong> is a simple, immutable <code>readonly class</code> carrying only strongly-typed properties into the service layer.
</p>
<h4>File Walkthrough: <code>LoginRequest</code> and <code>LoginDTO</code></h4>
<p>
Let's look at the real <code>magma/dto/LoginDTO.php</code>:
</p>
<pre><code>&lt;?php

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
</code></pre>
<p>
And here is <code>magma/requests/LoginRequest.php</code>, which validates the payload and transforms it into <code>LoginDTO</code>:
</p>
<pre><code>&lt;?php

declare(strict_types=1);

namespace Magma\requests;

use Magma\validation\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            &#039;email&#039;    =&gt; &#039;required|email&#039;,
            &#039;password&#039; =&gt; &#039;required|max:100&#039;,
        ];
    }

    public function toDTO(): \Magma\dto\LoginDTO
    {
        $data = $this-&gt;request-&gt;request();
        $email = is_array($data) &amp;&amp; isset($data[&#039;email&#039;]) &amp;&amp; is_string($data[&#039;email&#039;]) ? $data[&#039;email&#039;] : &#039;&#039;;
        $password = is_array($data) &amp;&amp; isset($data[&#039;password&#039;]) &amp;&amp; is_string($data[&#039;password&#039;]) ? $data[&#039;password&#039;] : &#039;&#039;;
        $rememberMe = is_array($data) &amp;&amp; !empty($data[&#039;remember_me&#039;]);

        return new \Magma\dto\LoginDTO(
            email: trim($email),
            password: $password,
            rememberMe: $rememberMe
        );
    }
}
</code></pre>
<p>
Now look at how clean <code>Magma\controllers\LoginController::authenticate()</code> (<code>magma/controllers/LoginController.php</code>) becomes:
</p>
<pre><code>public function authenticate(LoginRequest $loginRequest, Request $request): Response
{
    // Validation is automatically handled by RouteParameterResolver for LoginRequest.
    $dto = $loginRequest-&gt;toDTO();

    $result = $this-&gt;authService-&gt;attempt($dto-&gt;email, $dto-&gt;password, $dto-&gt;rememberMe);

    if (!$result-&gt;isSuccessful()) {
        return $this-&gt;redirectResolver-&gt;redirectAfterFailedLogin($dto-&gt;email, &#039;Invalid credentials&#039;);
    }

    $user = $result-&gt;getUser();
    if ($user !== null) {
        return $this-&gt;applyAuthResult($result, $this-&gt;redirectResolver-&gt;redirectAfterAuthentication($user, $request), $request);
    }

    return new RedirectResponse(&#039;/login&#039;);
}
</code></pre>
<p>
Another practical DTO in Magma is <code>Magma\dto\TenantDTO</code> (<code>magma/dto/TenantDTO.php</code>), which encapsulates tenant metadata and exposes type-safe white-label helpers such as <code>$tenant-&gt;setting(string $key, string $default = &#039;&#039;)</code>, <code>settingBool()</code>, <code>settingArray()</code>, and <code>hasSetting()</code> instead of raw nested array lookups.
</p>
<h4>Common Questions and Answers</h4>
<blockquote class="syllabus-quote">
<strong>Q: Why is it architecturally better to put input rule checking and type extraction inside <code>LoginRequest</code> + <code>LoginDTO</code> rather than inside <code>AuthenticationService</code> or <code>LoginController</code>?</strong><br>
<br>
<strong>A:</strong> <code>LoginRequest</code> handles HTTP input grammar (<code>required|email</code>, <code>max:100</code>), while <code>LoginDTO</code> guarantees an immutable, strictly-typed contract (<code>string $email</code>, <code>string $password</code>, <code>bool $rememberMe</code>). That leaves <code>LoginController</code> as a thin Traffic Cop and lets <code>AuthenticationService</code> stay 100% agnostic of HTTP forms.<br>
</blockquote>
<hr>
<h3>Chapter 9.2: Engine-Enforced Immutability</h3>
<p>
If a developer accidentally mutates a DTO mid-flight (e.g., <code>$dto-&gt;email = &#039;&#039;;</code>), it can introduce catastrophic, hard-to-trace bugs.
</p>
<p>
Magma eliminates this entirely by utilizing PHP 8.2's native <code>readonly class</code> modifiers for all DTOs (such as <code>LoginDTO</code>, <code>TenantDTO</code>, <code>ReviewDTO</code>, and <code>PaginationDTO</code>).
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
<h4>The Theory: Behavior Belongs to the Data (Conceptual DDD Illustration)</h4>
<p>
In a Rich Domain Model, the objects that represent your business (Entities) are no longer "dumb." They contain the business logic that directly pertains to them. To illustrate the contrast conceptually (using a classic e-commerce <code>Order</code> example before looking at Magma's live entities):
</p>
<p>
<strong>The Transaction Script Way (Conceptual Illustration — Dumb Data, Smart Service):</strong>
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
Many architectures corrupt their domain models by passing HTTP-specific objects (like a <code>Request</code> or a framework-specific FormRequest) directly into them.
</p>
<p>
Magma enforces <strong>100% Pure Domain Entities</strong>.
</p>
<p>
Entities are completely agnostic of the HTTP and persistence layers. For example:
</p>
<ul class="syllabus-list">
<li><code>Modules\Reviews\domain\Review</code> (<code>modules/Reviews/domain/Review.php</code>) accepts pure scalar fields (<code>int $tenantId</code>, <code>string $author</code>, <code>string $comment</code>, <code>int $rating</code>, <code>string $status</code>), validates domain invariants (<code>MIN_RATING = 1</code>, <code>MAX_RATING = 5</code>), and never touches a database connection or HTTP request.</li>
<li><code>Magma\domain\AuthUser</code> (<code>magma/domain/AuthUser.php</code>) is a <code>readonly class</code> encapsulating only safe session fields (<code>id</code>, <code>name</code>, <code>role</code>, <code>email</code>, <code>tenantId</code>), preventing password hashes from ever leaking into session payloads.</li>
</ul>
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
<h4>Self-Healing and Tenant Safety</h4>
<p>
To guarantee that these background workers never crash your infrastructure, Magma implements <strong>Self-Healing Workers</strong> in <code>QueueWorkerDaemon</code> (<code>magma/queue/QueueWorkerDaemon.php</code>, backed by <code>Magma\queue\ReliableQueueInterface</code> in <code>magma/queue/ReliableQueueInterface.php</code>). It wraps queue polling in a <code>try/catch</code> block so transient Redis or worker failures log via <code>NativeLogger</code> (<code>magma/logging/NativeLogger.php</code>) and recover cleanly instead of crashing the daemon.
</p>
<p>
Additionally, because background workers run continuously within a single PHP process, they share the same <code>Container</code>. This presents a data-leakage risk in multi-tenant SaaS architectures if singleton state bleeds from Job A into Job B. To prevent this, <code>QueueWorkerDaemon</code> calls <code>$container-&gt;flushInstances()</code> between jobs so every job runs in a fresh instance scope.
</p>
<hr>
<h3>Chapter 12.2: The Transactional Outbox Pattern & Idempotent Projections</h3>
<h4>The Dual-Write Problem</h4>
<p>
Synchronous background tasks kill web performance. The standard solution is to dispatch to a message queue. However, this introduces the <strong>Dual-Write Problem</strong>: What happens if the database transaction commits successfully, but the network connection to Redis fails? The system is now in an inconsistent state.
</p>
<p>
Magma completely solves this using the <strong>Transactional Outbox Pattern</strong>.
</p>
<p>
Instead of dispatching directly to a queue, <code>OutboxJobRepository</code> (<code>magma/database/OutboxJobRepository.php</code>) records domain jobs *atomically* within the exact same database transaction as the domain mutation. If the transaction commits, the job is guaranteed to be in the database (<code>bin/outbox_publisher.php</code> publishes it safely); if it rolls back, the job vanishes.
</p>
<h4>FOR UPDATE SKIP LOCKED & Idempotent Projections</h4>
<p>
When multiple outbox publisher workers poll concurrently, <code>OutboxJobRepository::fetchAndLockPending()</code> uses PostgreSQL's native <code>SELECT ... FOR UPDATE SKIP LOCKED</code> locking primitive so concurrent workers skip locked rows and never block each other.
</p>
<p>
Because distributed queues provide at-least-once delivery, downstream projection consumers guard against duplicate replay via <code>IdempotentProjectionGuard</code> (<code>magma/queue/IdempotentProjectionGuard.php</code>), which records processed event checkpoints in the <code>projection_checkpoints</code> table.
</p>
<h3>Chapter 12.3: Mastering Concurrency and Race Conditions</h3>
<h4>Subject & Intent: Designing for High Throughput</h4>
<p>
As web applications scale horizontally, naive assumptions about code execution order break down. A race condition occurs when two or more threads (or web requests) attempt to read and write shared data at the exact same millisecond. Without strict synchronization mechanisms, this leads to corrupted data, duplicate actions, and catastrophic failures.
</p>
<p>
Magma has undergone a rigorous, multi-pass <strong>Concurrency Audit</strong> to mathematically eliminate these architectural vulnerabilities from its core.
</p>
<h4>1. TOCTOU (Time-Of-Check to Time-Of-Use) Races</h4>
<p>
A common anti-pattern is checking if a condition exists, and then acting on it (<code>if (!$repo-&gt;exists()) { $repo-&gt;insert(); }</code>). Under concurrency, Worker A and Worker B both check simultaneously, both receive <code>false</code>, and both insert duplicate state.
</p>
<p>
<strong>The Magma Solution:</strong> <code>IdempotentProjectionGuard</code> relies on the database engine's atomic truth via <code>INSERT ... ON CONFLICT DO NOTHING</code>. If the insert claims the row, execution proceeds; otherwise another worker already processed the event and execution halts safely.
</p>
<h4>2. Two-Phase Reliable Queue Recovery (Orphan Prevention)</h4>
<p>
A naive Redis <code>BLPOP</code> removes a job from the list the instant a worker reads it. If that worker process suffers an OOM kill or hardware crash mid-job, the popped job vanishes forever.
</p>
<p>
<strong>The Magma Solution:</strong> <code>ReliableQueueInterface</code> (<code>magma/queue/ReliableQueueInterface.php</code>) and <code>RedisQueue</code> implement two-phase reliable consumption (<code>popReliable()</code>, <code>acknowledge()</code>, and <code>recoverOrphaned()</code>), moving in-flight payloads into a processing list until acknowledged so crashed jobs are deterministically recovered.
</p>
<h4>3. Isolating Network I/O from Database Locks</h4>
<p>
Performing synchronous network I/O (pushing to Redis or calling an external HTTP API) *inside* an open PostgreSQL transaction holds row locks across network latency spikes and exhausts connection pools.
</p>
<p>
<strong>The Magma Solution:</strong> <code>bin/outbox_publisher.php</code> claims batches atomically without holding a database transaction open across Redis network pushes, keeping PostgreSQL lock durations sub-millisecond.
</p>
<h4>4. Graceful Daemon Termination and Deadlocks</h4>
<p>
Background workers run in continuous loops (<code>while ($this-&gt;running)</code>). If a worker blocks indefinitely on Redis during deployment <code>SIGTERM</code>, orchestrators are forced to <code>SIGKILL</code> it mid-operation.
</p>
<p>
<strong>The Magma Solution:</strong> <code>QueueWorkerDaemon</code> registers <code>pcntl_signal</code> handlers, uses bounded <code>popReliable()</code> timeouts so shutdown flags are evaluated promptly, disconnects database handles cleanly, and invokes <code>$container-&gt;flushInstances()</code> per job.
</p>
<h4>5. Cross-Tenant Rate Limit Starvation</h4>
<p>
If rate-limit keys are scoped only by route or IP, a spike or attack on Tenant A can exhaust shared counters and starve Tenant B.
</p>
<p>
<strong>The Magma Solution:</strong> <code>RedisRateLimiter</code> (<code>magma/security/RedisRateLimiter.php</code>) injects <code>TenantContext</code> and scopes Redis rate-limit keys per tenant, isolating rate-limit buckets across organizations.
</p>
<h4>6. Dual-Write Events and Exception Aggregation</h4>
<p>
When an event such as <code>UserRegisteredEvent</code> triggers multiple listeners:
</p>
<ol class="syllabus-list">
<li><strong>The Dual Write:</strong> Side-effects are persisted through <code>OutboxJobRepository</code> inside the ACID transaction rather than firing raw network calls before commit.</li>
<li><strong>Listener Isolation:</strong> <code>EventDispatcher</code> (<code>magma/events/EventDispatcher.php</code>) aggregates listener throwables and throws <code>EventDispatchException</code> (<code>magma/events/EventDispatchException.php</code>) only after running remaining independent listeners, ensuring deterministic transaction rollback without silently skipping sibling listeners.</li>
</ol>
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
If you want to test a service such as <code>RegistrationService</code>, you do not need a live PostgreSQL database. You inject in-memory fakes for <code>UserCommandInterface</code>, <code>EventDispatcherInterface</code>, and <code>TransactionManagerInterface</code>, verifying domain orchestration instantly without booting external infrastructure.
</p>
<h3>Chapter 13.2: Deterministic Time (The Clock Interface)</h3>
<h4>Subject & Intent: Eradicating Hidden Global State</h4>
<p>
Time is inherently non-deterministic. Every time you call <code>time()</code>, <code>new DateTime()</code>, or SQL's <code>NOW()</code>, the output changes. If your application logic relies on these native functions (for instance, checking if a remember-me or password-reset token has expired), it becomes a hidden global dependency. This makes testing time-sensitive features flaky.
</p>
<p>
To resolve this, Magma implements a strict <strong><code>Magma\contracts\ClockInterface</code></strong> (<code>magma/contracts/ClockInterface.php</code>). Rather than calling native time functions, repositories and services inject <code>ClockInterface</code>.
<br>
In production (<code>InfrastructureServiceProvider</code>), the DI container binds this to <code>Magma\infrastructure\time\SystemClock</code> (<code>magma/infrastructure/time/SystemClock.php</code>).
<br>
In testing, we bind it to <code>Magma\infrastructure\time\MockClock</code> (<code>magma/infrastructure/time/MockClock.php</code>). This allows test suites to freeze, advance, or rewind time deterministically without pausing the CPU!
</p>
</div>
<div class="chapter-module" id="module-14-frontend-architecture-deep-freeze-css-layers"><h2 class="chapter-title">Module 14: Frontend Architecture: Deep Freeze & CSS Layers</h2>
<h3>Chapter 14.1: Deeply Immutable Reactive State Store</h3>
<p>
The client-side Vanilla ES6 architecture is as robust as the backend. Rather than relying on heavy frameworks like React or Vue, Magma implements a lightweight <code>ObservableStore.js</code>.
</p>
<p>
It employs a recursive <code>_deepFreeze()</code> algorithm that locks deeply nested state objects from rogue frontend mutations. Developers must dispatch explicit actions to mutate state, enforcing strict unidirectional data flow.
</p>
<h3>Chapter 14.2: Defensive Garbage Collection</h3>
<p>
In dynamic single-page applications, DOM elements are frequently created and destroyed. Standard event listeners create "zombie" memory leaks.
</p>
<p>
Magma's global event delegators utilize defensive <code>isConnected</code> checks to gracefully unbind themselves if their target component is ripped from the DOM dynamically, ensuring clean garbage collection.
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
Magma provides a static analysis linter (<code>bin/audit_schema.php</code>, powered by <code>TenantSecurityAuditor</code>) alongside static AST rules on the <code>lava</code> testing branch (such as <code>tests/PHPStan/SuperglobalBanRule.php</code>), verifying that:
</p>
<ol class="syllabus-list">
<li>Multi-tenant foreign keys are correctly indexed.</li>
<li>Direct superglobal usage is statically prohibited inside business services and controllers.</li>
</ol>
<p>
If boundaries are breached, the linter fails the CI/CD pipeline.
</p>
<h3>Chapter 15.2: Constant-Time B-Tree Keyset Pagination</h3>
<p>
Standard SQL pagination (<code>LIMIT 100 OFFSET 10000</code>) degrades linearly in performance (O(N)).
</p>
<p>
Magma utilizes <strong>Keyset Seeking</strong> (<code>WHERE id &lt; :last_id</code> / <code>WHERE id &gt; :cursor_last_id</code> in <code>AbstractQueryRepository::cursorPaginate()</code>). By leveraging B-Tree indexes, the database jumps directly to the cursor boundary, delivering predictable keyset performance regardless of table offset depth.
</p>
<h3>Chapter 15.3: Memory-Streaming Generators</h3>
<p>
Repositories returning large collections do not load the entire dataset into memory as a massive array. Instead, repositories such as <code>SiteReviewQueryRepository</code> stream records using PHP generators (<code>yield</code>), keeping RAM consumption flat during heavy workloads.
</p>
<h3>Chapter 15.4: Zero-Trust Cross-Domain SSO Handoff & Role Policy</h3>
<p>
In a multi-tenant platform where each tenant has its own domain (e.g., <code>tenant-a.com</code> vs. <code>tenant-b.com</code>), global session cookies must never leak authentication state across boundaries.
</p>
<p>
Magma solves this natively using <strong>Zero-Trust Cross-Domain SSO</strong>. When <code>TenantSecurityMiddleware</code> (<code>magma/middleware/TenantSecurityMiddleware.php</code>) detects that an authenticated user belongs to a different <code>tenant_id</code> than the active host domain in <code>AuthTenantMode::TenantScoped</code>, it issues a short-lived SSO token via <code>AuthenticationService::issueSsoToken()</code> (delegating to <code>RememberMeService::generateToken($userId, 60)</code> backed by <code>RememberTokenRepository</code> in <code>magma/repositories/RememberTokenRepository.php</code>), calls <code>$this-&gt;authService-&gt;logout()</code> to clear the mismatched session, resolves the user's true domain via <code>TenantContextProviderInterface::resolveDomainByTenantId()</code>, and redirects them cleanly with <code>?sso=...</code>. Downstream authorization checks remain decoupled from raw strings via <code>RolePolicyInterface</code> and <code>ConfigRolePolicy</code> (<code>magma/security/ConfigRolePolicy.php</code>).
</p>
</div>
<div class="chapter-module" id="module-16-the-lava-hardening-phase-enterprise-quality-control"><h2 class="chapter-title">Module 16: The Lava Hardening Phase - Enterprise Quality Control</h2>
<h3>Chapter 16.1: The Eradication of Legacy Facades and "Magic"</h3>
<p>
Enterprise software accumulates technical debt primarily through backward compatibility. To achieve architectural purity, Magma underwent a "Lava" hardening phase where legacy facades and unused abstractions were systematically eliminated.
</p>
<p>
The framework entirely abandoned primitive tuple arrays in the Routing engine in favor of strongly-typed <code>RouteDefinition</code> Value Objects. Backward-compatible wrappers and legacy shims were purged, forcing modern, object-oriented contracts at every layer.
</p>
<h3>Chapter 16.2: Mathematical Type Safety via PHPStan Level 9 & <code>strict_types</code></h3>
<p>
Type safety is the ultimate defense against runtime defects. The Magma core targets <strong>PHPStan Level 9</strong> (run on the <code>lava</code> testing branch alongside <code>tests/PHPStan/SuperglobalBanRule.php</code>) and enforces <code>declare(strict_types=1);</code> across <code>100%</code> of core PHP files in <code>magma/</code>.
</p>
<p>
By enforcing strict scalar types, demanding explicit array shapes, and preventing <code>mixed</code> types from leaking through boundaries, the framework eliminates silent type-coercion bugs before code ever reaches production.
</p>
<h3>Chapter 16.3: Advanced Cryptography and Boundary Enforcement</h3>
<p>
Security is not bolted on; it is embedded into the core.
</p>
<ol class="syllabus-list">
<li><strong>Argon2id Hashing:</strong> <code>AuthenticationService</code> and <code>RegistrationService</code> use <code>PASSWORD_ARGON2ID</code>—a memory-hard hashing algorithm that defends against GPU-based brute-force attacks.</li>
<li><strong>Transparent Rehashing:</strong> <code>AuthenticationService</code> detects outdated password hashes during successful logins and transparently upgrades them to Argon2id via <code>UserCommandRepository::updatePassword()</code>.</li>
<li><strong>Strict Headers:</strong> <code>SecurityHeadersMiddleware</code> injects <code>Permissions-Policy</code> and defensive security headers by default, reducing the application's attack surface.</li>
</ol>
<h3>Chapter 16.4: The Lava Testing Infrastructure - Zero Cross-Tenant Leakage</h3>
<p>
While Magma represents the dependency-free core architecture, the <strong>Lava</strong> branch represents its hardened, tested realization before being pulled downstream. Lava provides Database Integration test suites for downstream modules (<code>tests/Integration/Persistence/DatabaseIntegrationTestCase.php</code>).
<br>
By wrapping PHPUnit tests in isolated PostgreSQL <code>BEGIN/ROLLBACK</code> transactions, Lava prevents database pollution while keeping execution fast. Downstream developers extend <code>DatabaseIntegrationTestCase</code> to prove their CQRS repositories enforce <code>tenant_id</code> boundaries with zero cross-tenant leakage.
</p>
<h3>Chapter 16.5: Deterministic Time, Factories, and Headless Execution</h3>
<p>
Lava pairs core contracts with industry-agnostic test harnesses:
</p>
<ol class="syllabus-list">
<li><strong>Deterministic <code>MockClock</code>:</strong> <code>Magma\infrastructure\time\MockClock</code> replaces non-deterministic <code>time()</code> calls so tests can freeze, advance, or rewind wall-clock timestamps deterministically without sleeping.</li>
<li><strong>Agnostic Factories:</strong> <code>AbstractModelFactory</code> (<code>tests/Integration/Persistence/Factories/AbstractModelFactory.php</code>) and <code>GenericTenantFactory</code> (<code>tests/Integration/Persistence/Factories/GenericTenantFactory.php</code>) hydrate relational test fixtures cleanly without repetitive boilerplate SQL.</li>
<li><strong>Async Event Marshalling & Headless HTTP:</strong> <code>AsyncIntegrationTestCase</code> (<code>tests/Integration/Persistence/AsyncIntegrationTestCase.php</code>) drains the Transactional Outbox synchronously in tests, while <code>HttpIntegrationTestCase</code> (<code>tests/Integration/Http/HttpIntegrationTestCase.php</code>) dispatches simulated <code>Request</code> instances directly through <code>Application::handle()</code> and <code>TenantSecurityMiddleware</code> without spinning up a web server.</li>
</ol>
<h3>Chapter 16.6: CI/CD Gates and AST Enforcement</h3>
<p>
GitHub Actions (<code>.github/workflows/ci.yml</code> and <code>.github/workflows/enforce-main-purity.yml</code>) boots PostgreSQL and Redis services to execute the full suite on every commit while physically guarding <code>main</code> purity. Lava also runs custom PHPStan AST rules (<code>tests/PHPStan/SuperglobalBanRule.php</code>) at <strong>Level 9</strong> to ban direct superglobal access (<code>$_POST</code>, <code>$_GET</code>, <code>$_SESSION</code>) outside designated boundary adapters.
</p>
<blockquote class="syllabus-quote">
<strong>CRITICAL WARNING FOR LAVA BRANCH:</strong> The <code>lava</code> branch contains testing libraries (<code>PHPUnit</code>, <code>PHPStan</code>) and CI/CD configuration. <strong>NEVER</strong> merge <code>lava</code> back into <code>main</code>. The <code>main</code> branch must remain 100% pure, dependency-free Magma Core.<br>
</blockquote>
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