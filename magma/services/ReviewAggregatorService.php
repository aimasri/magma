<?php

namespace Magma\services;

use Magma\models\SiteReviewRepositoryInterface;
use Magma\models\XmlReviewRepository;

// ... (comments omitted for brevity, let's keep them in the replace)

/**
 * Review Aggregation Service
 *
 * Purpose:
 * - Collect reviews from disparate sources (local database, legacy XML files).
 * - Normalize external data formats into a uniform structure.
 * - Serve as an integration point for future review sources (e.g., Google Places, Trustpilot).
 *
 * Why / Why this design:
 * - Implements the Facade and Adapter patterns loosely. By hiding the complexity of 
 *   querying multiple repositories, the Controller only needs to call `getAggregatedReviews()`. 
 *   This prevents controllers from becoming bloated orchestrators.
 *
 * Teaching notes:
 * - Merging datasets from different sources dynamically (like XML and SQL) can cause 
 *   performance issues as data grows. External API calls (e.g., Google Places) must NEVER 
 *   be made synchronously during the HTTP request. Instead, they should be fetched via 
 *   asynchronous background queue workers and cached locally.
 */
class ReviewAggregatorService
{
    protected SiteReviewRepositoryInterface $siteReviewRepository;
    protected XmlReviewRepository $xmlReviewRepository;

    public function __construct(SiteReviewRepositoryInterface $siteReviewRepository, XmlReviewRepository $xmlReviewRepository)
    {
        $this->siteReviewRepository = $siteReviewRepository;
        $this->xmlReviewRepository = $xmlReviewRepository;
    }

    /**
     * Consolidate reviews from various sources into a single array.
     * 
     * Execution Flow:
     * 1. Retrieve the generators for both the XML and Database repositories.
     * 2. Rewind both generators to prepare for iteration.
     * 3. Manually interleave the results, yielding one from XML, then one from DB, 
     *    until the limit is reached or all sources are exhausted.
     * 
     * Logic behind the logic:
     * - This acts as a facade, so the controller only makes one call to get 
     *   all reviews regardless of their underlying storage mechanism.
     * - Interleaving the sources prevents "data starvation", ensuring that fresh 
     *   database reviews are displayed even if the XML file is massive.
     * - We track `$yieldedCount` to halt underlying generators exactly when the limit 
     *   is reached, avoiding full file scans on massive legacy XML sources.
     * 
     * @param int $limit
     * @param int $offset
     * @return iterable<\Magma\dto\ReviewDTO>
     */
    public function getAggregatedReviews(int $limit = 20, ?int $lastId = null): iterable
    {
        $xmlGen = $this->xmlReviewRepository->getAll();
        $siteGen = $this->getSiteReviews($limit, $lastId);

        $xmlGen->rewind();
        $siteGen->rewind();

        $sources = [$xmlGen, $siteGen];
        $yieldedCount = 0;

        while ($yieldedCount < $limit) {
            $yieldedThisRound = false;
            foreach ($sources as $source) {
                if ($source->valid()) {
                    yield $source->current();
                    $yieldedCount++;
                    if ($yieldedCount >= $limit) {
                        return;
                    }
                    $source->next();
                    $yieldedThisRound = true;
                }
            }
            if (!$yieldedThisRound) {
                break;
            }
        }
    }

    /**
     * Fetch approved reviews from the database.
     * 
     * Execution Flow:
     * 1. Request approved reviews from the SiteReviewRepository.
     * 2. Catch and log any database exceptions to prevent the homepage from crashing.
     * 
     * Logic behind the logic:
     * - If the database goes down, we still want to show the XML reviews. 
     *   Swallowing the exception and yielding nothing ensures partial availability.
     * - Using `yield from` guarantees this method always returns a valid Generator 
     *   object, even if an exception occurs, which prevents fatal errors when 
     *   interleaving sources.
     * 
     * @param int $limit
     * @param int|null $lastId
     * @return iterable<\Magma\dto\ReviewDTO>
     */
    private function getSiteReviews(int $limit = 20, ?int $lastId = null): \Generator
    {
        try {
            yield from $this->siteReviewRepository->getApprovedReviews($limit, $lastId);
        } catch (\Exception $e) {
            error_log("Failed to load site reviews: " . $e->getMessage());
        }
    }
}
