<?php

namespace Magma\enums;

/**
 * Password Reset Status
 *
 * Purpose:
 * - Define explicit outcome states for the password reset lifecycle.
 * - Provide a strict contract between the Domain Service and the HTTP Controller.
 *
 * Why / Why this design:
 * - By returning specific Enums rather than generic booleans, we eliminate "magic values" 
 *   and ambiguous `true`/`false` returns. The controller no longer has to guess why a 
 *   process failed; it can predictably branch its HTTP response based on domain events.
 *
 * Teaching notes:
 * - Enums (introduced in PHP 8.1) provide type-safety. Using them for service responses 
 *   is a highly robust alternative to throwing multiple specific Exceptions for expected 
 *   control flow (like "user not found" or "invalid token").
 */
enum PasswordResetStatus
{
    case SUCCESS;
    case USER_NOT_FOUND;
    case INVALID_TOKEN;
    case SYSTEM_ERROR;
}
