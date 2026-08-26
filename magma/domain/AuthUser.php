<?php

namespace Magma\domain;

/**
 * Title: Authenticated User Domain Entity
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
readonly class AuthUser
{
    private int $id;
    private string $name;
    private string $role;
    private string $email;
    private ?int $tenantId;

    /**
     * Constructs a new AuthUser entity.
     *
     * Execution Flow:
     * 1. Extracts the primary key, defaulting to 0 if missing.
     * 2. Extracts the name and role.
     * 3. Extracts the email.
     * 4. Extracts the tenant_id if present.
     *
     * Logic behind the logic:
     * - By specifically mapping only the necessary fields from the `$data` array, 
     *   we ensure that sensitive fields (like password hashes) cannot be accidentally 
     *   retained in the object state.
     *
     * @param array<string, mixed> $data The raw user array from the database.
     */
    public function __construct(array $data)
    {
        $idVal = $data['id'] ?? 0;
        $this->id = is_scalar($idVal) ? (int)$idVal : 0;
        
        $nameVal = $data['name'] ?? '';
        $this->name = is_scalar($nameVal) ? (string)$nameVal : '';
        
        $roleVal = $data['role'] ?? 'user';
        $this->role = is_scalar($roleVal) ? (string)$roleVal : 'user';
        
        $emailVal = $data['email'] ?? '';
        $this->email = is_scalar($emailVal) ? (string)$emailVal : '';
        
        if (isset($data['tenant_id'])) {
            $this->tenantId = is_scalar($data['tenant_id']) ? (int)$data['tenant_id'] : null;
        } else {
            $this->tenantId = null;
        }
    }

    public function hasTenantId(): bool
    {
        return $this->tenantId !== null;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getEmail(): string
    {
        return $this->email;
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
     * @return array<string, mixed>
     */
    public function toSessionArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'email' => $this->email,
            'tenant_id' => $this->tenantId
        ];
    }
}
