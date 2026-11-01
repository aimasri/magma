<?php

declare(strict_types=1);

namespace Magma\events;

/**
 * Title: Transactional Listener Contract
 *
 * Purpose:
 * - Marker contract indicating that a listener performs critical transactional operations
 *   (such as writing to the transactional outbox table) that must NOT be silently swallowed by the
 *   EventDispatcher, ensuring the enclosing transaction can roll back on failure.
 *
 * Why / Why this design:
 * - Solves the critical outbox consistency race where non-transactional listeners should fail safely
 *   without crashing the main request, but transactional listeners MUST propagate exceptions
 *   so that database transactions roll back if the event recording fails.
 */
interface TransactionalListenerInterface extends EventListenerInterface
{
}
