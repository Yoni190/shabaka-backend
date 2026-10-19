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
use App\Http\Controllers\Catalog\BrandController as CatalogBrandController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Client\AddressController;
use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\OrderController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;

Route::prefix('v1')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);

    Route::post('webhooks/moyasar', MoyasarWebhookController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [UserController::class, 'show']);
        Route::post('logout', [LogoutController::class, 'logout']);

        Route::apiResource('addresses', AddressController::class)
            ->whereNumber('address');

        // Cart
        Route::prefix('cart')->group(function () {
            Route::get('', [CartController::class, 'show']);
            Route::delete('items', [CartController::class, 'clearItems']);
            Route::post('items', [CartController::class, 'storeItem']);
            Route::patch('items/{item}', [CartController::class, 'updateItem'])->whereNumber('item');
            Route::delete('items/{item}', [CartController::class, 'destroyItem'])->whereNumber('item');
        });

        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');

        Route::post('payments/initiate', [PaymentController::class, 'initiate']);
        Route::post('payments/verify', [PaymentController::class, 'verify']);   
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

            // Orders
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order');
        });
    });

    Route::prefix('home')->group(function () {
        Route::get('categories', [HomeController::class, 'categories']);
        Route::get('products', [HomeController::class, 'products']); 
    });

    Route::get('categories', [CatalogCategoryController::class, 'index']);
    Route::get('brands', [CatalogBrandController::class, 'index']);
    Route::get('products', [CatalogProductController::class, 'index']);
    Route::get('products/{product}', [CatalogProductController::class, 'show']);
});