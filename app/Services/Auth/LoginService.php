<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginService
{
    /**
     * Authenticate a user and issue a Sanctum token.
     *
     * @param  array{email: string, password: string}  $data
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $this->fail();
        }

        if ($user->role !== UserRole::BUSINESS_CUSTOMER) {
            $this->fail();
        }

        return [
            'user'  => $user,
            'token' => $user->createToken('auth_token')->plainTextToken,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function fail(): never
    {
        throw ValidationException::withMessages([
            'email' => [__('auth.failed')],
        ]);
    }
}