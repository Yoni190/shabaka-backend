<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{

    public function __construct(
        private readonly LoginService $loginService,
    ) {}

    /**
     * Log in a user
     *
     * Authenticates the user and returns an authentication token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->loginService->login($request->validated());

        return response()->json([
            'user'  => $result['user'],
            'token' => $result['token'],
        ]);
    }
}

