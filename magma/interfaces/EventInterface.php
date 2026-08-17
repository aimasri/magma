<?php

namespace Magma\interfaces;

/**
 * Title: Base Event Interface
 *
 * Purpose:
 * - Acts as a marker interface for all domain and application events.
 *
 * Why / Why this design:
 * - Enforces strong typing when passing events around the application, ensuring
 *   that dispatchers and listeners only process valid event objects.
 *
 * Teaching notes:
 * - Marker interfaces contain no methods but are vital for polymorphism and
 *   type hinting across decoupled systems.
 */
interface EventInterface
{
}
