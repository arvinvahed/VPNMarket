<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait RequiresChannelMembership
{
    protected function ensureUserIsMemberOfChannel($bot, $userId, $chatId): bool
    {
        $forceJoinEnabled = filter_var(setting('force_join_enabled'), FILTER_VALIDATE_BOOLEAN);
        $channelId = setting('telegram_required_channel_id');

        if (!$forceJoinEnabled || !$channelId) {
            return true;
        }

        $cacheKey = "telegram_membership_check:{$userId}:{$channelId}";

        return Cache::remember($cacheKey, 60, function () use ($bot, $userId, $channelId) {
            try {
                $response = Http::timeout(5)->get("https://api.telegram.org/bot{$bot->getToken()}/getChatMember", [
                    'chat_id' => $channelId,
                    'user_id' => $userId,
                ]);

                if (!$response->successful()) {
                    \Log::warning('خطا در بررسی عضویت کانال', [
                        'user_id' => $userId,
                        'channel_id' => $channelId,
                        'response' => $response->json()
                    ]);
                    return true;
                }

                $data = $response->json();
                $status = $data['result']['status'] ?? 'left';

                return in_array($status, ['creator', 'administrator', 'member', 'restricted']);

            } catch (\Exception $e) {
                \Log::error('خطا در بررسی عضویت کانال', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'channel_id' => $channelId
                ]);
                return true;
            }
        });
    }

    protected function sendMembershipWarning($bot, $chatId, $channelId): void
    {
        try {
            $channelInfo = Http::get("https://api.telegram.org/bot{$bot->getToken()}/getChat", [
                'chat_id' => $channelId
            ]);

            $channelData = $channelInfo->json()['result'] ?? [];
            $channelTitle = $channelData['title'] ?? 'کانال مورد نیاز';

            $joinUrl = $channelId[0] === '@'
                ? "https://t.me/" . ltrim($channelId, '@')
                : null;

        } catch (\Exception $e) {
            $channelTitle = 'کانال مورد نیاز';
            $joinUrl = null;
        }

        $keyboard = $joinUrl
            ? [[['text' => '✅ عضویت در کانال', 'url' => $joinUrl]], [['text' => '🔄 بررسی عضویت', 'callback_data' => 'check_membership']]]
            : [[['text' => '🔄 بررسی عضویت', 'callback_data' => 'check_membership']]];

        $message = "⚠️ **دسترسی محدود شد!**\n\n";
        $message .= "برای استفاده از ربات، ابتدا باید در کانال زیر عضو شوید:\n\n";
        $message .= "📢 {$channelTitle}\n\n";
        $message .= "لطفاً پس از عضویت، روی دکمه «بررسی عضویت» کلیک کنید.";

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }
}
