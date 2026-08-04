<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

interface UserCommandInterface extends \Magma\database\CommandInterface
{
    public function create(\Magma\domain\UserRegistration $registration): int;
    public function updatePassword(int $userId, string $hashedPassword): void;
}
