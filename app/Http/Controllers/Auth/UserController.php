<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * Get the authenticated user
     *
     * Returns the currently authenticated user's profile.
     */
    public function show(Request $request): User
    {
        return $this->userService->getAuthenticatedUser($request->user());
    }

    /**
     * Get the authenticated admin
     *
     * Returns the currently authenticated admin user's profile.
     * Only users with the admin role may access this endpoint.
     */
    public function showAdmin(Request $request): User
    {
        return $this->userService->getAuthenticatedAdmin($request->user());
    }
}