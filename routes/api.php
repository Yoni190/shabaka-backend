<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\AdminLoginController;

Route::prefix('v1')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [UserController::class, 'show']);
        Route::post('logout', [LogoutController::class, 'logout']);
    });

    // Admin endpoints
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminLoginController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user', [UserController::class, 'showAdmin']);
            Route::post('logout', [LogoutController::class, 'logout']);
        });
    });
});