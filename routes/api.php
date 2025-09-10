<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\Api\ShopifyProductsController;
use App\Http\Controllers\DashboardController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    Route::apiResource('orders', OrderController::class);
    Route::apiResource('shipments', ShipmentController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('order-item', OrderItemController::class);
    Route::get('/shopify-products', [ShopifyProductsController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'index']);

});
