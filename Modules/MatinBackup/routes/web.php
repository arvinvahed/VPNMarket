<?php

use Illuminate\Support\Facades\Route;
use Modules\MatinBackup\Http\Controllers\MatinBackupController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('matinbackups', MatinBackupController::class)->names('matinbackup');
});
