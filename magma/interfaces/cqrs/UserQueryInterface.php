<?php

declare(strict_types=1);

namespace Magma\interfaces\cqrs;

interface UserQueryInterface extends \Magma\database\QueryInterface
{
    public function findByEmail(string $email): ?\Magma\domain\AuthUser;
    public function findForAuth(string $email): ?array;
    public function findById(int $id): ?\Magma\domain\AuthUser;
}
