<?php

namespace App\Services\Auth;

use App\Models\User;

class UserService
{
    /**
     * Return the authenticated user with the relations the client needs.
     */
    public function getAuthenticatedUser(User $user): User
    {
        return $user->loadMissing('business');
    }
}