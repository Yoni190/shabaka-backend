<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminLoginService
{
    /**
     * Authenticate an admin user and issue a Sanctum token.
     *
     * Rejects the login if the credentials are invalid or the user does not
     * hold the admin role. The same generic message is used for both cases to
     * avoid leaking which emails belong to real (non-admin) accounts.
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

        if ($user->role !== UserRole::ADMIN) {
            $this->fail();
        }

        return [
            'user'  => $user,
            'token' => $user->createToken('admin_auth_token')->plainTextToken,
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