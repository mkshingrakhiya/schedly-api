<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\RegisterUserDataDTO;
use App\Models\User;

class AuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $validated
     */
    public function registerUser(array $validated): User
    {
        $data = RegisterUserDataDTO::fromArray($validated);

        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);
    }
}
