<?php

namespace App\Providers;

use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

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

        // Configure Telegram Bot Proxy if set in .env
        try {
            $proxy = env('TELEGRAM_PROXY');
            if ($proxy) {
                $guzzle = new GuzzleClient([
                    'proxy' => $proxy,
                    'verify' => false, // Disable SSL verification for local dev with proxy
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ]);
                
                $httpClient = new GuzzleHttpClient($guzzle);
                
                // Inject custom HTTP client into Telegram config
                config(['telegram.http_client_handler' => $httpClient]);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to configure Telegram Proxy: " . $e->getMessage());
        }

        // ==========================================================
    }
}
