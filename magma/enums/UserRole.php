<?php

namespace Magma\enums;

/**
 * UserRole — Defines the roles a user can possess in the system.
 * 
 * Purpose:
 * - Centralize all defined user roles to avoid hardcoded strings.
 * - Provide domain-specific helper methods to simplify access control checks and routing.
 * 
 * Why / Why this design:
 * - Centralizing role checks inside an Enum adheres to the DRY principle. Instead of 
 *   scattering `in_array($role, ['vendor_admin', ...])` throughout controllers and middleware, 
 *   we delegate that knowledge to the domain layer. If roles are added or changed, 
 *   only this enum needs to be updated.
 * 
 * Teaching notes:
 * - In PHP 8.1+, true Enums should be used. For legacy compatibility or specific 
 *   behavioral needs, defining constants on a class is an acceptable fallback.
 */
class UserRole
{
    public const VENDOR_ADMIN = 'vendor_admin';
    public const VENDOR_STAFF = 'vendor_staff';

    /**
     * Determines if a given role string belongs to a vendor.
     * 
     * Execution Flow:
     * 1. Check if the provided role strictly matches any defined vendor-level roles.
     * 
     * Logic behind the logic:
     * - By encapsulating this check, the rest of the application remains ignorant of 
     *   exactly *which* roles constitute a vendor. It only knows *if* the user is a vendor.
     * 
     * @param string|null $role
     * @return bool
     */
    public static function isVendorRole(?string $role): bool
    {
        if ($role === null) {
            return false;
        }
        return in_array($role, [self::VENDOR_ADMIN, self::VENDOR_STAFF], true);
    }

    /**
     * Returns the appropriate dashboard base path for a given role.
     * 
     * Execution Flow:
     * 1. Check if the user is a vendor.
     * 2. Return the vendor dashboard path or the standard user dashboard path.
     * 
     * Logic behind the logic:
     * - Centralizing route paths tied to roles prevents controllers from hardcoding 
     *   destination URLs, keeping routing logic isolated.
     * 
     * @param string|null $role
     * @return string
     */
    public static function dashboardPath(?string $role): string
    {
        if (self::isVendorRole($role)) {
            return '/admin';
        }
        return '/user';
    }
}
