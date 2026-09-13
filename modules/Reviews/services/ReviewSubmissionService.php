<?php

namespace Modules\Reviews\services;

use Modules\Reviews\interfaces\cqrs\SiteReviewCommandInterface;
use Modules\Reviews\domain\Review;
use Modules\Reviews\interfaces\ReviewSubmissionServiceInterface;
use Modules\Reviews\dto\ReviewDTO;

/**
 * Title: Review Submission Service
 *
 * Purpose:
 * - Handle the business logic of processing and saving a new user review.
 * - Map incoming DTO data to the format expected by the repository.
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
class ReviewSubmissionService implements ReviewSubmissionServiceInterface
{
    /**
     * Initializes the ReviewSubmissionService.
     *
     * Execution Flow:
     * 1. Injects the SiteReviewCommandInterface and ReviewFactoryInterface.
     * 2. Binds these dependencies via constructor property promotion to manage review creation and persistence.
     *
     * Logic behind the logic:
     * - Abstract Factory and Repository Patterns: Offloads object creation to a factory and
     *   persistence to a repository, keeping the service focused on workflow orchestration.
     *
     * @param SiteReviewCommandInterface $siteReviewRepository
     * @param \Modules\Reviews\interfaces\ReviewFactoryInterface $reviewFactory
     */
    public function __construct(
        private readonly SiteReviewCommandInterface $siteReviewRepository,
        private readonly \Modules\Reviews\interfaces\ReviewFactoryInterface $reviewFactory
    ) {}

    /**
     * Submits a new review to the database.
     * 
     * Execution Flow:
     * 1. Accept a DTO of validated review data.
     * 2. Instantiate a Review domain entity to encapsulate data mapping and defaults.
     * 3. Delegate the actual SQL insertion to the repository.
     * 
     * Logic behind the logic:
     * - Data mapping happens here to ensure the repository remains dumb and 
     *   only cares about executing queries, while the controller remains ignorant 
     *   of database schema requirements.
     * 
     * @param ReviewDTO $dto Validated review DTO containing author, comment, and rating.
     * @return bool True if successful, false otherwise.
     */
    public function submit(ReviewDTO $dto, int $tenantId): bool
    {
        $review = $this->reviewFactory->create($tenantId, $dto->author, $dto->comment, $dto->rating);
        return $this->siteReviewRepository->addReview($review);
    }
}
