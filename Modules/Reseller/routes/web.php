<?php

use Illuminate\Support\Facades\Route;
use Modules\Reseller\Http\Controllers\ResellerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('resellers', ResellerController::class)->names('reseller');
});
