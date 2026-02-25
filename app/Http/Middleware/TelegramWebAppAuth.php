<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebAppAuth
{
    public function handle(Request $request, Closure $next)
    {
        // لاگ برای دیباگ
        Log::info('TelegramWebAppAuth middleware', [
            'has_init_data' => $request->has('_telegram_init_data'),
            'has_user_id' => $request->has('user_id'),
            'header_init_data' => $request->header('X-Telegram-Init-Data') ? 'present' : 'missing',
            'auth_check' => Auth::check()
        ]);

        // اگر کاربر قبلاً لاگین کرده، اجازه عبور بده
        if (Auth::check()) {
            return $next($request);
        }

        // روش ۱: initData از تلگرام (امن‌تر)
        $initData = $request->header('X-Telegram-Init-Data')
            ?? $request->input('_telegram_init_data');

        if ($initData && $this->validateInitData($initData)) {
            $user = $this->authenticateWithInitData($initData);
            if ($user) {
                Auth::login($user);
                return $next($request);
            }
        }

        // روش ۲: user_id از query/form (برای تست و فال‌بک)
        $telegramUserId = $request->input('user_id');

        if ($telegramUserId) {
            $user = User::where('telegram_chat_id', $telegramUserId)->first();

            if ($user) {
                Log::info('Authenticated via user_id', ['user_id' => $user->id]);
                Auth::login($user);
                return $next($request);
            }
        }

        // اگر هیچی نیست
        Log::warning('WebApp auth failed', [
            'ip' => $request->ip(),
            'url' => $request->fullUrl()
        ]);

        // برای API response JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'لطفاً از طریق ربات تلگرام وارد شوید',
                'error' => 'unauthorized'
            ], 401);
        }

        // برای web redirect
        $botUsername = config('services.telegram.bot_username', env('TELEGRAM_BOT_USERNAME', ''));
        if ($botUsername) {
            return redirect()->away("https://t.me/{$botUsername}");
        }

        return redirect()->away('https://t.me/');
    }

    /**
     * ولیدیشن initData تلگرام (اختیاری ولی توصیه شده)
     */
    protected function validateInitData(string $initData): bool
    {
        // در نسخه production باید HMAC رو چک کنی
        // فعلاً true برمی‌گردونیم برای تست
        return true;
    }

    /**
     * احراز هویت با initData
     */
    protected function authenticateWithInitData(string $initData): ?User
    {
        try {
            parse_str($initData, $data);

            if (empty($data['user'])) {
                Log::warning('No user in initData');
                return null;
            }

            $userData = json_decode($data['user'], true);
            if (!$userData || !isset($userData['id'])) {
                Log::warning('Invalid user data in initData');
                return null;
            }

            $telegramId = $userData['id'];

            $user = User::firstOrCreate(
                ['telegram_chat_id' => $telegramId],
                [
                    'name' => ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''),
                    'email' => $telegramId . '@telegram.user',
                    'password' => Hash::make(Str::random(16)),
                    'referral_code' => Str::random(8),
                ]
            );

            Log::info('User authenticated', ['user_id' => $user->id, 'telegram_id' => $telegramId]);
            return $user;

        } catch (\Exception $e) {
            Log::error('WebApp auth error: ' . $e->getMessage());
            return null;
        }
    }
}
