<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WebhookController as NowPaymentsWebhookController;
use Modules\TelegramBot\Http\Controllers\WebhookController as TelegramWebhookController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $settings = Setting::all()->pluck('value', 'key');
    $plans = Plan::where('is_active', true)->orderBy('price')->get();
    $activeTheme = $settings->get('active_theme', 'welcome');

    if (!view()->exists("themes.{$activeTheme}")) {
        abort(404, "قالب '{$activeTheme}' یافت نشد.");
    }

    return view("themes.{$activeTheme}", ['settings' => $settings, 'plans' => $plans]);
})->name('home');


Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        $user = Auth::user();
        if ($user->show_renewal_notification) {
            session()->flash('renewal_success', 'سرویس شما با موفقیت تمدید شد. لینک اشتراک شما تغییر کرده است، لطفاً لینک جدید را کپی و در نرم‌افزار خود آپدیت کنید.');
            $user->update(['show_renewal_notification' => false]);
        }
        $orders = $user->orders()->with('plan')->whereNotNull('plan_id')->whereNull('renews_order_id')->latest()->get();
        $transactions = $user->orders()->with('plan')->latest()->get();
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        $tickets = $user->tickets()->latest()->get();
        return view('dashboard', compact('orders', 'plans', 'tickets', 'transactions'));
    })->name('dashboard');

    // Wallet
    Route::get('/wallet/charge', [OrderController::class, 'showChargeForm'])->name('wallet.charge.form');
    Route::post('/wallet/charge', [OrderController::class, 'createChargeOrder'])->name('wallet.charge.create');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Order & Payment Process
    Route::post('/order/{plan}', [OrderController::class, 'store'])->name('order.store');
    Route::get('/order/{order}', [OrderController::class, 'show'])->name('order.show');
    Route::post('/order/{order}/renew', [OrderController::class, 'renew'])->name('order.renew');

    // Subscription Extension
    Route::get('/subscription/{order}/extend', [\App\Http\Controllers\SubscriptionExtensionController::class, 'show'])->name('subscription.extend.show');
    Route::post('/subscription/{order}/extend', [\App\Http\Controllers\SubscriptionExtensionController::class, 'store'])->name('subscription.extend');

    Route::post('/payment/card/{order}/submit', [OrderController::class, 'submitCardReceipt'])->name('payment.card.submit');
    Route::post('/payment/card/{order}', [OrderController::class, 'processCardPayment'])->name('payment.card.process');

    Route::post('/payment/crypto/{order}', [OrderController::class, 'processCryptoPayment'])->name('payment.crypto.process');
    Route::post('/payment/wallet/{order}', [OrderController::class, 'processWalletPayment'])->name('payment.wallet.process');

    // Coupon routes
    Route::post('/order/{order}/apply-coupon', [OrderController::class, 'applyCoupon'])->name('order.apply-coupon');
    Route::post('/order/{order}/remove-coupon', [OrderController::class, 'removeCoupon'])->name('order.remove-coupon');
});

Route::post('/webhooks/nowpayments', [NowPaymentsWebhookController::class, 'handle'])->name('webhooks.nowpayments');
Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle'])->name('webhooks.telegram');


/* BREEZE AUTHENTICATION */
require __DIR__.'/auth.php';

