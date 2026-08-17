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
        
        return $row ? new AuthUser($row) : null;
    }

    public function findForAuth(string $email): ?array
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, email, role, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function findById(int $id): ?AuthUser
    {
        $stmt = $this->getDb()->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row ? new AuthUser($row) : null;
    }
}
