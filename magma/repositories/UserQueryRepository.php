<?php

namespace Magma\repositories;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\models\AbstractQueryRepository;
use Magma\domain\AuthUser;

/**
 * Title: User Query Repository
 * Purpose:
 * - Handles specialized read operations for User domain entities.
 * Why / Why this design:
 * - Adheres to CQRS principles by inheriting from AbstractQueryRepository, restricting operations to the read-replica.
 * - Enforces separation of concerns by completely isolating SQL retrieval from business and domain logic.
 * Teaching notes:
 * - This repository ensures that User read operations are highly performant and scalable.
 */
class UserQueryRepository extends AbstractQueryRepository implements UserQueryInterface
{
    public function findByEmail(string $email): ?AuthUser
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return is_array($row) ? new AuthUser($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForAuth(string $email): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, email, role, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($result) ? $result : null;
    }

    public function findById(int $id): ?AuthUser
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return is_array($row) ? new AuthUser($row) : null;
    }

    public function getPasswordChangedAt(int $id): ?int
    {
        $stmt = $this->getDb()->prepare("SELECT password_changed_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $val = $stmt->fetchColumn();
        if (!$val) {
            return null;
        }
        $time = strtotime((string)$val);
        return $time !== false ? $time : null;
    }
}
