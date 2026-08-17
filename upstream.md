# Magma Framework Upstream Candidates

This document tracks framework evolutions, architectural improvements, and enterprise hardening candidates identified during downstream application development in **FussyBaby** that should be reviewed for upstream inclusion in the core **Magma** repository.

---

## Candidate 1: Optional `TenantContext` in Base Repositories (`AbstractQueryRepository` & `AbstractCommandRepository`)

### What it is
Allow `TenantContext` to be nullable in `AbstractQueryRepository` and `AbstractCommandRepository`:
```php
protected ?TenantContext $tenantContext = null;

public function __construct(
    DatabaseConnectionManager $dbManager,
    ?TenantContext $tenantContext = null
) {
    $this->dbManager = $dbManager;
    $this->tenantContext = $tenantContext;
}

protected function getTenantId(): ?int
{
    return ($this->tenantContext !== null && $this->tenantContext->hasVendorId())
        ? $this->tenantContext->getVendorId()
        : null;
}
```

### Why it matters
- **Circular Dependency Prevention:** Foundational repositories that *provide* the tenant context (such as `VenueQueryRepository` which feeds `VenueContextService` to fulfill `TenantContextProviderInterface`) cannot depend on `TenantContext` in their constructor without causing a circular DI resolution deadlock (`TenantContext` -> `TenantContextProvider` -> `VenueQueryRepository` -> `TenantContext`).
- **Flexibility:** Allows global lookup repositories and system-level repositories that operate across tenants or before a tenant is resolved to extend the base repository infrastructure cleanly.

---

## Candidate 2: `\ArrayAccess` Implementation on `VendorDTO` (and Immutable View DTOs)

### What it is
Implement `\ArrayAccess` (with read-only enforcement) and `toArray()` on `VendorDTO`:
```php
class VendorDTO implements \ArrayAccess
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $tagline,
        public readonly string $email,
        public readonly int $plan_id,
        public readonly string $subscription_status,
        public readonly ?string $billing_cycle_anchor,
        public readonly ?string $payment_gateway_customer_id,
        public readonly array $theme_settings = []
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, (string)$offset) && isset($this->{(string)$offset});
    }

    public function offsetGet(mixed $offset): mixed
    {
        return property_exists($this, (string)$offset) ? $this->{(string)$offset} : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('VendorDTO is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('VendorDTO is immutable.');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'email' => $this->email,
            'plan_id' => $this->plan_id,
            'subscription_status' => $this->subscription_status,
            'billing_cycle_anchor' => $this->billing_cycle_anchor,
            'payment_gateway_customer_id' => $this->payment_gateway_customer_id,
            'theme_settings' => $this->theme_settings,
        ];
    }
}
```

### Why it matters
- **View Compatibility:** Shared view templates, partials, and view composers can access properties using standard array notation (`$vendor['theme_settings']`) and property access (`$vendor->theme_settings`) interchangeably.
- **Encapsulation & Immutability:** Enforces domain immutability by throwing `LogicException` on any mutation attempt while remaining ergonomic in template engines.

---

## Candidate 3: Multi-Directory Fallback Resolution in `TemplateEngine`

### What it is
Enhance `TemplateEngine::resolveLayoutPath()` to search candidate directories:
```php
private function resolveLayoutPath(string $layout): ?string
{
    if ((str_contains($layout, '::') || str_starts_with($layout, '@')) && $this->loader !== null) {
        return $this->loader->resolvePath($layout);
    }

    $searchPaths = array_filter([
        $this->layoutPath,
        $this->viewsPath . '/layouts',
        $this->viewsPath . '/partials',
        $this->viewsPath,
        $this->partialsPath,
    ]);

    foreach ($searchPaths as $baseDir) {
        $candidate = rtrim((string)$baseDir, '/\\') . '/' . ltrim($layout, '/\\');
        if (!str_ends_with($candidate, '.php')) {
            $candidate .= '.php';
        }
        if (file_exists($candidate)) {
            return $candidate;
        }
    }

    throw new \RuntimeException("Layout file not found: {$layout}");
}
```

### Why it matters
- Eliminates hardcoded layout path assumptions when applications organize layouts under `views/layouts/`, `views/partials/`, or root `views/`.
- Supports both `@Namespace/view` and `Namespace::view` modular syntax uniformly across layouts, views, and partials.

---

## Candidate 4: Method Name Segregation in `AbstractCommandRepository` (`executeUpdate` / `executeDelete`)

### What it is
Rename generic low-level table mutation helper methods in `AbstractCommandRepository` from `update()` and `delete()` to `executeUpdate()` and `executeDelete()`:
```php
protected function executeUpdate(string $table, array $data, string $where, array $whereParams = []): int;
protected function executeDelete(string $table, string $where, array $whereParams = []): int;
```

### Why it matters
- **Prevents Signature Conflicts (LSP & ISP):** Concrete domain repositories frequently implement interfaces declaring `public function update(int $id, array $data): bool` or `public function delete(int $id): bool`. Having `protected function update(string $table, ...)` on the abstract base class causes fatal PHP signature mismatch errors when subclasses implement their domain interfaces.
