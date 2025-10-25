<?php

namespace Modules\TelegramBot\Listeners;

use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue; // <-- مهم: برای استفاده از صف
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Ticketing\Events\TicketReplied;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendTelegramReplyNotification
{
    /**
     * Escape text for Telegram's MarkdownV2 parse mode.
     */
    protected function escape(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        return str_replace($chars, array_map(fn($char) => '\\' . $char, $chars), $text);
    }

    /**
     * Handle the event.
     */
    public function handle(TicketReplied $event): void
    {
        Log::info('SendTelegramReplyNotification handler triggered.');

        $reply = $event->reply;
        // Eager load relationships for efficiency
        $ticket = $reply->load('ticket.user')->ticket;
        $ticketOwner = $ticket->user; // کاربری که تیکت را ایجاد کرده
        $replyingUser = $reply->load('user')->user; // کاربری که پاسخ را ثبت کرده

        // --- منطق جدید و قابل اعتماد ---
        // 1. آیا صاحب تیکت، چت آی‌دی تلگرام دارد؟
        // 2. آیا کاربری که پاسخ داده، همان صاحب تیکت است؟ (اگر بله، پیام نده)
        if (empty($ticketOwner->telegram_chat_id) || !$replyingUser || $replyingUser->id === $ticketOwner->id) {
            Log::warning('Telegram notification aborted.', [
                'ticket_id' => $ticket->id,
                'reason' => empty($ticketOwner->telegram_chat_id) ? 'User has no Telegram chat ID.' : 'Reply is from the ticket owner themselves.',
            ]);
            return; // اجرای کد متوقف می‌شود
        }

        try {
            Log::info("Attempting to send Telegram notification for ticket #{$ticket->id} to user {$ticketOwner->id}.");

            $settings = Setting::pluck('value', 'key');
            $botToken = $settings->get('telegram_bot_token');
            if (!$botToken) {
                Log::error('Telegram bot token not found in settings.');
                return;
            }
            Telegram::setAccessToken($botToken);

            // Update ticket status to 'answered'
            $ticket->update(['status' => 'answered']);

            // Create Inline Keyboard for user actions
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '✍️ پاسخ به تیکت', 'callback_data' => "reply_ticket_{$ticket->id}"]),
                Keyboard::inlineButton(['text' => '❌ بستن تیکت', 'callback_data' => "close_ticket_{$ticket->id}"]),
            ]);

            // Prepare the message using MarkdownV2
            $message = "📩 *پاسخ جدید به تیکت شما \\#{$ticket->id}*\n\n";
            $message .= "*موضوع:* " . $this->escape($ticket->subject) . "\n";
            $message .= "*پاسخ:* " . $this->escape($reply->message);

            $basePayload = [
                'chat_id'      => $ticketOwner->telegram_chat_id,
                'reply_markup' => $keyboard,
                'parse_mode'   => 'MarkdownV2',
            ];

            // Send with attachment if it exists
            if ($reply->attachment_path && Storage::disk('public')->exists($reply->attachment_path)) {
                $filePath = Storage::disk('public')->path($reply->attachment_path);
                $mimeType = Storage::disk('public')->mimeType($reply->attachment_path);
                Log::info('Sending reply with attachment.', ['path' => $filePath, 'mime' => $mimeType]);

                $filePayload = $basePayload + ['caption' => $message];

                if (str_starts_with($mimeType, 'image/')) {
                    $filePayload['photo'] = InputFile::create($filePath);
                    Telegram::sendPhoto($filePayload);
                } else {
                    $filePayload['document'] = InputFile::create($filePath);
                    Telegram::sendDocument($filePayload);
                }
            } else {
                // Send a text-only message
                Log::info('Sending text-only reply.');
                $textPayload = $basePayload + ['text' => $message];
                Telegram::sendMessage($textPayload);
            }

            Log::info("Successfully queued Telegram notification for ticket #{$ticket->id}.");

        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification for ticket reply: {$e->getMessage()}", [
                'ticket_id' => $ticket->id,
                'user_id'   => $ticketOwner->id,
                'trace'     => $e->getTraceAsString()
            ]);
        }
    }
}
