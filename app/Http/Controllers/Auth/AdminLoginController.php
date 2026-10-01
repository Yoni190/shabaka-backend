<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\Auth\AdminLoginService;
use Illuminate\Http\JsonResponse;

class AdminLoginController extends Controller
{
    public function __construct(
        private readonly AdminLoginService $adminLoginService,
    ) {}

    /**
     * Log in as an admin
     *
     * Authenticates an admin user and returns an authentication token.
     * Only users with the admin role are allowed through.
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->adminLoginService->login($request->validated());

        return response()->json([
            'user'  => $result['user'],
            'token' => $result['token'],
        ]);
    }
}