<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\LoginUserDataDTO;
use App\Domain\Auth\DTOs\RegisterUserDataDTO;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

    /**
     * @param  array{email: string, password: string}  $validated
     */
    public function loginUser(array $validated): ?User
    {
        $data = LoginUserDataDTO::fromArray($validated);

        if (! Auth::attempt([
            'email' => $data->email,
            'password' => $data->password,
        ])) {
            return null;
        }

        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
