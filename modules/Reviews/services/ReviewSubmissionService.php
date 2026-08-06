<?php

namespace Modules\Reviews\services;

use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Modules\Reviews\domain\Review;

/**
 * Review Submission Service
 *
 * Purpose:
 * - Handle the business logic of processing and saving a new user review.
 * - Map raw incoming request data to the format expected by the repository.
 *
 * Why / Why this design:
 * - Implements the Service Layer pattern. By extracting the data mapping and 
 *   database interaction from the controller, the controller remains strictly 
 *   focused on HTTP routing (request in, response out).
 *
 * Teaching notes:
 * - In a more complex application, this service might also dispatch events 
 *   (e.g., `ReviewSubmittedEvent`), notify administrators, or interact with 
 *   spam detection services before saving.
 */
class ReviewSubmissionService
{
    private SiteReviewCommandInterface $siteReviewRepository;

    public function __construct(SiteReviewCommandInterface $siteReviewRepository)
    {
        $this->siteReviewRepository = $siteReviewRepository;
    }

    /**
     * Submits a new review to the database.
     * 
     * Execution Flow:
     * 1. Accept a raw associative array of validated review data.
     * 2. Instantiate a Review domain entity to encapsulate data mapping and defaults.
     * 3. Delegate the actual SQL insertion to the repository.
     * 
     * Logic behind the logic:
     * - Data mapping happens here to ensure the repository remains dumb and 
     *   only cares about executing queries, while the controller remains ignorant 
     *   of database schema requirements.
     * 
     * @param array $data Validated review data containing author, comment, and rating.
     * @return bool True if successful, false otherwise.
     */
    public function submit(array $data): bool
    {
        $review = new Review($data);
        return $this->siteReviewRepository->addReview($review);
    }
}
