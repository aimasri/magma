<?php

namespace Magma\interfaces\cqrs;

/**
 * Title: Query Interface (CQRS Read Model)
 *
 * Purpose:
 * - Represents the read model in a CQRS architecture.
 *
 * Why this design:
 * - CQRS Pattern: Strictly separates read-only queries from state-modifying commands.
 * - Read Optimization: Allows for heavy optimization (e.g., CTEs, materialized views) without breaking write logic.
 *
 * Teaching notes:
 * - Queries should never modify state.
 * - Queries should return Data Transfer Objects (DTOs) or associative arrays, not Active Record models.
 */
interface QueryInterface
{
}
