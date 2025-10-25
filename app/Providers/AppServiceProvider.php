<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;


use Modules\Ticketing\Providers\EventServiceProvider as TicketingEventServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // رجیستر EventServiceProvider ماژول Ticketing
        $this->app->register(TicketingEventServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::creating(function ($user) {
            do {
                $code = 'REF-' . strtoupper(\Illuminate\Support\Str::random(6));
            } while (User::where('referral_code', $code)->exists());

            $user->referral_code = $code;
        });

        // ==========================================================
    }
}
