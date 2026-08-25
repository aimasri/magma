<?php

declare(strict_types=1);

namespace Magma\dto;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $rememberMe
    ) {}
}
