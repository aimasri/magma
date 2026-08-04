<?php

namespace Magma\repositories;

use Magma\interfaces\cqrs\UserCommandInterface;
use Magma\database\BaseCommandRepository;
use Magma\domain\UserRegistration;

class UserCommandRepository extends BaseCommandRepository implements UserCommandInterface
{
    public function create(UserRegistration $registration): int
    {
        try {
            $stmt = $this->getDb()->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([
                $registration->getName(),
                $registration->getEmail(),
                $registration->getHashedPassword()
            ]);
            return (int) $this->getDb()->lastInsertId();
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new \Magma\domain\exceptions\DuplicateResourceException('This email is already registered.', 0, $e);
            }
            throw $e;
        }
    }

    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $stmt = $this->getDb()->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
    }
}
