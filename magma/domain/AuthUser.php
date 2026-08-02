<?php

namespace Magma\domain;

/**
 * Authenticated User Domain Entity
 *
 * Purpose:
 * - Encapsulate the data structure of an authenticated user session.
 *
 * Why / Why this design:
 * - Decouples the session storage array structure from the raw database query output.
 * - Ensures that only specific, non-sensitive fields are exposed to the session layer.
 *
 * Teaching notes:
 * - Explicitly converting the array to an entity prevents password hashes or other 
 *   sensitive fields returned by the repository from accidentally leaking into the session payload.
 */
class AuthUser
{
    private int $id;
    private string $name;
    private string $role;
    private string $email;
    private ?int $vendorId;

    /**
     * Constructs a new AuthUser entity.
     *
     * Execution Flow:
     * 1. Extracts the primary key, defaulting to 0 if missing.
     * 2. Extracts the name and role.
     * 3. Extracts the email.
     * 4. Extracts the vendor_id if present.
     *
     * Logic behind the logic:
     * - By specifically mapping only the necessary fields from the `$data` array, 
     *   we ensure that sensitive fields (like password hashes) cannot be accidentally 
     *   retained in the object state.
     *
     * @param array $data The raw user array from the database.
     */
    public function __construct(array $data)
    {
        $this->id = (int)($data['id'] ?? 0);
        $this->name = $data['name'] ?? '';
        $this->role = $data['role'] ?? 'user';
        $this->email = $data['email'] ?? '';
        $this->vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
    }

    public function hasVendorId(): bool
    {
        return $this->vendorId !== null;
    }

    public function getVendorId(): ?int
    {
        return $this->vendorId;
    }

    /**
     * Converts the entity into an array suitable for session storage.
     *
     * Execution Flow:
     * 1. Constructs an associative array mapping class properties to session keys.
     * 2. Returns the array to the AuthenticationService.
     *
     * Logic behind the logic:
     * - This explicit conversion acts as a boundary. The session layer should only 
     *   store primitives, not serialized domain objects, to prevent class hydration 
     *   vulnerabilities during deserialization on subsequent requests.
     *
     * @return array
     */
    public function toSessionArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'email' => $this->email,
            'vendor_id' => $this->vendorId
        ];
    }
}
