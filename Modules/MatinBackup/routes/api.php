<?php

use Illuminate\Support\Facades\Route;
use Modules\MatinBackup\Http\Controllers\MatinBackupController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('matinbackups', MatinBackupController::class)->names('matinbackup');
});
