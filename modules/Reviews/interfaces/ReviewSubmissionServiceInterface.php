<?php

declare(strict_types=1);

namespace Modules\Reviews\interfaces;

use Modules\Reviews\dto\ReviewDTO;

/**
 * Title: Review Submission Service Interface
 *
 * Purpose:
 * - Defines the contract for processing review submissions.
 *
 * Why / Why this design:
 * - Follows the Dependency Inversion Principle (DIP), allowing controllers to rely on 
 *   abstractions rather than concrete service classes.
 *
 * Teaching notes:
 * - Inject this interface instead of `ReviewSubmissionService` to facilitate mocking 
 *   in tests and decouple the controller from business logic implementations.
 */
interface ReviewSubmissionServiceInterface
{
    /**
     * Submits a new review for processing.
     *
     * @param ReviewDTO $dto The validated review data transfer object.
     * @return bool True on successful submission, false otherwise.
     */
    public function submit(ReviewDTO $dto): bool;
}
