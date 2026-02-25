<?php

use Illuminate\Support\Facades\Route;
use Modules\Reseller\Http\Controllers\ResellerApiController;
use Modules\Reseller\Http\Controllers\TestVpnController;
use Modules\Reseller\Http\Controllers\TelegramResellerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Reseller Mini App API Routes
Route::middleware(['auth:sanctum'])->prefix('v1/reseller')->group(function () {
    Route::get('profile', [ResellerApiController::class, 'profile']);
    Route::get('servers', [ResellerApiController::class, 'servers']);
    Route::get('accounts', [ResellerApiController::class, 'accounts']);
    Route::post('accounts', [ResellerApiController::class, 'createAccount']);
    Route::get('accounts/{id}', [ResellerApiController::class, 'getAccount']);
});

// Telegram Bot API Routes
Route::prefix('v1/telegram')->group(function () {
    Route::get('plans', [TelegramResellerController::class, 'getAvailablePlans']);
    Route::post('reseller/apply', [TelegramResellerController::class, 'submitApplication']);
    Route::get('reseller/status/{user_id}', [TelegramResellerController::class, 'getApplicationStatus']);
});

// Test routes for VPN functionality
Route::middleware(['auth:sanctum'])->prefix('v1/test')->group(function () {
    Route::post('vpn/create', [TestVpnController::class, 'testMarzbanAccount']);
    Route::delete('vpn/delete/{id}', [TestVpnController::class, 'testDeleteAccount']);
});