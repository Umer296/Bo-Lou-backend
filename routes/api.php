<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Api\ShopifyProductsController;

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
    Route::get('/shopify-products', [ShopifyProductsController::class, 'index']);

});
