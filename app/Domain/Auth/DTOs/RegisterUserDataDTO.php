<?php

namespace App\Domain\Auth\DTOs;

final readonly class RegisterUserDataDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }

    /**
     * @param array{name: string, email: string, password: string} $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
