<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (!function_exists('escapeTelegramHTML')) {
    /**
     * Escapes text for Telegram's HTML parse mode.
     */
    function escapeTelegramHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}


if (!function_exists('setting')) {
    /**
     * Get a setting value from the database.
     * Uses cache for better performance.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    function setting($key, $default = null)
    {

        $settings = Cache::rememberForever('settings', function () {
            return Setting::all()->pluck('value', 'key');
        });


        return $settings->get($key, $default);
    }
}
