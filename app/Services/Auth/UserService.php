<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\AuthorizationException;

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