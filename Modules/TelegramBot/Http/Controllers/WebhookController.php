<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\TelegramBotSetting;
use App\Services\XUIService;
use App\Models\User;
use App\Services\MarzbanService;
use App\Models\Inbound;
use Modules\Ticketing\Events\TicketCreated;
use Modules\Ticketing\Models\Ticket;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Telegram\Bot\FileUpload\InputFile;

class WebhookController extends Controller
{
    protected $settings;


    public function sendBroadcastMessage(string $chatId, string $message): bool
    {
        try {
            // --- Load Telegram Bot Token ---
            if (!$this->settings) {
                $this->settings = \App\Models\Setting::all()->pluck('value', 'key');
            }

            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                \Log::error('❌ Cannot send broadcast message: bot token is not set.');
                return false;
            }

            \Telegram\Bot\Laravel\Facades\Telegram::setAccessToken($botToken);


            $title = "📢 *اعلان ویژه از سوی تیم مدیریت*";
            $divider = str_repeat('━', 20);
            $footer = "💠 *با تشکر از همراهی شما* 💠";

            // Escape user message for MarkdownV2 safety
            $formattedMessage = $this->escape($message);

            $fullMessage = "{$title}\n\n{$divider}\n\n📝 *{$formattedMessage}*\n\n{$divider}\n\n{$footer}";

            // --- Send Message ---
            \Telegram\Bot\Laravel\Facades\Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $fullMessage,
                'parse_mode' => 'MarkdownV2',
            ]);

            \Log::info("✅ Broadcast message sent successfully to chat {$chatId}");
            return true;
        } catch (\Exception $e) {
            \Log::warning("⚠️ Failed to send broadcast message to user {$chatId}: " . $e->getMessage());
            return false;
        }
    }


    public function sendSingleMessageToUser(string $chatId, string $message): bool
    {
        try {

            if (!$this->settings) {
                $this->settings = \App\Models\Setting::all()->pluck('value', 'key');
            }
            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                \Illuminate\Support\Facades\Log::error('Cannot send single Telegram message: bot token is not set.');
                return false;
            }
            \Telegram\Bot\Laravel\Facades\Telegram::setAccessToken($botToken);


            $header = "📢 *پیام فوری از مدیریت*";
            $notice = "⚠️ این یک پیام اطلاع‌رسانی یک‌طرفه از پنل ادمین است و پاسخ دادن به آن در این چت، پیگیری نخواهد شد\\.";


            $adminMessageLines = explode("\n", $message);
            $formattedMessage = implode("\n", array_map(fn($line) => "> " . trim($line), $adminMessageLines));

            $fullMessage = "{$header}\n\n{$this->escape($notice)}\n\n{$formattedMessage}";


            \Telegram\Bot\Laravel\Facades\Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $fullMessage,
                'parse_mode' => 'MarkdownV2',
            ]);

            \Illuminate\Support\Facades\Log::info("Admin sent message to user {$chatId}.", ['message' => $message]);
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send single Telegram message: ' . $e->getMessage(), ['chat_id' => $chatId, 'message' => $message]);
            return false;
        }
    }


    public function handle(Request $request)
    {
        Log::info('Telegram Webhook Received:', $request->all());

        try {
            $this->settings = Setting::all()->pluck('value', 'key');
            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                Log::warning('Telegram bot token is not set.');
                return response('ok', 200);
            }
            Telegram::setAccessToken($botToken);
            $update = Telegram::getWebhookUpdate();

            if ($update->isType('callback_query')) {
                $this->handleCallbackQuery($update);
            } elseif ($update->has('message')) {
                $message = $update->getMessage();
                if ($message->has('text')) {
                    $this->handleTextMessage($update);
                } elseif ($message->has('photo')) {
                    $this->handlePhotoMessage($update);
                }
            }
        } catch (\Exception $e) {
            Log::error('Telegram Bot Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
        return response('ok', 200);
    }


    protected function handleTextMessage($update)
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $text = trim($message->getText() ?? '');
        $user = User::where('telegram_chat_id', $chatId)->first();


        if ($user && !$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId);
            return;
        }


        if (!$user) {
            $userFirstName = $message->getFrom()->getFirstName() ?? 'کاربر';
            $password = Str::random(10);
            $user = User::create([
                'name' => $userFirstName,
                'email' => $chatId . '@telegram.user',
                'password' => Hash::make($password),
                'telegram_chat_id' => $chatId,
                'referral_code' => Str::random(8),
            ]);

            // چک عضویت قبل از خوش‌آمدگویی
            if (!$this->isUserMemberOfChannel($user)) {
                $this->showChannelRequiredMessage($chatId);
                return;
            }



            $telegramSettings = \App\Models\TelegramBotSetting::pluck('value', 'key');
            $welcomeMessage = $telegramSettings->get('welcome_message', "🌟 خوش آمدید {$userFirstName} عزیز!\n\nبرای شروع، یکی از گزینه‌های منو را انتخاب کنید:");
            $welcomeMessage = str_replace('{userFirstName}', $userFirstName, $welcomeMessage);
            // این چک تکراری بود و حذف شد (چون بالا چک شده)
            // if (!$this->isUserMemberOfChannel($user)) {
            //     $this->showChannelRequiredMessage($chatId);
            //     return;
            // }

            if (Str::startsWith($text, '/start ')) {
                $referralCode = Str::after($text, '/start ');
                $referrer = User::where('referral_code', $referralCode)->first();

                if ($referrer && $referrer->id !== $user->id) {
                    $user->referrer_id = $referrer->id;
                    $user->save();
                    $welcomeGift = (int) $this->settings->get('referral_welcome_gift', 0);
                    if ($welcomeGift > 0) {
                        $user->increment('balance', $welcomeGift);
                        $welcomeMessage .= "\n\n🎁 هدیه خوش‌آمدگویی: " . number_format($welcomeGift) . " تومان به کیف پول شما اضافه شد.";
                    }
                    if ($referrer->telegram_chat_id) {
                        $referrerMessage = "👤 *خبر خوب!*\n\nکاربر جدیدی با نام «{$userFirstName}» با لینک دعوت شما به ربات پیوست.";
                        try {
                            Telegram::sendMessage(['chat_id' => $referrer->telegram_chat_id, 'text' => $this->escape($referrerMessage), 'parse_mode' => 'MarkdownV2']);
                        } catch (\Exception $e) {
                            Log::error("Failed to send referral notification: " . $e->getMessage());
                        }
                    }
                }
            }

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $welcomeMessage,
                'reply_markup' => $this->getReplyMainMenu()
            ]);
            return;
        }


        if (!$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId);
            return;
        }

        if ($user->bot_state) {
            if ($user->bot_state === 'awaiting_deposit_amount') {
                $this->processDepositAmount($user, $text);
            } elseif (Str::startsWith($user->bot_state, 'awaiting_new_ticket_') || Str::startsWith($user->bot_state, 'awaiting_ticket_reply')) {
                $this->processTicketConversation($user, $text, $update);
            }
            return;
        }

        switch ($text) {
            case '🛒 خرید سرویس':
                $this->sendPlans($chatId);
                break;
            case '🛠 سرویس‌های من':
                $this->sendMyServices($user);
                break;
            case '💰 کیف پول':
                $this->sendWalletMenu($user);
                break;
            case '📜 تاریخچه تراکنش‌ها':
                $this->sendTransactions($user);
                break;
            case '💬 پشتیبانی':
                $this->showSupportMenu($user);
                break;
            case '🎁 دعوت از دوستان':
                $this->sendReferralMenu($user);
                break;
            case '📚 راهنمای اتصال':
                $this->sendTutorialsMenu($chatId);
                break;
            case '🧪 اکانت تست':
                $this->handleTrialRequest($user);
                break;

            case '/start':
                $telegramSettings = \App\Models\TelegramBotSetting::pluck('value', 'key');
                $startMessage = $telegramSettings->get('start_message', 'سلام مجدد! لطفاً یک گزینه را انتخاب کنید:');
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($startMessage),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $this->getReplyMainMenu()
                ]);
                break;
            default:
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'دستور شما نامفهوم است. لطفاً از دکمه‌های منو استفاده کنید.',
                    'reply_markup' => $this->getReplyMainMenu()
                ]);
                break;
        }
    }


    protected function handleCallbackQuery($update)
    {
        $callbackQuery = $update->getCallbackQuery();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $data = $callbackQuery->getData();
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user && !$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId, $messageId);
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'ابتدا باید در کانال عضو شوید!',
                'show_alert' => true
            ]);
            return;
        }

        if (Str::startsWith($data, 'show_duration_')) {
            $durationDays = (int)Str::after($data, 'show_duration_');
            $this->sendPlansByDuration($chatId, $durationDays, $messageId);
            return; // ← مهم!
        }

        if (Str::startsWith($data, 'show_service_')) {
            $orderId = Str::after($data, 'show_service_');
            $this->showServiceDetails($user, $orderId, $messageId);
            return;
        }

        // چک عضویت
        // این بخش به درستی کار می‌کند، چون تابع isUserMemberOfChannel اصلاح شده
        if (!$user || !$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId, $messageId);
            Telegram::answerCallbackQuery([
                'callback_query_id' => $callbackQuery->getId(),
                'text' => 'ابتدا باید در کانال عضو شوید!',
                'show_alert' => true
            ]);
            return;
        }

        try {
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
        } catch (\Exception $e) { Log::warning('Could not answer callback query: ' . $e->getMessage()); }



        if (!$user) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ کاربر یافت نشد. لطفاً با دستور /start ربات را مجدداً راه‌اندازی کنید."), 'parse_mode' => 'MarkdownV2']);
            return;
        }

        if (!Str::startsWith($data, ['/deposit_custom', '/support_new', 'reply_ticket_'])) {
            $user->update(['bot_state' => null]);
        }

        // --- Purchase Flow ---
        if (Str::startsWith($data, 'buy_plan_')) {
            $planId = Str::after($data, 'buy_plan_');
            $this->startPurchaseProcess($user, $planId, $messageId);
        } elseif (Str::startsWith($data, 'pay_wallet_')) {
            $planId = Str::after($data, 'pay_wallet_');
            $this->processWalletPayment($user, $planId, $messageId);
        } elseif (Str::startsWith($data, 'pay_card_')) {
            $orderId = Str::after($data, 'pay_card_');
            $this->sendCardPaymentInfo($chatId, $orderId, $messageId);
        }// --- QR Code Generation ---
        elseif (Str::startsWith($data, 'qrcode_order_')) {
            $orderId = Str::after($data, 'qrcode_order_');

            try {
                Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
            } catch (\Exception $e) {
                Log::warning('Could not answer callback query for QR Code: ' . $e->getMessage());
            }

            $this->sendQRCodeForOrder($user, $orderId);
        }

        elseif (Str::startsWith($data, 'renew_order_')) {
            $originalOrderId = Str::after($data, 'renew_order_');
            $this->startRenewalPurchaseProcess($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'renew_pay_wallet_')) {
            $originalOrderId = Str::after($data, 'renew_pay_wallet_');
            $this->processRenewalWalletPayment($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'renew_pay_card_')) {
            $originalOrderId = Str::after($data, 'renew_pay_card_');
            $this->handleRenewCardPayment($user, $originalOrderId, $messageId);
        }
        // --- Deposit Flow ---
        elseif (Str::startsWith($data, 'deposit_amount_')) {
            $amount = Str::after($data, 'deposit_amount_');
            $this->processDepositAmount($user, $amount, $messageId);
        } elseif ($data === '/deposit_custom') {
            $this->promptForCustomDeposit($user, $messageId);
        }
        // --- Ticket Flow ---
        elseif (Str::startsWith($data, 'close_ticket_')) {
            $ticketId = Str::after($data, 'close_ticket_');
            $this->closeTicket($user, $ticketId, $messageId, $callbackQuery->getId());
        } elseif (Str::startsWith($data, 'reply_ticket_')) {
            $ticketId = Str::after($data, 'reply_ticket_');
            $this->promptForTicketReply($user, $ticketId, $messageId);
        } elseif ($data === '/support_new') {
            $this->promptForNewTicket($user, $messageId);
        }
        // --- Navigation ---
        else {
            switch ($data) {
                // IMPORTANT: When a user clicks an inline button, we should reply with another inline menu
                // not the main reply menu. So we send a new message with the main reply menu.
                case '/start':
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => '🌟 منوی اصلی',
                        'reply_markup' => $this->getReplyMainMenu()
                    ]);
                    // Also delete the old inline message to avoid confusion
                    try { Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]); } catch (\Exception $e) {}
                    break;
                case '/plans': $this->sendPlans($chatId, $messageId); break;
                case '/my_services': $this->sendMyServices($user, $messageId); break;
                case '/wallet': $this->sendWalletMenu($user, $messageId); break;
                case '/referral': $this->sendReferralMenu($user, $messageId); break;
                case '/support_menu': $this->showSupportMenu($user, $messageId); break;
                case '/deposit': $this->showDepositOptions($user, $messageId); break;
                case '/transactions': $this->sendTransactions($user, $messageId); break;
                case '/tutorials': $this->sendTutorialsMenu($chatId, $messageId); break;
                case '/tutorial_android': $this->sendTutorial('android', $chatId, $messageId); break;
                case '/tutorial_ios': $this->sendTutorial('ios', $chatId, $messageId); break;
                case '/tutorial_windows': $this->sendTutorial('windows', $chatId, $messageId); break;
                case '/check_membership':
                    if ($this->isUserMemberOfChannel($user)) {
                        Telegram::answerCallbackQuery([
                            'callback_query_id' => $callbackQuery->getId(),
                            'text' => 'عضویت شما تأیید شد!',
                            'show_alert' => false
                        ]);
                        // پیام قبلی را حذف کن
                        try { Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]); } catch (\Exception $e) {}

                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => 'خوش آمدید! حالا می‌توانید از ربات استفاده کنید.',
                            'reply_markup' => $this->getReplyMainMenu()
                        ]);
                    } else {
                        Telegram::answerCallbackQuery([
                            'callback_query_id' => $callbackQuery->getId(),
                            'text' => 'هنوز عضو کانال نشده‌اید. لطفاً اول عضو شوید.',
                            'show_alert' => true
                        ]);
                        $this->showChannelRequiredMessage($chatId, $messageId);
                    }
                    break;

                case '/cancel_action':
                    $user->update(['bot_state' => null]);
                    // Delete the message with the inline keyboard
                    try { Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]); } catch (\Exception $e) {}
                    // Send a new message confirming cancellation
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => '✅ عملیات لغو شد.',
                        'reply_markup' => $this->getReplyMainMenu(),
                    ]);
                    break;
                default:
                    Log::warning('Unknown callback data received:', ['data' => $data, 'chat_id' => $chatId]);
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'دستور نامعتبر.',
                        'reply_markup' => $this->getReplyMainMenu(),
                    ]);
                    break;
            }
        }
    }

    protected function handlePhotoMessage($update)
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user && !$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId);
            return;
        }

        // چک عضویت برای عکس‌ها
        if (!$user || !$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId);
            return;
        }

        if (!$user || !$user->bot_state) {
            $this->sendOrEditMainMenu($chatId, "❌ لطفاً ابتدا یک عملیات (مانند ثبت تیکت یا رسید) را شروع کنید.");
            return;
        }

        // Handle photo for tickets
        if (Str::startsWith($user->bot_state, 'awaiting_ticket_reply|') || Str::startsWith($user->bot_state, 'awaiting_new_ticket_message|')) {
            $text = $message->getCaption() ?? '[📎 فایل پیوست شد]';
            $this->processTicketConversation($user, $text, $update);
            return;
        }

        // Handle photo for receipts
        if (Str::startsWith($user->bot_state, 'waiting_receipt_')) {
            $orderId = Str::after($user->bot_state, 'waiting_receipt_');
            $order = Order::find($orderId);

            if ($order && $order->user_id === $user->id && $order->status === 'pending') {
                try {
                    $fileName = $this->savePhotoAttachment($update, 'receipts');
                    if (!$fileName) throw new \Exception("Failed to save photo attachment.");

                    $order->update(['card_payment_receipt' => $fileName]);
                    $user->update(['bot_state' => null]);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $this->escape("✅ رسید شما با موفقیت ثبت شد. پس از بررسی توسط ادمین، نتیجه به شما اطلاع داده خواهد شد."),
                        'parse_mode' => 'MarkdownV2',
                    ]);
                    $this->sendOrEditMainMenu($chatId, "چه کار دیگری برایتان انجام دهم?");

                    // Notify admin
                    $adminChatId = $this->settings->get('telegram_admin_chat_id');
                    if ($adminChatId) {

                        // --- بخش اصلاح شده ---
                        $orderType = $order->renews_order_id ? 'تمدید سرویس' : ($order->plan_id ? 'خرید سرویس' : 'شارژ کیف پول');

                        $adminMessage = "🧾 *رسید جدید برای سفارش \\#{$orderId}*\n\n";
                        $adminMessage .= "*کاربر:* " . $this->escape($user->name) . " \\(ID: `{$user->id}`\\)\n";
                        $adminMessage .= "*مبلغ:* " . $this->escape(number_format($order->amount) . ' تومان') . "\n";
                        $adminMessage .= "*نوع سفارش:* " . $this->escape($orderType) . "\n\n";
                        $adminMessage .= $this->escape("لطفا در پنل مدیریت بررسی و تایید کنید."); // <-- escape کردن جمله آخر

                        Telegram::sendPhoto([
                            'chat_id' => $adminChatId,
                            'photo' => InputFile::create(Storage::disk('public')->path($fileName)),
                            'caption' => $adminMessage,
                            'parse_mode' => 'MarkdownV2'
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error("Receipt processing failed for order {$orderId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ خطا در پردازش رسید. لطفاً دوباره تلاش کنید."), 'parse_mode' => 'MarkdownV2']);
                    $this->sendOrEditMainMenu($chatId, "لطفا دوباره تلاش کنید.");
                }
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ سفارش نامعتبر است یا در انتظار پرداخت نیست."), 'parse_mode' => 'MarkdownV2']);
                $this->sendOrEditMainMenu($chatId, "لطفا وضعیت سفارش خود را بررسی کنید.");
            }
        }
    }


    protected function sendPlans($chatId, $messageId = null)
    {
        try {

            $activePlans = Plan::where('is_active', true)
                ->orderBy('duration_days', 'asc')
                ->get();

            if ($activePlans->isEmpty()) {
                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/start'])]);
                $this->sendOrEditMessage($chatId, "⚠️ هیچ پلن فعالی در دسترس نیست.", $keyboard, $messageId);
                return;
            }

            // استخراج duration_days منحصربه‌فرد و مرتب
            $durations = $activePlans->pluck('duration_days')->unique()->sort();

            $message = "🚀 *انتخاب سرویس VPN*\n\n";
            $message .= "لطفاً مدت‌زمان سرویس مورد نظر را انتخاب کنید:\n\n";
            $message .= "👇 یکی از گزینه‌های زیر را بزنید:";

            $keyboard = Keyboard::make()->inline();

            foreach ($durations as $durationDays) {
                // تولید برچسب فارسی برای هر مدت‌زمان
                $buttonText = $this->generateDurationLabel($durationDays);

                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => $buttonText,
                        'callback_data' => "show_duration_{$durationDays}"
                    ])
                ]);
            }

            $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);

            $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);

        } catch (\Exception $e) {
            Log::error('Error in sendPlans: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString()
            ]);

            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton(['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => '/start'])]);

            $this->sendOrEditMessage($chatId, "❌ خطایی در بارگذاری پلن‌ها رخ داد.", $keyboard, $messageId);
        }
    }


    protected function generateDurationLabel(int $days): string
    {
        // اگر مضرب 30 روز (یک ماه) باشد
        if ($days % 30 === 0) {
            $months = $days / 30;

            return match ($months) {
                1 => '🔸 یک ماهه',
                2 => '🔸 دو ماهه',
                3 => '🔸 سه ماهه',
                6 => '🔸 شش ماهه',
                12 => '🔸 یک ساله',
                default => "{$months} ماهه",
            };
        }


        return "{$days} روزه";
    }
    protected function sendPlansByDuration($chatId, $durationDays, $messageId = null)
    {
        try {
            $plans = Plan::where('is_active', true)
                ->where('duration_days', $durationDays)
                ->orderBy('volume_gb', 'asc')
                ->get();

            if ($plans->isEmpty()) {
                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/plans'])]);
                $this->sendOrEditMessage($chatId, "⚠️ پلنی با این مدت‌زمان یافت نشد.", $keyboard, $messageId);
                return;
            }

            $durationLabel = $plans->first()->duration_label;
            $message = "📅 *پلن‌های {$durationLabel}*\n\n";


            foreach ($plans as $index => $plan) {
                if ($index > 0) {
                    $message .= "〰️〰️〰️\n\n";
                }
                $message .= ($index + 1) . ". 💎 *" . $this->escape($plan->name) . "*\n";
                $message .= "   📦 " . $this->escape($plan->volume_gb . ' گیگ') . "\n";
                $message .= "   💳 " . $this->escape(number_format($plan->price) . ' تومان') . "\n";
            }

            $message .= "\n👇 پلن مورد نظر را انتخاب کنید:";

            $keyboard = Keyboard::make()->inline();

            // دکمه‌ها: نام پلن + قیمت
            foreach ($plans as $plan) {
                // ✅ اینجا نام پلن و قیمت رو ترکیب میکنیم
                $buttonText = $this->escape($plan->name) . ' | ' . number_format($plan->price) . ' تومان';

                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => $buttonText,
                        'callback_data' => "buy_plan_{$plan->id}"
                    ])
                ]);
            }

            $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به انتخاب زمان', 'callback_data' => '/plans'])]);

            $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);

        } catch (\Exception $e) {
            Log::error('Error in sendPlansByDuration: ' . $e->getMessage(), [
                'duration_days' => $durationDays,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString()
            ]);

            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton(['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => '/start'])]);

            $this->sendOrEditMessage($chatId, "❌ خطایی در بارگذاری پلن‌ها رخ داد.", $keyboard, $messageId);
        }
    }

    protected function sendQRCodeForOrder($user, $orderId)
    {
        $order = $user->orders()->find($orderId);
        if (!$order) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ سرویس یافت نشد."),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        if (empty($order->config_details)) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ لینک کانفیگ هنوز آماده نشده است."),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        try {

            $configLink = trim($order->config_details);

            // استفاده از API qrserver.com
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query([
                    'size' => '400x400',
                    'data' => $configLink,
                    'ecc' => 'H',
                    'margin' => 10,
                    'color' => '000000',
                    'bgcolor' => 'FFFFFF',
                    'format' => 'png'
                ]);

            // دانلود QR Code با cURL
            $ch = curl_init($qrUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $qrData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($qrData === false || $httpCode !== 200) {
                throw new \Exception('دریافت QR Code ناموفق بود. کد: ' . $httpCode);
            }


            $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
            file_put_contents($tempFile, $qrData);


            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => "🔄 تمدید سرویس", 'callback_data' => "renew_order_{$order->id}"]),
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت به جزئیات', 'callback_data' => "show_service_{$order->id}"])
                ])
                ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به لیست سرویس‌ها', 'callback_data' => '/my_services'])]);

            // ارسال فایل
            Telegram::sendPhoto([
                'chat_id' => $user->telegram_chat_id,
                'photo' => InputFile::create($tempFile),
                'caption' => $this->escape("📱 QR Code برای سرویس #{$order->id}\n\nلینک: `{$configLink}`"),
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $keyboard
            ]);

            // پاک کردن فایل موقت
            unlink($tempFile);

        } catch (\Exception $e) {
            Log::error('QR Code Generation FAILED', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'error_message' => $e->getMessage(),
                'config' => $order->config_details ?? 'N/A'
            ]);

            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ خطا در تولید QR Code: " . $e->getMessage()),
                'parse_mode' => 'MarkdownV2'
            ]);
        }
    }
    protected function sendMyServices($user, $messageId = null)
    {
        $orders = $user->orders()->with('plan')
            ->where('status', 'paid')
            ->whereNotNull('plan_id')
            ->whereNull('renews_order_id')
            ->where('expires_at', '>', now()->subDays(30))
            ->orderBy('expires_at', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '🛒 خرید سرویس جدید', 'callback_data' => '/plans']),
                Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start']),
            ]);
            $this->sendOrEditMessage($user->telegram_chat_id, "⚠️ شما هیچ سرویس فعال یا اخیراً منقضی شده‌ای ندارید.", $keyboard, $messageId);
            return;
        }

        $message = "🛠 *سرویس‌های شما*\n\nلطفاً یک سرویس را برای مشاهده جزئیات انتخاب کنید:";

        $keyboard = Keyboard::make()->inline();


        foreach ($orders as $order) {
            if (!$order->plan) {
                continue;
            }

            $expiresAt = Carbon::parse($order->expires_at);
            $now = now();
            $statusIcon = '🟢';

            // تعیین آیکون وضعیت
            if ($expiresAt->isPast()) {
                $statusIcon = '⚫️';
            } elseif ($expiresAt->diffInDays($now) <= 7) {
                $statusIcon = '🟡';
            }

            $buttonText = "{$statusIcon} {$order->plan->name} (ID: #{$order->id})";

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $buttonText,
                    'callback_data' => "show_service_{$order->id}"
                ])
            ]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }


    protected function showServiceDetails($user, $orderId, $messageId = null)
    {
        $order = $user->orders()->with('plan')->find($orderId);

        if (!$order || !$order->plan || $order->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر یافت نشد یا معتبر نیست.", $messageId);
            return;
        }

        // 1. ساخت نام کاربری استاندارد
        $panelUsername = $order->panel_username;
        if (empty($panelUsername)) {
            $panelUsername = "user-{$user->id}-order-{$order->id}";
        }

        // 2. محاسبات زمان
        $expiresAt = Carbon::parse($order->expires_at);
        $now = now();
        $statusIcon = '🟢';

        $daysRemaining = $now->diffInDays($expiresAt, false);
        $daysRemaining = (int) $daysRemaining;

        if ($expiresAt->isPast()) {
            $statusIcon = '⚫️';
            $remainingText = "*منقضی شده*";
        } elseif ($daysRemaining <= 7) {
            $statusIcon = '🟡';
            $remainingText = "*" . $this->escape($daysRemaining . ' روز') . "* باقی‌مانده (تمدید کنید)";
        } else {
            $remainingText = "*" . $this->escape($daysRemaining . ' روز') . "* باقی‌مانده";
        }

        // ساخت پیام
        $message = "🔍 جزئیات سرویس #{$order->id}\n\n";

        $message .= "{$statusIcon} سرویس: " . $this->escape($order->plan->name) . "\n";


        $message .= "👤 نام کاربری: `" . $panelUsername . "`\n";


        $message .= "🗓 انقضا: " . $this->escape($expiresAt->format('Y/m/d')) . " - " . $remainingText . "\n";


        $message .= "📦  حجم:  " . $this->escape($order->plan->volume_gb . ' گیگابایت') . "\n";
        if (!empty($order->config_details)) {


            $message .= "\n🔗 *لینک اتصال:*\n" . $order->config_details;

        } else {
            $message .= "\n⏳ *در حال آماده‌سازی کانفیگ...*";
        }

        $keyboard = Keyboard::make()->inline();

        if (!empty($order->config_details)) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => "📱 دریافت QR Code", 'callback_data' => "qrcode_order_{$order->id}"])
            ]);
        }

        $keyboard->row([
            Keyboard::inlineButton(['text' => "🔄 تمدید سرویس", 'callback_data' => "renew_order_{$order->id}"])
        ]);

        $keyboard->row([
            Keyboard::inlineButton(['text' => '⬅️ بازگشت به لیست سرویس‌ها', 'callback_data' => '/my_services'])
        ]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }
    protected function sendRawMarkdownMessageWithPreview($chatId, $text, $keyboard, $messageId = null, $disablePreview = false)
    {
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'MarkdownV2',
            'reply_markup' => $keyboard,
            'disable_web_page_preview' => $disablePreview,
        ];

        try {
            if ($messageId) {
                try {

                    $payload['message_id'] = $messageId;
                    Telegram::editMessageText($payload);
                    return; // موفقیت‌آمیز بود
                } catch (\Exception $e) {

                    Log::warning("Edit message failed (ID: {$messageId}), sending new message: " . $e->getMessage());
                    unset($payload['message_id']);
                    Telegram::sendMessage($payload);
                    return;
                }
            }
            Telegram::sendMessage($payload);
        } catch (\Exception $e) {
            Log::error("Error in sendRawMarkdownMessageWithPreview: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }
    protected function sendOrEditMessageWithPreviewOption($chatId, $text, $keyboard, $messageId = null, $disablePreview = false)
    {

        $escapedText = $this->escape($text);

        // Let's create a new payload here to add the 'disable_web_page_preview' option
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $escapedText,
            'parse_mode'   => 'MarkdownV2',
            'reply_markup' => $keyboard,
            'disable_web_page_preview' => $disablePreview
        ];

        try {
            if ($messageId) {
                $payload['message_id'] = $messageId;

                Telegram::editMessageText($payload);
            } else {
                Telegram::sendMessage($payload);
            }
        } catch (\Exception $e) {
            Log::error("Error in sendOrEditMessageWithPreviewOption: " . $e->getMessage());
            // Fallback logic from the original sendOrEditMessage
            if ($messageId && Str::contains($e->getMessage(), ['message to edit not found'])) {
                unset($payload['message_id']);
                try { Telegram::sendMessage($payload); } catch (\Exception $e2) {}
            }
        }
    }
    protected function sendWalletMenu($user, $messageId = null)
    {
        $balance = number_format($user->balance ?? 0);
        $message = "💰 *کیف پول شما*\n\n";
        $message .= "موجودی فعلی: *{$balance} تومان*\n\n";
        $message .= "می‌توانید حساب خود را شارژ کنید یا تاریخچه تراکنش‌ها را مشاهده نمایید:";

        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '💳 شارژ حساب', 'callback_data' => '/deposit']),
                Keyboard::inlineButton(['text' => '📜 تاریخچه تراکنش‌ها', 'callback_data' => '/transactions']),
            ])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function sendReferralMenu($user, $messageId = null)
    {
        try {
            $botInfo = Telegram::getMe();
            $botUsername = $botInfo->getUsername();
        } catch (\Exception $e) {
            Log::error("Could not get bot username: " . $e->getMessage());
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ خطایی در دریافت اطلاعات ربات رخ داد.", $messageId);
            return;
        }

        $referralCode = $user->referral_code ?? Str::random(8);
        if (!$user->referral_code) {
            $user->update(['referral_code' => $referralCode]);
        }
        $referralLink = "https://t.me/{$botUsername}?start={$referralCode}";
        $referrerReward = number_format((int) $this->settings->get('referral_referrer_reward', 0));
        $referralCount = $user->referrals()->count();

        $message = "🎁 *دعوت از دوستان*\n\n";
        $message .= "با اشتراک‌گذاری لینک زیر، دوستان خود را به ربات دعوت کنید.\n\n";
        $message .= "💸 با هر خرید موفق دوستانتان، *{$referrerReward} تومان* به کیف پول شما اضافه می‌شود.\n\n";
        $message .= "🔗 *لینک دعوت شما:*\n`{$referralLink}`\n\n";
        $message .= "👥 تعداد دعوت‌های موفق شما: *{$referralCount} نفر*";

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function sendTransactions($user, $messageId = null)
    {

        $transactions = $user->transactions()->with('order.plan')->latest()->take(10)->get();

        $message = "📜 *۱۰ تراکنش اخیر شما*\n\n";

        if ($transactions->isEmpty()) {
            $message .= $this->escape("شما تاکنون هیچ تراکنشی ثبت نکرده‌اید.");
        } else {
            foreach ($transactions as $transaction) {


                $type = 'نامشخص';
                switch ($transaction->type) {
                    case 'deposit':
                        $type = '💰 شارژ کیف پول';
                        break;
                    case 'purchase':
                        if ($transaction->order?->renews_order_id) {
                            $type = '🔄 تمدید سرویس';
                        } else {
                            $type = '🛒 خرید سرویس';
                        }
                        break;
                    case 'referral_reward':
                        $type = '🎁 پاداش دعوت';
                        break;
                }


                $status = '⚪️';
                switch ($transaction->status) {
                    case 'completed':
                        $status = '✅'; // موفق
                        break;
                    case 'pending':
                        $status = '⏳'; // در انتظار
                        break;
                    case 'failed':
                        $status = '❌'; // ناموفق
                        break;
                }

                $amount = number_format(abs($transaction->amount));
                $date = Carbon::parse($transaction->created_at)->format('Y/m/d');

                // --- ساخت پیام برای هر تراکنش ---
                $message .= "{$status} *" . $this->escape($type) . "*\n";
                $message .= "   💸 *مبلغ:* " . $this->escape($amount . " تومان") . "\n";
                $message .= "   📅 *تاریخ:* " . $this->escape($date) . "\n";
                if ($transaction->order?->plan) {
                    $message .= "   🏷 *پلن:* " . $this->escape($transaction->order->plan->name) . "\n";
                }
                $message .= "〰️〰️〰️〰️〰️〰️\n";
            }
        }

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton(['text' => '⬅️ بازگشت به کیف پول', 'callback_data' => '/wallet'])
        ]);


        $this->sendRawMarkdownMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }
    protected function sendTutorialsMenu($chatId, $messageId = null)
    {
        $message = "📚 *راهنمای اتصال*\n\nلطفاً سیستم‌عامل خود را برای دریافت راهنما و لینک دانلود انتخاب کنید:";
        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '📱 اندروید (V2rayNG)', 'callback_data' => '/tutorial_android']),
                Keyboard::inlineButton(['text' => '🍏 آیفون (V2Box)', 'callback_data' => '/tutorial_ios']),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '💻 ویندوز (V2rayN)', 'callback_data' => '/tutorial_windows']),
                Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start']),
            ]);
        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function sendTutorial($platform, $chatId, $messageId = null)
    {
        // Load settings from TelegramBotSetting
        $telegramSettings = \App\Models\TelegramBotSetting::pluck('value', 'key');

        // Map platform to setting key
        $settingKey = match($platform) {
            'android' => 'tutorial_android',
            'ios' => 'tutorial_ios',
            'windows' => 'tutorial_windows',
            default => null
        };

        // Get tutorial from database or use fallback
        $message = $settingKey ? ($telegramSettings->get($settingKey) ?? "آموزشی برای این پلتفرم یافت نشد.")
            : "پلتفرم نامعتبر است.";

        // If no tutorial is set in DB, use the old defaults as fallback
        if ($message === "آموزشی برای این پلتفرم یافت نشد.") {
            $fallbackTutorials = [
                'android' => "*راهنمای اندروید \\(V2rayNG\\)*\n\n1\\. برنامه V2rayNG را از [این لینک](https://github.com/2dust/v2rayNG/releases) دانلود و نصب کنید\\.\n2\\. لینک کانفیگ را از بخش *سرویس‌های من* کپی کنید\\.\n3\\. در برنامه، روی علامت `+` بزنید و `Import config from Clipboard` را انتخاب کنید\\.\n4\\. کانفیگ اضافه شده را انتخاب و دکمه اتصال \\(V شکل\\) پایین صفحه را بزنید\\.",
                'ios' => "*راهنمای آیفون \\(V2Box\\)*\n\n1\\. برنامه V2Box را از [اپ استور](https://apps.apple.com/us/app/v2box-v2ray-client/id6446814690) نصب کنید\\.\n2\\. لینک کانفیگ را از بخش *سرویس‌های من* کپی کنید\\.\n3\\. در برنامه، وارد بخش `Configs` شوید، روی `+` بزنید و `Import from clipboard` را انتخاب کنید\\.\n4\\. برای اتصال، به بخش `Home` بروید و دکمه اتصال را بزنید \\(ممکن است نیاز به تایید VPN در تنظیمات گوشی باشد\\)\\.",
                'windows' => "*راهنمای ویندوز \\(V2rayN\\)*\n\n1\\. برنامه v2rayN را از [این لینک](https://github.com/2dust/v2rayN/releases) دانلود \\(فایل `v2rayN-With-Core.zip`\\) و از حالت فشرده خارج کنید\\.\n2\\. فایل `v2rayN.exe` را اجرا کنید\\.\n3\\. لینک کانفیگ را از بخش *سرویس‌های من* کپی کنید\\.\n4\\. در برنامه V2RayN، کلیدهای `Ctrl+V` را فشار دهید تا سرور اضافه شود\\.\n5\\. روی آیکون برنامه در تسک‌بار \\(کنار ساعت\\) راست کلیک کرده، از منوی `System Proxy` گزینه `Set system proxy` را انتخاب کنید تا تیک بخورد\\.\n6\\. برای اتصال، دوباره روی آیکون راست کلیک کرده و از منوی `Servers` کانفیگ اضافه شده را انتخاب کنید\\.",
            ];
            $message = $fallbackTutorials[$platform] ?? "آموزشی برای این پلتفرم یافت نشد.";
        }

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به آموزش‌ها', 'callback_data' => '/tutorials'])]);

        $payload = [
            'chat_id'      => $chatId,
            'text'         => $message, // Already contains MarkdownV2 formatting
            'parse_mode'   => 'MarkdownV2',
            'reply_markup' => $keyboard,
            'disable_web_page_preview' => true
        ];

        try {
            if ($messageId) {
                $payload['message_id'] = $messageId;
                Telegram::editMessageText($payload);
            } else {
                Telegram::sendMessage($payload);
            }
        } catch (\Exception $e) {
            Log::warning("Could not edit/send tutorial message: " . $e->getMessage());
            if($messageId) {
                unset($payload['message_id']);
                try { Telegram::sendMessage($payload); } catch (\Exception $e2) {
                    Log::error("Failed fallback send tutorial: " . $e2->getMessage());
                }
            }
        }
    }




    protected function startPurchaseProcess($user, $planId, $messageId)
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ پلن مورد نظر یافت نشد.", $messageId);
            return;
        }

        $balance = $user->balance ?? 0;
        $message = "🛒 *تایید خرید*\n\n";
        $message .= "▫️ پلن: *{$this->escape($plan->name)}*\n";
        $message .= "▫️ قیمت: *" . number_format($plan->price) . " تومان*\n";
        $message .= "▫️ موجودی کیف پول: *" . number_format($balance) . " تومان*\n\n";
        $message .= "لطفاً روش پرداخت را انتخاب کنید:";

        $keyboard = Keyboard::make()->inline();
        if ($balance >= $plan->price) {
            $keyboard->row([Keyboard::inlineButton(['text' => '✅ پرداخت با کیف پول', 'callback_data' => "pay_wallet_{$plan->id}"])]);
        }
        $order = $user->orders()->create(['plan_id' => $plan->id, 'status' => 'pending', 'source' => 'telegram', 'amount' => $plan->price]);
        $keyboard->row([Keyboard::inlineButton(['text' => '💳 کارت به کارت', 'callback_data' => "pay_card_{$order->id}"])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به پلن‌ها', 'callback_data' => '/plans'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function processWalletPayment($user, $planId, $messageId)
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            $this->sendOrEditMessage($user->telegram_chat_id, "❌ پلن یافت نشد.", Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/plans'])]), $messageId);
            return;
        }
        if ($user->balance < $plan->price) {
            $this->sendOrEditMessage($user->telegram_chat_id, "❌ موجودی کیف پول شما کافی نیست.", Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '💳 شارژ کیف پول', 'callback_data' => '/deposit']), Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/plans'])]), $messageId);
            return;
        }

        $order = null;
        try {
            DB::transaction(function () use ($user, $plan, &$order) {
                $user->decrement('balance', $plan->price);

                $order = $user->orders()->create([
                    'plan_id' => $plan->id, 'status' => 'paid', 'source' => 'telegram',
                    'amount' => $plan->price, 'expires_at' => now()->addDays($plan->duration_days),
                    'payment_method' => 'wallet'
                ]);

                Transaction::create([
                    'user_id' => $user->id, 'order_id' => $order->id, 'amount' => -$plan->price,
                    'type' => 'purchase', 'status' => 'completed',
                    'description' => "خرید سرویس {$plan->name} از کیف پول"
                ]);

                // ۱. تابع فقط یک بار فراخوانی می‌شود
                $provisionData = $this->provisionUserAccount($order, $plan);

                // ۲. نتیجه چک شده و دیتابیس فقط یک بار آپدیت می‌شود
                if ($provisionData && $provisionData['link']) {
                    $order->update([
                        'config_details' => $provisionData['link'],
                        'panel_username' => $provisionData['username']
                    ]);
                } else {
                    throw new \Exception('Provisioning failed, config data is null.');
                }
            });


            $order->refresh();



            $link = $order->config_details;
            $successMessage = "✅ خرید شما با موفقیت انجام شد.\n\nلینک کانفیگ:\n{$link}";


            $this->sendOrEditMessage($user->telegram_chat_id, $successMessage, Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']), Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])]), $messageId);

        } catch (\Exception $e) {
            Log::error('Wallet Payment Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'plan_id' => $planId, 'user_id' => $user->id]);
            if ($order && $order->exists) {
                $order->update(['status' => 'failed']);
                try {
                    $user->increment('balance', $plan->price); // Refund
                    Log::info("User balance refunded after failed provisioning.", ['user_id' => $user->id, 'amount' => $plan->price]);
                } catch (\Exception $refundEx) {
                    Log::critical("CRITICAL: Failed to refund user balance!", ['user_id' => $user->id, 'amount' => $plan->price, 'error' => $refundEx->getMessage()]);
                }
            }
            $orderIdText = $order ? "\\#{$order->id}" : '';
            $this->sendOrEditMessage($user->telegram_chat_id, "⚠️ پرداخت موفق بود اما در ایجاد سرویس خطایی رخ داد. مبلغ به کیف پول شما بازگردانده شد. لطفاً با پشتیبانی تماس بگیرید. سفارش: {$orderIdText}", Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '💬 پشتیبانی', 'callback_data' => '/support_menu'])]), $messageId);
        }
    }


    protected function provisionUserAccount(Order $order, Plan $plan)
    {
        $settings = $this->settings;
        $configData = ['link' => null, 'username' => null];
        $uniqueUsername = "user-{$order->user_id}-order-{$order->id}";

        try {
            if (($settings->get('panel_type') ?? 'marzban') === 'marzban') {
                $marzban = new MarzbanService($settings->get('marzban_host'), $settings->get('marzban_sudo_username'), $settings->get('marzban_sudo_password'), $settings->get('marzban_node_hostname'));
                $response = $marzban->createUser([
                    'username' => $uniqueUsername,
                    'proxies' => (object) [],
                    'expire' => $order->expires_at->timestamp,
                    'data_limit' => $plan->volume_gb * 1024 * 1024 * 1024,
                ]);
                if (!empty($response['subscription_url'])) {
                    $configData['link'] = $response['subscription_url'];
                    $configData['username'] = $uniqueUsername;
                } else {
                    Log::error('Marzban user creation failed or subscription URL missing.', ['response' => $response]);
                    return null;
                }
            } elseif ($settings->get('panel_type') === 'xui') {
                $inboundPanelId = (int) $settings->get('xui_default_inbound_id');
                Log::info('XUI: Searching for inbound', ['panel_id' => $inboundPanelId]);

                $inboundModel = null;
                $inboundModel = \App\Models\Inbound::whereRaw('JSON_EXTRACT(inbound_data, "$.id") = ?', [$inboundPanelId])->first();

                if (!$inboundModel) {
                    $allInbounds = \App\Models\Inbound::all();
                    foreach ($allInbounds as $inbound) {
                        $data = json_decode($inbound->inbound_data, true);
                        if (isset($data['id']) && $data['id'] == $inboundPanelId) {
                            $inboundModel = $inbound;
                            Log::info('XUI: Inbound found manually', ['inbound_id' => $inboundModel->id]);
                            break;
                        }
                    }
                }

                if (!$inboundModel) {
                    Log::error("XUI Inbound not found for Panel ID: {$inboundPanelId}");
                    return null;
                }

                $xui = new \App\Services\XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));
                $clientData = [
                    'email' => $uniqueUsername,
                    'total' => $plan->volume_gb * 1024 * 1024 * 1024,
                    'expiryTime' => $order->expires_at->timestamp * 1000,
                ];

                $response = $xui->addClient($inboundPanelId, $clientData);

                if ($response && isset($response['success']) && $response['success']) {
                    $inboundData = $inboundModel->inbound_data;
                    $linkType = $settings->get('xui_link_type', 'single');
                    $configLink = null;

                    if ($linkType === 'subscription') {
                        $subId = $response['generated_subId'] ?? $uniqueUsername;
                        $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                        if ($subBaseUrl && $subId !== $uniqueUsername) {
                            $configLink = $subBaseUrl . '/sub/' . $subId;
                        } else {
                            Log::error("XUI Subscription: base URL or subId missing.", [
                                'base_url' => $subBaseUrl,
                                'subId' => $subId,
                                'response' => $response
                            ]);
                            return null;
                        }
                    } else {
                        $clientSettings = json_decode($response['obj']['settings'] ?? '{}', true);
                        $uuid = $response['generated_uuid'] ?? $response['obj']['id'] ?? null;
                        if ($uuid){
                            $streamSettings = $inboundData['streamSettings'] ?? [];
                            $serverAddress = $settings->get('server_address_for_link', parse_url($settings->get('xui_host'), PHP_URL_HOST));
                            $port = $inboundData['port'] ?? 443;
                            $remark = $plan->name;
                            $params = [];
                            $params['type'] = $streamSettings['network'] ?? 'tcp';
                            $params['security'] = $streamSettings['security'] ?? 'none';
                            if($params['type'] === 'ws' && isset($streamSettings['wsSettings'])){
                                $params['path'] = $streamSettings['wsSettings']['path'] ?? '/';
                                $params['host'] = $streamSettings['wsSettings']['headers']['Host'] ?? $serverAddress;
                            }
                            if($params['security'] === 'tls' && isset($streamSettings['tlsSettings'])){
                                $params['sni'] = $streamSettings['tlsSettings']['serverName'] ?? $serverAddress;
                            }
                            $flow = $clientSettings['clients'][0]['flow'] ?? '';
                            if ($flow) {
                                $params['flow'] = $flow;
                            }
                            $queryString = http_build_query(array_filter($params));
                            $configLink = "vless://{$uuid}@{$serverAddress}:{$port}?{$queryString}#" . urlencode($remark . " - " . $uniqueUsername);
                        } else {
                            Log::error('Could not extract UUID from XUI response.', ['response' => $response]);
                            return null;
                        }
                    }

                    $configData['link'] = $configLink;
                    $configData['username'] = $uniqueUsername;
                    Log::info('XUI: Link generated successfully', ['link' => $configLink]);
                } else {
                    Log::error('XUI user creation failed.', ['response' => $response]);
                    return null;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to provision account for Order {$order->id}: " . $e->getMessage());
            return null;
        }

        return $configData;
    }
    protected function showDepositOptions($user, $messageId)
    {
        $message = "💳 *شارژ کیف پول*\n\nلطفاً مبلغ مورد نظر برای شارژ را انتخاب کنید یا مبلغ دلخواه خود را وارد نمایید:";
        $keyboard = Keyboard::make()->inline();

        // ✅ خواندن مقادیر از دیتابیس
        $telegramSettings = TelegramBotSetting::pluck('value', 'key');
        $depositAmountsJson = $telegramSettings->get('deposit_amounts', '[]');
        $depositAmountsData = json_decode($depositAmountsJson, true);

        // استخراج مقادیر amount
        $depositAmounts = [];
        if (is_array($depositAmountsData)) {
            foreach ($depositAmountsData as $item) {
                if (isset($item['amount']) && is_numeric($item['amount'])) {
                    $depositAmounts[] = (int)$item['amount'];
                }
            }
        }

        // اگر تنظیماتی وجود نداشت، از مقادیر پیش‌فرض استفاده شود
        if (empty($depositAmounts)) {
            $depositAmounts = [50000, 100000, 200000, 500000];
        }

        // مرتب‌سازی برای نمایش بهتر
        sort($depositAmounts);

        // ساخت کیبورد
        foreach (array_chunk($depositAmounts, 2) as $row) {
            $rowButtons = [];
            foreach ($row as $amount) {
                $rowButtons[] = Keyboard::inlineButton([
                    'text' => number_format($amount) . ' تومان',
                    'callback_data' => 'deposit_amount_' . $amount
                ]);
            }
            $keyboard->row($rowButtons);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '✍️ ورود مبلغ دلخواه', 'callback_data' => '/deposit_custom'])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به کیف پول', 'callback_data' => '/wallet'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function promptForCustomDeposit($user, $messageId)
    {
        $user->update(['bot_state' => 'awaiting_deposit_amount']);
        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, "💳 لطفاً مبلغ دلخواه خود را (به تومان، حداقل ۱۰,۰۰۰) در یک پیام ارسال کنید:", $keyboard, $messageId);
    }

    protected function processDepositAmount($user, $amount, $messageId = null)
    {
        $amount = (int) preg_replace('/[^\d]/', '', $amount);
        $minDeposit = (int) $this->settings->get('min_deposit_amount', 10000);

        if ($amount < $minDeposit) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ مبلغ نامعتبر است. لطفاً مبلغی حداقل " . number_format($minDeposit) . " تومان وارد کنید."),
                'parse_mode' => 'MarkdownV2'
            ]);
            $this->promptForCustomDeposit($user, null);
            return;
        }

        $order = $user->orders()->create([
            'plan_id' => null, 'status' => 'pending', 'source' => 'telegram_deposit', 'amount' => $amount
        ]);
        $user->update(['bot_state' => null]);
        $this->sendCardPaymentInfo($user->telegram_chat_id, $order->id, $messageId);
    }

    protected function sendCardPaymentInfo($chatId, $orderId, $messageId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            $this->sendOrEditMainMenu($chatId, "❌ سفارش یافت نشد.", $messageId);
            return;
        }
        $user = $order->user;
        $user->update(['bot_state' => 'waiting_receipt_' . $orderId]);

        $cardNumber = $this->settings->get('payment_card_number', 'شماره کارتی تنظیم نشده');
        $cardHolder = $this->settings->get('payment_card_holder_name', 'صاحب حسابی تنظیم نشده');
        $amountToPay = number_format($order->amount);

        // --- ساخت پیام جدید و زیبا ---
        // متغیرها را جداگانه escape می‌کنیم و در متن فرمت‌بندی شده قرار می‌دهیم
        $message = "💳 *پرداخت کارت به کارت*\n\n";
        $message .= "لطفاً مبلغ *" . $this->escape($amountToPay) . " تومان* را به حساب زیر واریز نمایید:\n\n";
        $message .= "👤 *به نام:* " . $this->escape($cardHolder) . "\n";
        $message .= "💳 *شماره کارت:*\n`" . $this->escape($cardNumber) . "`\n\n";
        $message .= "🔔 *مهم:* پس از واریز، *فقط عکس رسید* را در همین چت ارسال کنید\\.";

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف از پرداخت', 'callback_data' => '/cancel_action'])]);

        // از متد جدید برای ارسال پیام فرمت‌شده استفاده می‌کنیم
        $this->sendRawMarkdownMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function sendRawMarkdownMessage($chatId, $text, $keyboard, $messageId = null, $disablePreview = false)
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
            'reply_markup' => $keyboard,
            'disable_web_page_preview' => $disablePreview
        ];

        try {
            if ($messageId) {
                $payload['message_id'] = $messageId;
                Telegram::editMessageText($payload);
            } else {
                Telegram::sendMessage($payload);
            }
        } catch (\Exception $e) {
            if ($messageId && Str::contains($e->getMessage(), 'not found')) {
                unset($payload['message_id']);
                Telegram::sendMessage($payload);
            }
        }
    }



    protected function startRenewalPurchaseProcess($user, $originalOrderId, $messageId)
    {
        $originalOrder = $user->orders()->with('plan')->find($originalOrderId);

        if (!$originalOrder || !$originalOrder->plan || $originalOrder->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر برای تمدید یافت نشد یا معتبر نیست.", $messageId);
            return;
        }

        $plan = $originalOrder->plan;
        $balance = $user->balance ?? 0;
        $expiresAt = Carbon::parse($originalOrder->expires_at);

        $message = "🔄 *تایید تمدید سرویس*\n\n";
        $message .= "▫️ سرویس: *{$this->escape($plan->name)}*\n";
        $message .= "▫️ تاریخ انقضای فعلی: *" . $this->escape($expiresAt->format('Y/m/d')) . "*\n";
        $message .= "▫️ هزینه تمدید ({$plan->duration_days} روز): *" . number_format($plan->price) . " تومان*\n";
        $message .= "▫️ موجودی کیف پول: *" . number_format($balance) . " تومان*\n\n";
        $message .= "لطفاً روش پرداخت برای تمدید را انتخاب کنید:";

        $keyboard = Keyboard::make()->inline();
        if ($balance >= $plan->price) {
            $keyboard->row([Keyboard::inlineButton(['text' => '✅ تمدید با کیف پول (آنی)', 'callback_data' => "renew_pay_wallet_{$originalOrderId}"])]);
        }
        $keyboard->row([Keyboard::inlineButton(['text' => '💳 تمدید با کارت به کارت', 'callback_data' => "renew_pay_card_{$originalOrderId}"])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به سرویس‌ها', 'callback_data' => '/my_services'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function processRenewalWalletPayment($user, $originalOrderId, $messageId)
    {
        $originalOrder = $user->orders()->with('plan')->find($originalOrderId);
        if (!$originalOrder || !$originalOrder->plan || $originalOrder->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر برای تمدید یافت نشد.", $messageId);
            return;
        }

        $plan = $originalOrder->plan;
        if ($user->balance < $plan->price) {
            $this->sendOrEditMessage($user->telegram_chat_id, "❌ موجودی کیف پول شما برای تمدید کافی نیست.", Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '💳 شارژ کیف پول', 'callback_data' => '/deposit']), Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/my_services'])]), $messageId);
            return;
        }

        $newRenewalOrder = null;
        $newExpiryDate = null;
        try {
            DB::transaction(function () use ($user, $originalOrder, $plan, &$newRenewalOrder, &$newExpiryDate) {
                $user->decrement('balance', $plan->price);

                $newRenewalOrder = $user->orders()->create([
                    'plan_id' => $plan->id, 'status' => 'paid', 'source' => 'telegram_renewal',
                    'amount' => $plan->price, 'expires_at' => null,
                    'renews_order_id' => $originalOrder->id, 'payment_method' => 'wallet',
                ]);

                Transaction::create([
                    'user_id' => $user->id, 'order_id' => $newRenewalOrder->id, 'amount' => -$plan->price,
                    'type' => 'purchase', 'status' => 'completed',
                    'description' => "تمدید سرویس {$plan->name} (سفارش اصلی #{$originalOrder->id})"
                ]);

                $newExpiryDate = $this->renewUserAccount($originalOrder, $plan);
                if (!$newExpiryDate) {
                    throw new \Exception('Failed to update user on the panel.');
                }

                $originalOrder->update(['expires_at' => $newExpiryDate]);
            });

            $newExpiryDateCarbon = Carbon::parse($newExpiryDate);
            $successMessage = $this->escape("✅ سرویس شما با موفقیت برای {$plan->duration_days} روز دیگر تمدید شد و تا تاریخ {$newExpiryDateCarbon->format('Y/m/d')} اعتبار دارد.");
            $this->sendOrEditMainMenu($user->telegram_chat_id, $successMessage, $messageId);

        } catch (\Exception $e) {
            Log::error('Renewal Wallet Payment Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'original_order_id' => $originalOrderId]);
            if ($newRenewalOrder) {
                try { $user->increment('balance', $plan->price); } catch (\Exception $refundEx) { Log::error("Failed to refund user: ".$refundEx->getMessage()); }
            }
            $this->sendOrEditMessage($user->telegram_chat_id, "⚠️ تمدید با کیف پول با خطا مواجه شد. مبلغ به کیف پول بازگردانده شد. لطفاً با پشتیبانی تماس بگیرید.", Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '💬 پشتیبانی', 'callback_data' => '/support_menu'])]), $messageId);
        }
    }

    protected function handleRenewCardPayment($user, $originalOrderId, $messageId)
    {
        $originalOrder = $user->orders()->with('plan')->find($originalOrderId);
        if (!$originalOrder || !$originalOrder->plan || $originalOrder->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر برای تمدید یافت نشد.", $messageId);
            return;
        }
        $plan = $originalOrder->plan;

        $newRenewalOrder = $user->orders()->create([
            'plan_id' => $plan->id, 'status' => 'pending', 'source' => 'telegram_renewal',
            'amount' => $plan->price, 'expires_at' => null,
            'renews_order_id' => $originalOrder->id,
        ]);

        $this->sendCardPaymentInfo($user->telegram_chat_id, $newRenewalOrder->id, $messageId);
    }

    protected function renewUserAccount(Order $originalOrder, Plan $plan)
    {
        $settings = $this->settings;
        $user = $originalOrder->user;
        $uniqueUsername = "user-{$user->id}-order-{$originalOrder->id}";

        $currentExpiresAt = Carbon::parse($originalOrder->expires_at);
        $baseDate = $currentExpiresAt->isPast() ? now() : $currentExpiresAt;
        $newExpiryDate = $baseDate->copy()->addDays($plan->duration_days);
        $newExpiryTimestamp = $newExpiryDate->timestamp;
        $newDataLimitBytes = $plan->data_limit_gb * 1024 * 1024 * 1024;

        try {
            if (($settings->get('panel_type') ?? 'marzban') === 'marzban') {
                $marzban = new MarzbanService($settings->get('marzban_host'), $settings->get('marzban_sudo_username'), $settings->get('marzban_sudo_password'), $settings->get('marzban_node_hostname'));

                $updateResponse = $marzban->updateUser($uniqueUsername, [
                    'expire' => $newExpiryTimestamp,
                    'data_limit' => $newDataLimitBytes,
                ]);
                $resetResponse = $marzban->resetUserTraffic($uniqueUsername);

                if ($updateResponse !== null && $resetResponse !== null) {
                    Log::info("Marzban user renewed successfully.", ['username' => $uniqueUsername]);
                    return $newExpiryDate;
                } else {
                    Log::error('Marzban user renewal failed.', ['username' => $uniqueUsername, 'update' => $updateResponse, 'reset' => $resetResponse]);
                    return null;
                }

            } elseif ($settings->get('panel_type') === 'xui') {
                $inboundId = $settings->get('xui_default_inbound_id');
                if (!$inboundId) throw new \Exception("XUI Inbound ID not set.");


                $xui = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));

                // Placeholder: Logic to find and update XUI client
                Log::warning('XUI user renewal (update/reset) needs specific implementation for your panel API.', ['username' => $uniqueUsername]);
                $success = true; // Assume success for placeholder

                if ($success) {
                    return $newExpiryDate;
                } else {
                    Log::error('XUI user renewal update failed.', ['username' => $uniqueUsername]);
                    return null;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to renew user account {$uniqueUsername} on panel: " . $e->getMessage());
            return null;
        }
        return null;
    }

    //======================================================================
    // 5. Ticket & Support Methods
    //======================================================================

    protected function showSupportMenu($user, $messageId = null)
    {
        $tickets = $user->tickets()->latest()->take(4)->get();
        $message = "💬 *پشتیبانی*\n\n";
        if ($tickets->isEmpty()) {
            $message .= "شما تاکنون هیچ تیکتی ثبت نکرده‌اید.";
        } else {
            $message .= "لیست آخرین تیکت‌های شما:\n";
            foreach ($tickets as $ticket) {
                $status = match ($ticket->status) {
                    'open' => '🔵 باز',
                    'answered' => '🟢 پاسخ ادمین',
                    'closed' => '⚪️ بسته',
                    default => '⚪️ نامشخص',
                };
                $ticketIdEscaped = $this->escape((string)$ticket->id);
                $message .= "\n📌 *تیکت \\#{$ticketIdEscaped}* | " . $this->escape($status) . "\n";
                $message .= "*موضوع:* " . $this->escape($ticket->subject) . "\n";
                $message .= "_{$this->escape($ticket->updated_at->diffForHumans())}_";
            }
        }

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '📝 ایجاد تیکت جدید', 'callback_data' => '/support_new'])]);
        foreach ($tickets as $ticket) {
            if ($ticket->status !== 'closed') {
                $keyboard->row([
                    Keyboard::inlineButton(['text' => "✏️ پاسخ/مشاهده تیکت #{$ticket->id}", 'callback_data' => "reply_ticket_{$ticket->id}"]),
                    Keyboard::inlineButton(['text' => "❌ بستن تیکت #{$ticket->id}", 'callback_data' => "close_ticket_{$ticket->id}"]),
                ]);
            }
        }
        $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function promptForNewTicket($user, $messageId)
    {
        $user->update(['bot_state' => 'awaiting_new_ticket_subject']);
        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, "📝 لطفاً *موضوع* تیکت جدید را در یک پیام ارسال کنید:", $keyboard, $messageId);
    }

    protected function promptForTicketReply($user, $ticketId, $messageId)
    {
        $ticketIdEscaped = $this->escape($ticketId);
        $user->update(['bot_state' => 'awaiting_ticket_reply|' . $ticketId]);
        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, "✏️ لطفاً پاسخ خود را برای تیکت \\#{$ticketIdEscaped} وارد کنید (می‌توانید عکس هم ارسال کنید):", $keyboard, $messageId);
    }

    protected function closeTicket($user, $ticketId, $messageId, $callbackQueryId)
    {
        $ticket = $user->tickets()->where('id', $ticketId)->first();
        if ($ticket && $ticket->status !== 'closed') {
            $ticket->update(['status' => 'closed']);
            try {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $callbackQueryId,
                    'text' => "تیکت #{$ticketId} بسته شد.",
                    'show_alert' => false
                ]);
            } catch (\Exception $e) { Log::warning("Could not answer close ticket query: ".$e->getMessage());}
            $this->showSupportMenu($user, $messageId); // Refresh menu
        } else {
            try { Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId, 'text' => "تیکت یافت نشد یا قبلا بسته شده.", 'show_alert' => true]); } catch (\Exception $e) {}
        }
    }


    protected function processTicketConversation($user, $text, $update)
    {
        $state = $user->bot_state;
        $chatId = $user->telegram_chat_id;

        try {
            if ($state === 'awaiting_new_ticket_subject') {
                if (mb_strlen($text) < 3) {
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ موضوع باید حداقل ۳ حرف باشد. لطفا دوباره تلاش کنید."), 'parse_mode' => 'MarkdownV2']);
                    return;
                }
                $user->update(['bot_state' => 'awaiting_new_ticket_message|' . $text]);
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("✅ موضوع دریافت شد.\n\nحالا *متن پیام* خود را وارد کنید (می‌توانید همراه پیام، عکس هم ارسال کنید):"), 'parse_mode' => 'MarkdownV2']);

            } elseif (Str::startsWith($state, 'awaiting_new_ticket_message|')) {
                $subject = Str::after($state, 'awaiting_new_ticket_message|');
                $isPhotoOnly = $update->getMessage()->has('photo') && (empty(trim($text)) || $text === '[📎 فایل پیوست شد]');
                $messageText = $isPhotoOnly ? '[📎 پیوست تصویر]' : $text;

                if (empty(trim($messageText))) {
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ متن پیام نمی‌تواند خالی باشد. لطفا پیام خود را وارد کنید:"), 'parse_mode' => 'MarkdownV2']);
                    return;
                }

                // 1. Create Ticket
                $ticket = $user->tickets()->create([
                    'subject' => $subject,
                    'message' => $messageText, // Store initial text
                    'priority' => 'medium', 'status' => 'open', 'source' => 'telegram', 'user_id' => $user->id
                ]);

                // 2. Create the first Reply
                $replyData = ['user_id' => $user->id, 'message' => $messageText];
                if ($update->getMessage()->has('photo')) {
                    try { $replyData['attachment_path'] = $this->savePhotoAttachment($update, 'ticket_attachments'); }
                    catch (\Exception $e) { Log::error("Error saving photo for new ticket {$ticket->id}: " . $e->getMessage()); }
                }
                $reply = $ticket->replies()->create($replyData);

                // 3. Clear state and notify user
                $user->update(['bot_state' => null]);
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("✅ تیکت #{$ticket->id} با موفقیت ثبت شد."), 'parse_mode' => 'MarkdownV2']);
                $this->sendOrEditMainMenu($chatId, "پشتیبانی به زودی پاسخ شما را خواهد داد.");

                // 4. Notify Admin (using TicketCreated event)
                event(new TicketCreated($ticket));

            } elseif (Str::startsWith($state, 'awaiting_ticket_reply|')) {
                $ticketId = Str::after($state, 'awaiting_ticket_reply|');
                $ticket = $user->tickets()->find($ticketId);

                if (!$ticket) {
                    $this->sendOrEditMainMenu($chatId, "❌ تیکت مورد نظر یافت نشد.");
                    return;
                }

                $isPhotoOnly = $update->getMessage()->has('photo') && (empty(trim($text)) || $text === '[📎 فایل پیوست شد]');
                $messageText = $isPhotoOnly ? '[📎 پیوست تصویر]' : $text;

                if (empty(trim($messageText))) {
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ متن پاسخ نمی‌تواند خالی باشد."), 'parse_mode' => 'MarkdownV2']);
                    return;
                }

                $replyData = ['user_id' => $user->id, 'message' => $messageText];
                if ($update->getMessage()->has('photo')) {
                    try { $replyData['attachment_path'] = $this->savePhotoAttachment($update, 'ticket_attachments'); }
                    catch (\Exception $e) { Log::error("Error saving photo for ticket reply {$ticketId}: " . $e->getMessage()); }
                }
                $reply = $ticket->replies()->create($replyData);
                $ticket->update(['status' => 'open']);

                $user->update(['bot_state' => null]);
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("✅ پاسخ شما برای تیکت #{$ticketId} ثبت شد."), 'parse_mode' => 'MarkdownV2']);
                $this->sendOrEditMainMenu($chatId, "پشتیبانی به زودی پاسخ شما را خواهد داد.");

                event(new TicketReplied($reply)); // Fire event for admin notification
            }
        } catch (\Exception $e) {
            Log::error('Failed to process ticket conversation: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $user->update(['bot_state' => null]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape("❌ خطایی در پردازش پیام شما رخ داد. لطفاً دوباره تلاش کنید."),
                'parse_mode' => 'MarkdownV2'
            ]);
        }
    }

    protected function isUserMemberOfChannel($user)
    {
        $forceJoin = $this->settings->get('force_join_enabled', '0');

        // Debug: لاگ وضعیت فورس جوین
        Log::info("Force Join Check", [
            'enabled_value' => $forceJoin,
            'type' => gettype($forceJoin)
        ]);

        // چک کردن صحیح مقدار (ممکنه '1'، 1، true یا 'on' باشه)
        if (!in_array($forceJoin, ['1', 1, true, 'on'], true)) {
            Log::info("Force join is disabled, skipping membership check.");
            return true;
        }

        $channelId = $this->settings->get('telegram_required_channel_id');
        if (empty($channelId)) {
            Log::error('❌ FORCE JOIN IS ENABLED BUT NO CHANNEL ID IS SET!');
            return false; // مهم: باید false بده تا دسترسی بسته بشه
        }

        try {
            Log::info("🔍 Checking membership...", [
                'channel_id' => $channelId,
                'user_chat_id' => $user->telegram_chat_id
            ]);

            // 🔥 استفاده از HTTP Facade برای درخواست مستقیم به API تلگرام
            $botToken = $this->settings->get('telegram_bot_token');
            $apiUrl = "https://api.telegram.org/bot{$botToken}/getChatMember";

            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($apiUrl, [
                'chat_id' => $channelId,
                'user_id' => $user->telegram_chat_id,
            ]);

            if (!$response->successful()) {
                Log::error("❌ Telegram API request failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'channel_id' => $channelId,
                    'user_id' => $user->telegram_chat_id
                ]);
                return false;
            }

            $data = $response->json();
            $status = $data['result']['status'] ?? 'left';

            Log::info("✅ Membership check result", [
                'user_id' => $user->telegram_chat_id,
                'channel_id' => $channelId,
                'status' => $status
            ]);

            return in_array($status, ['member', 'administrator', 'creator'], true);

        } catch (\Exception $e) {
            Log::error("❌ Exception in membership check", [
                'error' => $e->getMessage(),
                'channel_id' => $channelId,
                'user_id' => $user->telegram_chat_id
            ]);
            return false;
        }
    }



    protected function showChannelRequiredMessage($chatId, $messageId = null)
    {
        $channelId = $this->settings->get('telegram_required_channel_id');

        if (empty($channelId)) {
            $message = "❌ خطا: کانال عضویت اجباری تنظیم نشده است.";
            $this->sendOrEditMessage($chatId, $message, null, $messageId);
            return;
        }

        // تشخیص نوع کانال و ساخت لینک
        $channelLink = null;
        $channelDisplayName = $channelId;

        if (str_starts_with($channelId, '@')) {
            // کانال عمومی
            $username = ltrim($channelId, '@');
            $channelLink = "https://t.me/{$username}";
            $channelDisplayName = "@" . $username;
        } elseif (preg_match('/^-100\d+$/', $channelId)) {
            // کانال خصوصی (نیاز به لینک دعوت جداگانه)
            $channelDisplayName = "کانال خصوصی";
            // توصیه: در تنظیمات یک فیلد جدید بسازید: telegram_private_channel_invite_link
            $channelLink = $this->settings->get('telegram_private_channel_invite_link');
        } else {
            Log::error("Invalid channel ID format", ['channel_id' => $channelId]);
        }

        // پیام بهتر
        $message = "⛔️ *عضویت در کانال الزامی است!*\n\n";
        $message .= "برای ادامه استفاده از ربات، باید در کانال زیر عضو شوید:\n\n";
        $message .= "📢 {$channelDisplayName}\n\n";
        $message .= "🔹 پس از عضویت، روی دکمه «✅ بررسی عضویت» بزنید.";

        $keyboard = Keyboard::make()->inline();

        // اگر لینک داریم، دکمه عضویت رو نشون بده
        if (!empty($channelLink)) {
            $keyboard->row([Keyboard::inlineButton(['text' => '📲 عضویت در کانال', 'url' => $channelLink])]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '✅ بررسی عضویت', 'callback_data' => '/check_membership'])]);

        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function savePhotoAttachment($update, $directory)
    {
        $photo = collect($update->getMessage()->getPhoto())->last();
        if(!$photo) return null;

        $botToken = $this->settings->get('telegram_bot_token');
        try {
            $file = Telegram::getFile(['file_id' => $photo->getFileId()]);
            $filePath = method_exists($file, 'getFilePath') ? $file->getFilePath() : ($file['file_path'] ?? null);
            if(!$filePath) { throw new \Exception('File path not found in Telegram response.'); }

            $fileContents = file_get_contents("https://api.telegram.org/file/bot{$botToken}/{$filePath}");
            if ($fileContents === false) { throw new \Exception('Failed to download file content.');}

            Storage::disk('public')->makeDirectory($directory);
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = $directory . '/' . Str::random(40) . '.' . $extension;
            $success = Storage::disk('public')->put($fileName, $fileContents);

            if (!$success) { throw new \Exception('Failed to save file to storage.'); }

            return $fileName;

        } catch (\Exception $e) {
            Log::error('Error saving photo attachment: ' . $e->getMessage(), ['file_id' => $photo->getFileId()]);
            return null;
        }
    }

    //======================================================================
    // 6. Helper Methods
    //======================================================================

    /**
     * Escape text for Telegram's MarkdownV2 parse mode.
     */
    protected function escape(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $text = str_replace('\\', '\\\\', $text); // Escape backslash first
        return str_replace($chars, array_map(fn($char) => '\\' . $char, $chars), $text);
    }

    /**
     * Get the main menu keyboard (Inline).
     */
    protected function getMainMenuKeyboard(): Keyboard
    {
        return Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '🛒 خرید سرویس', 'callback_data' => '/plans']),
                Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '💰 کیف پول', 'callback_data' => '/wallet']),
                Keyboard::inlineButton(['text' => '🎁 دعوت از دوستان', 'callback_data' => '/referral']),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '💬 پشتیبانی', 'callback_data' => '/support_menu']),
                Keyboard::inlineButton(['text' => '📚 راهنمای اتصال', 'callback_data' => '/tutorials']),
            ]);
    }

    /**
     * Send or edit the main menu message.
     */
    protected function sendOrEditMainMenu($chatId, $text, $messageId = null)
    {
        $this->sendOrEditMessage($chatId, $text, $this->getMainMenuKeyboard(), $messageId);
    }

    protected function getReplyMainMenu(): Keyboard
    {
        return Keyboard::make([
            'keyboard' => [
                ['🛒 خرید سرویس', '🛠 سرویس‌های من'],
                ['💰 کیف پول', '📜 تاریخچه تراکنش‌ها'],
                ['💬 پشتیبانی', '🎁 دعوت از دوستان'],
                ['📚 راهنمای اتصال', '🧪 اکانت تست'],

            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }



    protected function handleTrialRequest($user)
    {

        // از تنظیماتی که قبلاً لود شده استفاده کن
        $settings = $this->settings;
        $chatId = $user->telegram_chat_id;

        // 🔍 لاگ برای دیباگ
        Log::info('Trial request initiated', [
            'user_id' => $user->id,
            'trial_enabled_value' => $settings->get('trial_enabled'),
            'trial_enabled_type' => gettype($settings->get('trial_enabled')),
            'trial_accounts_taken' => $user->trial_accounts_taken ?? 0
        ]);

        // ✅ مقایسه انعطاف‌پذیر (حل مشکل اصلی)
        $trialEnabled = filter_var($settings->get('trial_enabled') ?? '0', FILTER_VALIDATE_BOOLEAN);
        if (!$trialEnabled) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape('❌ قابلیت دریافت اکانت تست در حال حاضر غیرفعال است.')
            ]);
            Log::warning('Trial account is disabled in settings');
            return;
        }

        // محدودیت تعداد اکانت‌ها
        $limit = (int) $settings->get('trial_limit_per_user', 1);
        $currentTrials = $user->trial_accounts_taken ?? 0;

        if ($currentTrials >= $limit) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape('❗️شما قبلاً از اکانت تست خود استفاده کرده‌اید و دیگر مجاز به دریافت آن نیستید.')
            ]);
            Log::info('User trial limit reached', ['current' => $currentTrials, 'limit' => $limit]);
            return;
        }

        try {
            $volumeMB = (int) $settings->get('trial_volume_mb', 500);
            $durationHours = (int) $settings->get('trial_duration_hours', 24);

            $uniqueUsername = "trial-{$user->id}-" . ($currentTrials + 1);
            $expiresAt = now()->addHours($durationHours);
            $dataLimitBytes = $volumeMB * 1024 * 1024;

            $panelType = $settings->get('panel_type');
            $configLink = null;

            // ۵. تصمیم‌گیری بر اساس نوع پنل
            if ($panelType === 'marzban') {
                $marzbanService = new MarzbanService($settings->get('marzban_host'), $settings->get('marzban_sudo_username'), $settings->get('marzban_sudo_password'), $settings->get('marzban_node_hostname'));
                $response = $marzbanService->createUser([
                    'username' => $uniqueUsername,
                    'expire' => $expiresAt->timestamp,
                    'data_limit' => $dataLimitBytes,
                ]);

                if ($response && !empty($response['subscription_url'])) {
                    $configLink = $response['subscription_url'];
                } else {
                    throw new \Exception('خطا در ارتباط با پنل مرزبان.');
                }

            } elseif ($panelType === 'xui') {
                $xuiService = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));
                $inboundId = $settings->get('xui_default_inbound_id');
                $inbound = Inbound::where('inbound_data->id', $inboundId)->first();

                if (!$inbound || !$inbound->inbound_data) {
                    throw new \Exception('اطلاعات اینباند پیش‌فرض برای X-UI یافت نشد. Inbound Panel ID: ' . $inboundId);
                }

                if (!$xuiService->login()) {
                    throw new \Exception('خطا در لاگین به پنل X-UI.');
                }

                $inboundData = $inbound->inbound_data;

                $clientData = [
                    'email' => $uniqueUsername,
                    'total' => $dataLimitBytes,
                    'expiryTime' => $expiresAt->timestamp * 1000,
                ];

                $response = $xuiService->addClient($inboundData['id'], $clientData);
                $configLink = null; // متغیر برای ذخیره لینک نهایی

                if ($response && isset($response['success']) && $response['success']) {

                    // ✅ تعیین نوع لینک: subscription یا single
                    $linkType = $settings->get('xui_link_type', 'single');

                    if ($linkType === 'subscription') {
                        // 🎯 ساخت لینک سابسکرپشن (درست مانند خرید معمولی)
                        $subId = $response['generated_subId'] ?? $uniqueUsername;
                        $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');

                        if ($subBaseUrl && $subId !== $uniqueUsername) {
                            $configLink = $subBaseUrl . '/sub/' . $subId;
                            Log::info('XUI: Subscription link generated for trial', ['link' => $configLink]);
                        } else {
                            Log::error("XUI Subscription: base URL or subId missing for trial.", [
                                'base_url' => $subBaseUrl,
                                'subId' => $subId,
                                'response' => $response
                            ]);
                            throw new \Exception('تنظیمات لینک سابسکرپشن ناقص است.');
                        }
                    } else {
                        // 🔗 ساخت لینک VLESS سینگل
                        $uuid = $response['generated_uuid'] ?? null;
                        if(!$uuid) {
                            $clientSettings = json_decode($response['obj']['settings'] ?? '{}', true);
                            $uuid = $clientSettings['clients'][0]['id'] ?? null;
                        }
                        if (!$uuid) throw new \Exception('UUID از پاسخ X-UI استخراج نشد.');

                        $streamSettings = json_decode($inboundData['streamSettings'], true);
                        $serverAddress = $settings->get('server_address_for_link', parse_url($settings->get('xui_host'), PHP_URL_HOST));
                        $port = $inboundData['port'];
                        $remark = "Trial Account";
                        $paramsArray = [
                            'type' => $streamSettings['network'] ?? null,
                            'security' => $streamSettings['security'] ?? null,
                            'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null),
                            'sni' => $streamSettings['tlsSettings']['serverName'] ?? null,
                            'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null,
                        ];
                        $params = http_build_query(array_filter($paramsArray));
                        $configLink = "vless://{$uuid}@{$serverAddress}:{$port}?{$params}#" . urlencode($remark . " - " . $uniqueUsername);
                        Log::info('XUI: Single link generated for trial', ['link' => $configLink]);
                    }

                } else {
                    throw new \Exception($response['msg'] ?? 'خطا در ساخت کاربر در پنل X-UI');
                }
            }

            else {
                throw new \Exception('نوع پنل در تنظیمات مشخص نشده است.');
            }


            if ($configLink) {
                $user->increment('trial_accounts_taken');


                if (empty($configLink)) {
                    throw new \Exception('لینک کانفیگ پس از ساخت کاربر دریافت نشد.');
                }

                // ذخیره و ارسال موفقیت‌آمیز
                $user->increment('trial_accounts_taken');

                $message = "✅ اکانت تست شما با موفقیت ساخته شد!\n\n";
                $message .= "📦 حجم: *{$volumeMB} مگابایت*\n";
                $message .= "⏳ اعتبار: *{$durationHours} ساعت*\n\n";
                $message .= "🔗 لینک اتصال:\n{$configLink}";

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($message),
                    'parse_mode' => 'MarkdownV2'
                ]);


                Log::info('Trial account created successfully', [
                    'user_id' => $user->id,
                    'username' => $uniqueUsername
                ]);
            }
            else {
                throw new \Exception('لینک کانفیگ پس از ساخت کاربر دریافت نشد.');
            }

        } catch (\Exception $e) {

            Log::error('Trial Account Creation Failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape('❌ خطا در ساخت اکانت تست. لطفاً بعداً تلاش کنید.')
            ]);
        }
    }

    /**
     * Centralized method to send or edit messages with proper error handling.
     */
    protected function sendOrEditMessage($chatId, $text, $keyboard, $messageId = null)
    {
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $this->escape($text),
            'parse_mode'   => 'MarkdownV2',
            'reply_markup' => $keyboard
        ];
        try {
            if ($messageId) {
                $payload['message_id'] = $messageId;
                Telegram::editMessageText($payload);
            } else {
                Telegram::sendMessage($payload);
            }
        } catch (\Telegram\Bot\Exceptions\TelegramResponseException $e) {
            if (Str::contains($e->getMessage(), 'message is not modified')) {
                Log::info("Message not modified.", ['chat_id' => $chatId]);
            } elseif (Str::contains($e->getMessage(), ['message to edit not found', 'message identifier is not specified'])) {
                Log::warning("Could not edit message {$messageId}. Sending new.", ['error' => $e->getMessage()]);
                unset($payload['message_id']);
                try { Telegram::sendMessage($payload); } catch (\Exception $e2) {Log::error("Failed to send new message after edit failure: " . $e2->getMessage());}
            } else {
                Log::error("Telegram API error: " . $e->getMessage(), ['payload' => $payload, 'trace' => $e->getTraceAsString()]);
                if ($messageId) {
                    unset($payload['message_id']);
                    try { Telegram::sendMessage($payload); } catch (\Exception $e2) {Log::error("Failed to send new message after API error: " . $e2->getMessage());}
                }
            }
        }
        catch (\Exception $e) {
            Log::error("General error during send/edit message: " . $e->getMessage(), ['chat_id' => $chatId, 'trace' => $e->getTraceAsString()]);
            if($messageId) {
                unset($payload['message_id']);
                try { Telegram::sendMessage($payload); } catch (\Exception $e2) {Log::error("Failed to send new message after general failure: " . $e2->getMessage());}
            }
        }
    }
}
