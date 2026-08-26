# Magma Framework: Lava (Testing & CI/CD)

> **Note:** You are currently viewing the **Lava** branch.
> For the comprehensive framework documentation, architectural philosophy, and the 15-module Masterclass Syllabus, please switch back to the `main` branch.

---

## 🌋 The Lava Environment

Welcome to the second stage of the Magma architectural evolution. While the `main` branch (Magma) represents the pure, dependency-free core logic, the **Lava** branch introduces the infrastructure required to harden that core. 

Lava is what happens when Magma hits the surface environment and cools. Tests and pipelines give the raw code structure, predictability, and safety.

### Branch Objectives

The primary purpose of this branch is to introduce and enforce strict quality control boundaries without polluting the framework's runtime environment. 

1. **Zero Runtime Dependencies:** We strictly enforce that all external packages (like testing tools) are relegated to `require-dev`. The core application remains 100% dependency-free in production.
2. **Maximum Strictness Static Analysis:** Implementation of PHPStan at Level 9 to mathematically prove type safety, forcing explicit array shapes and eliminating ambiguous `mixed` types.
3. **Automated Testing:** Integration of PHPUnit for both Unit and Integration testing, verifying PSR-4 autoloading and architectural boundaries.
4. **CI/CD Readiness:** Establishing the baseline configuration needed for automated deployment pipelines.

### Getting Started

To initialize the Lava environment locally:

```bash
# 1. Install development dependencies (PHPStan & PHPUnit)
composer install

# 2. Run the automated test suite
vendor/bin/phpunit

# 3. Run maximum-strictness static analysis
vendor/bin/phpstan analyse
```

### The Hardening Phase

When you first run PHPStan on this branch, it will detect hundreds of type-related errors. This is intentional. The objective within the Lava environment is to methodically resolve these errors by enforcing strict types, explicit iterables, and robust type-narrowing assertions across the entire codebase—transforming it into enterprise-hardened software.

---
*For full architectural rules and educational guidelines, please refer to the `AGENTS.md` and `textbook.md` files.*
