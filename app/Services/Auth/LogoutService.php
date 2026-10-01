<?php

namespace App\Services\Auth;

use App\Models\User;

class LogoutService
{
    /**
     * Revoke the token used for the current request.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        // Personal access tokens can be deleted; transient tokens (e.g. via
        // Sanctum::actingAs in tests) cannot — guard against that.
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
    }

    /**
     * Revoke every token for the user (all devices).
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }
}