You now have a powerful $this->travelTo('2026-12-25 08:00:00') helper available in your tests. This means when downstream developers port their domain modules, they can instantly simulate holiday rushes or overnight shifts without relying on date() or time()

---

Downstream developers can simply extend `AbstractModelFactory` to create a `RecipeFactory`. They will instantly have the power to write `$this->recipeFactory()->create(['yield' => 500])` in their tests without writing a single line of raw SQL! (Note: Investigate this further as JS might currently be used for data seeding).

---

We have an `HttpIntegrationTestCase` that allows developers to write headless tests simulating incoming HTTP requests to verify routing, API responses, and the `TenantSecurityMiddleware` without ever needing to boot a local web server.
