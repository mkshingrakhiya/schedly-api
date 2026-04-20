<?php

namespace App\Domain\Auth\DTOs;

final readonly class LoginUserDataDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array{email: string, password: string}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            email: $validated['email'],
            password: $validated['password'],
        );
    }
}
