<?php

namespace Magma\repositories;

use Magma\interfaces\cqrs\UserQueryInterface;
use Magma\database\BaseQueryRepository;
use Magma\domain\AuthUser;

class UserQueryRepository extends BaseQueryRepository implements UserQueryInterface
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
