<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Business;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\RegisterService;
use App\Http\Requests\RegisterBusinessRequest;


class RegisterController extends Controller
{
    public function __construct(
        private RegisterService $registerService
    ) {
    }

    public function register(RegisterBusinessRequest $request)
    {
        $this->registerService->register($request->validated());

        return response()->json([
            'message' => 'Business account created successfully',
        ], 201);
    }
}
