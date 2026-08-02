<?php

namespace Magma\interfaces\cqrs;

/**
 * Title: Analytics CQRS Interface
 *
 * Purpose:
 * - Represents specialized aggregate queries distinct from standard CRUD reads.
 *
 * Why this design:
 * - Query Segregation: Analytics often require massive data aggregation and shouldn't block standard domain operations.
 * - Performance Isolation: Separating this prevents N+1 performance bottlenecks in normal Repositories.
 *
 * Teaching notes:
 * - Analytics classes should exclusively read from read-replicas or data warehouses, never the primary write database.
 */
interface AnalyticsInterface
{
    /**
     * Aggregates data based on provided metrics and criteria.
     *
     * @param array $metrics What to calculate (e.g., sums, averages).
     * @param array $criteria Filters for the analytics window (e.g., date ranges).
     * @return array The aggregated analytics results.
     */
    public function aggregate(array $metrics, array $criteria = []): array;
}
