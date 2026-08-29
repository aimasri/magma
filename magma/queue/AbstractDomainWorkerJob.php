<?php

declare(strict_types=1);

namespace Magma\queue;

use Magma\security\TenantContext;
use InvalidArgumentException;
use Throwable;

/**
 * Title: Abstract Domain Worker Job
 *
 * Purpose:
 * - Base class for background queue jobs that orchestrate business actions via injected Domain Services.
 * - Enforces tenant context isolation, payload validation, and clean separation between messaging infrastructure
 *   and domain business rules.
 *
 * Why / Why this design:
 * - Separation of Concerns: Queue jobs must never contain complex business math or direct SQL queries.
 *   They act purely as adapters that unpack payloads and invoke pure Domain Services.
 * - Multi-Tenant Safety: Automatically binds the worker's thread execution context to the tenant specified
 *   in the job payload, preventing cross-tenant data leaks in long-running worker daemons.
 * - Testability: Isolating domain logic in services allows worker jobs to be unit tested with mock services
 *   without database or Redis infrastructure.
 *
 * Teaching notes:
 * - Notice the Template Method pattern: `handle()` provides the rigid execution pipeline (validate -> bind tenant
 *   -> beforeHandle -> execute -> afterHandle), while child classes only implement `execute()`.
 */
abstract class AbstractDomainWorkerJob implements JobInterface
{
    protected ?TenantContext $tenantContext;

    /**
     * Initializes the worker job with an optional tenant context.
     *
     * Logic behind the logic:
     * - Injecting the TenantContext allows the job to automatically bind to the correct tenant
     *   before executing domain logic, ensuring multi-tenant data isolation.
     *
     * @param TenantContext|null $tenantContext
     */
    public function __construct(?TenantContext $tenantContext = null)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Executes the standardized worker job lifecycle.
     *
     * Execution Flow:
     * 1. Validate the payload schema using `validatePayload()`.
     * 2. Configure the multi-tenant context if a tenant ID is present.
     * 3. Invoke the `beforeHandle()` lifecycle hook.
     * 4. Delegate to the abstract `execute()` method to invoke domain services.
     * 5. Invoke the `afterHandle()` lifecycle hook upon successful completion.
     * 6. Trap any Throwable, invoke `onFailure()`, and re-throw for worker retry management.
     *
     * Logic behind the logic:
     * - Re-throwing the exception after `onFailure()` ensures that the queue driver (RedisQueue/SQS)
     *   knows the job failed and can route it to a dead-letter / failed_jobs queue.
     *
     * @param array<string, mixed> $payload The JSON-decoded queue payload.
     * @throws Throwable
     */
    public function handle(array $payload): void
    {
        try {
            $this->validatePayload($payload);
            $this->bindTenantContext($payload);
            $this->beforeHandle($payload);

            $this->execute($payload);

            $this->afterHandle($payload);
        } catch (Throwable $e) {
            $this->onFailure($payload, $e);
            throw $e;
        }
    }

    /**
     * Binds the tenant context from the incoming payload.
     *
     * @param array<string, mixed> $payload
     */
    protected function bindTenantContext(array $payload): void
    {
        if ($this->tenantContext === null) {
            return;
        }

        $tenantId = $payload['tenant_id'] ?? null;
        if ($tenantId !== null && is_numeric($tenantId)) {
            $this->tenantContext->setTenantId((int) $tenantId);
        }
    }

    /**
     * Validates that the payload contains required fields before executing domain services.
     *
     * @param array<string, mixed> $payload
     * @throws InvalidArgumentException
     */
    protected function validatePayload(array $payload): void
    {
        $requiredKeys = $this->getRequiredPayloadKeys();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                throw new InvalidArgumentException("Missing required payload field [{$key}] in job [" . static::class . "].");
            }
        }
    }

    /**
     * Returns a list of required payload keys for this job.
     *
     * @return string[]
     */
    protected function getRequiredPayloadKeys(): array
    {
        return [];
    }

    /**
     * Hook executed before domain logic runs.
     *
     * @param array<string, mixed> $payload
     */
    protected function beforeHandle(array $payload): void
    {
        // Optional override
    }

    /**
     * Hook executed after domain logic successfully finishes.
     *
     * @param array<string, mixed> $payload
     */
    protected function afterHandle(array $payload): void
    {
        // Optional override
    }

    /**
     * Hook executed when an exception occurs during job handling.
     *
     * @param array<string, mixed> $payload
     * @param Throwable $e
     */
    protected function onFailure(array $payload, Throwable $e): void
    {
        // Optional override for alerting/telemetry
    }

    /**
     * Executes the domain business logic by invoking injected Domain Services.
     *
     * @param array<string, mixed> $payload
     */
    abstract protected function execute(array $payload): void;
}
