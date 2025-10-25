<?php

return [
    'name' => 'TelegramBot',
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
];
