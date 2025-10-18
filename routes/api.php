<?php

use App\Http\Controllers\PanelsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Admin-only routes for panel management
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::apiResource('panels', PanelsController::class);
    Route::post('panels/{panel}/test-connection', [PanelsController::class, 'testConnection']);
});
