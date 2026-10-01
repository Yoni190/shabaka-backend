<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LogoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __construct(
        private readonly LogoutService $logoutService,
    ) {}

    /**
     * Log out a user
     *
     * Revokes the current authentication token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->logoutService->logout($request->user());

        return response()->json(['message' => 'Logged out']);
    }
}