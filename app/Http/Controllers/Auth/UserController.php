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
}