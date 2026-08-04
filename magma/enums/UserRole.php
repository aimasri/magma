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
 * - In PHP 8.1+, true Enums should be used.
 */
enum UserRole: string
{
    case VENDOR_ADMIN = 'vendor_admin';
    case VENDOR_STAFF = 'vendor_staff';

    /**
     * Determines if a given role string belongs to a vendor.
     * 
     * @param string|null $role
     * @return bool
     */
    public static function isVendorRole(?string $role): bool
    {
        if ($role === null) {
            return false;
        }
        
        $enum = self::tryFrom($role);
        if (!$enum) {
            return false;
        }
        
        return match ($enum) {
            self::VENDOR_ADMIN, self::VENDOR_STAFF => true,
        };
    }

    /**
     * Returns the appropriate dashboard base path for a given role.
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
