<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\BlogController;

Route::middleware('web')->prefix('blog')->group(function () {

    // صفحه اصلی بلاگ
    Route::get('/', [BlogController::class, 'index'])->name('blog.index');

    // صفحه داخلی پست (باید آخر باشه)
    Route::get('/{slug}', [BlogController::class, 'show'])->name('blog.show');

});
