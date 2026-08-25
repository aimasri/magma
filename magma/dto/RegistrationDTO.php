<?php

declare(strict_types=1);

namespace Magma\dto;

readonly class RegistrationDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {}
}
