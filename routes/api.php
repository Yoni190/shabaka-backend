<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Catalog\CategoryController as CatalogCategoryController;
use App\Http\Controllers\Catalog\ProductController as CatalogProductController;
use App\Http\Controllers\Catalog\HomeController;

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

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::get('user', [UserController::class, 'showAdmin']);
            Route::post('logout', [LogoutController::class, 'logout']);
            Route::get('dashboard', [DashboardController::class, 'index']);
            
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('brands', BrandController::class);
            Route::apiResource('products', ProductController::class);
        });
    });

    Route::prefix('home')->group(function () {
        Route::get('categories', [HomeController::class, 'categories']);
        Route::get('products', [HomeController::class, 'products']); 
    });

    Route::get('categories', [CatalogCategoryController::class, 'index']);
    Route::get('products', [CatalogProductController::class, 'index']);
});