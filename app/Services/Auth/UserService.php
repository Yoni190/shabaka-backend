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

    /**
     * Return the authenticated admin user.
     *
     * Admins have no business, so no relation is eager-loaded here.
     *
     * @throws AuthorizationException
     */
    public function getAuthenticatedAdmin(User $user): User
    {
        if ($user->role !== UserRole::ADMIN) {
            throw new AuthorizationException('This account does not have admin access.');
        }

        return $user;
    }
}