<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\TelegramBotSetting;
use App\Services\XUIService;
use App\Models\User;
use App\Services\MarzbanService;
use App\Services\PasargadService;
use App\Models\Inbound;
use Modules\Ticketing\Events\TicketCreated;
use Modules\Ticketing\Events\TicketReplied;
use Modules\Ticketing\Models\Ticket;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http; // ✅ اضافه شده
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Str;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Carbon\Carbon;
use Telegram\Bot\FileUpload\InputFile;

class WebhookController extends Controller
{
    protected $settings;

    /**
     * ✅ اضافه شده: کانستراکتور برای اطمینان از مقداردهی settings
     */
    public function __construct()
    {
        $this->settings = collect();
    }

    public function sendBroadcastMessage(string $chatId, string $message): bool
    {
        try {
            if ($this->settings->isEmpty()) { // ✅ اصلاح: استفاده از isEmpty() به جای null check
                $this->settings = Setting::all()->pluck('value', 'key');
            }

            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                Log::error('❌ Cannot send broadcast message: bot token is not set.');
                return false;
            }

            // ✅ اصلاح: استفاده از Telegram facade بدون بک‌اسلش اضافی
            Telegram::setAccessToken($botToken);

            $title = "📢 *اعلان ویژه از سوی تیم مدیریت*";
            $divider = str_repeat('━', 20);
            $footer = "💠 *با تشکر از همراهی شما* 💠";

            $formattedMessage = $this->escape($message);

            $fullMessage = "{$title}\n\n{$divider}\n\n📝 *{$formattedMessage}*\n\n{$divider}\n\n{$footer}";

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $fullMessage,
                'parse_mode' => 'MarkdownV2',
            ]);

            Log::info("✅ Broadcast message sent successfully to chat {$chatId}");
            return true;
        } catch (\Exception $e) {
            Log::warning("⚠️ Failed to send broadcast message to user {$chatId}: " . $e->getMessage());
            return false;
        }
    }

    public function sendSingleMessageToUser(string $chatId, string $message): bool
    {
        try {
            if ($this->settings->isEmpty()) { // ✅ اصلاح
                $this->settings = Setting::all()->pluck('value', 'key');
            }
            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                Log::error('Cannot send single Telegram message: bot token is not set.');
                return false;
            }
            Telegram::setAccessToken($botToken);

            $header = "📢 *پیام فوری از مدیریت*";
            // ✅ اصلاح: نقطه در MarkdownV2 باید escape شود اما توی کپشن نیاز نیست
            $notice = "⚠️ این یک پیام اطلاع‌رسانی یک‌طرفه از پنل ادمین است و پاسخ دادن به آن در این چت، پیگیری نخواهد شد.";

            $adminMessageLines = explode("\n", $message);
            $formattedMessage = implode("\n", array_map(fn($line) => "> " . trim($line), $adminMessageLines));

            $fullMessage = "{$header}\n\n{$this->escape($notice)}\n\n{$formattedMessage}";

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $fullMessage,
                'parse_mode' => 'MarkdownV2',
            ]);

            Log::info("Admin sent message to user {$chatId}.", ['message' => $message]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send single Telegram message: ' . $e->getMessage(), ['chat_id' => $chatId, 'message' => $message]);
            return false;
        }
    }

    public function handle(Request $request)
    {
        Log::info("BOT_WEBHOOK_RECEIVED", ['ip' => $request->ip()]);
        try {
            $this->settings = Setting::all()->pluck('value', 'key');
            $botToken = $this->settings->get('telegram_bot_token');

            if ($botToken) {
                // پاکسازی کوتیشن‌های احتمالی از توکن
                $botToken = trim($botToken, '"\' ');
            }
            
            if (!$botToken) {
                $botToken = config('telegrambot.bot_token');
                if ($botToken) $botToken = trim($botToken, '"\' ');
            }

            if (!$botToken) {
                Log::warning('Telegram bot token is not set.');
                return response('ok', 200);
            }

            Log::info("BOT_TOKEN_FOUND", ['token_prefix' => substr($botToken, 0, 5)]);
            
            Telegram::setAccessToken($botToken);
            $update = Telegram::getWebhookUpdate();
            
            Log::info("UPDATE_OBJECT_CREATED", [
                'type' => $update->detectType(),
                'has_message' => $update->has('message'),
                'is_callback' => $update->isType('callback_query')
            ]);

            if ($update->isType('callback_query')) {
                Log::info("PROCESSING_CALLBACK_QUERY");
                $this->handleCallbackQuery($update);
            } elseif ($update->has('message')) {
                Log::info("PROCESSING_MESSAGE");
                $message = $update->getMessage();
                if ($message->has('text')) {
                    Log::info("MESSAGE_HAS_TEXT", ['text' => $message->getText()]);
                    $this->handleTextMessage($update);
                } elseif ($message->has('photo')) {
                    Log::info("MESSAGE_HAS_PHOTO");
                    $this->handlePhotoMessage($update);
                }
            } else {
                Log::info("UPDATE_TYPE_NOT_HANDLED", ['type' => $update->detectType()]);
            }
        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/bot_debug.log'), date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            Log::error('Telegram Bot Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500)
            ]);
        }
        
        Log::info("WEBHOOK_FINISHED");
        return response('ok', 200);
    }

    protected function handleTextMessage($update)
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $text = trim($message->getText() ?? '');
        
        Log::info("HTM_START", ['chat_id' => $chatId, 'text' => $text]);
        
        $user = User::where('telegram_chat_id', $chatId)->first();
        Log::info("HTM_USER_CHECK", ['found' => (bool)$user]);

        if ($user) {
            $member = $this->isUserMemberOfChannel($user);
            Log::info("HTM_MEMBERSHIP_CHECK", ['is_member' => $member]);
            if (!$member) {
                Log::info("HTM_MEMBERSHIP_REQUIRED_STOP");
                $this->showChannelRequiredMessage($chatId);
                return;
            }
        }

        if (!$user) {
            Log::info("HTM_CREATING_NEW_USER");
            $userFirstName = $message->getFrom()->getFirstName() ?? 'کاربر';
            $password = Str::random(10);
            $user = User::create([
                'name' => $userFirstName,
                'email' => $chatId . '@telegram.user',
                'password' => Hash::make($password),
                'telegram_chat_id' => $chatId,
                'referral_code' => Str::random(8),
            ]);

            if (!$this->isUserMemberOfChannel($user)) {
                Log::info("HTM_NEW_USER_MEMBERSHIP_REQUIRED_STOP");
                $this->showChannelRequiredMessage($chatId);
                return;
            }

            $telegramSettings = TelegramBotSetting::pluck('value', 'key');
            $welcomeMessage = $telegramSettings->get('welcome_message', "🌟 خوش آمدید {$userFirstName} عزیز!\n\nبرای شروع، یکی از گزینه‌های منو را انتخاب کنید:");
            $welcomeMessage = str_replace('{userFirstName}', $userFirstName, $welcomeMessage);

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

            Log::info("HTM_SENDING_WELCOME");
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $welcomeMessage,
                'reply_markup' => $this->getReplyMainMenu()
            ]);
            return;
        }

        if ($user->bot_state) {
            Log::info("HTM_BOT_STATE_ACTIVE", ['state' => $user->bot_state]);
            
            // اگر کاربر /start فرستاد، وضعیت را ریست کن تا از بن‌بست خارج شود
            if ($text === '/start') {
                Log::info("HTM_RESETTING_STATE_BY_START");
                $user->update(['bot_state' => null]);
            } 
            else {
                if ($user->bot_state === 'awaiting_deposit_amount') {
                    $this->processDepositAmount($user, $text);
                    return;
                } elseif (Str::startsWith($user->bot_state, 'awaiting_new_ticket_') || Str::startsWith($user->bot_state, 'awaiting_ticket_reply')) {
                    $this->processTicketConversation($user, $text, $update);
                    return;
                } elseif (Str::startsWith($user->bot_state, 'awaiting_discount_code|')) {
                    $orderId = Str::after($user->bot_state, 'awaiting_discount_code|');
                    $this->processDiscountCode($user, $orderId, $text);
                    return;
                } elseif (Str::startsWith($user->bot_state, 'awaiting_username_for_order|')) {
                    $planId = Str::after($user->bot_state, 'awaiting_username_for_order|');
                    $this->processUsername($user, $planId, $text);
                    return;
                } elseif (Str::startsWith($user->bot_state, 'waiting_receipt_')) {
                    Log::info("HTM_WAITING_RECEIPT_FEEDBACK");
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $this->escape("⚠️ شما در مرحله ارسال فیش واریزی هستید.\n\n📸 لطفاً تصویر رسید خود را ارسال کنید.\n❌ اگر قصد لغو دارید، دستور /start را بفرستید."),
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    return;
                }
                
                Log::info("HTM_BOT_STATE_UNKNOWN_STOP");
                return;
            }
        }

        // نرمال‌سازی متن برای دکمه‌هایی که ممکن است نیم‌فاصله داشته باشند یا نداشته باشند
        $normalizedText = str_replace(['‌', ' '], '', $text);

        Log::info("HTM_SWITCH_START", ['normalized' => $normalizedText]);

        if ($text === '🛒 خرید سرویس') {
            $this->sendPlans($chatId);
        } elseif ($normalizedText === '🛠سرویسهایمن' || $normalizedText === '🛠سرویس‌هایمن') {
            $this->sendMyServices($user);
        } elseif ($text === '💰 کیف پول') {
            $this->sendWalletMenu($user);
        } elseif ($text === '📜 تاریخچه تراکنش‌ها' || $normalizedText === '📜تاریخچهتراکنشها') {
            $this->sendTransactions($user);
        } elseif ($text === '💬 پشتیبانی') {
            $this->showSupportMenu($user);
        } elseif ($text === '🎁 دعوت از دوستان') {
            $this->sendReferralMenu($user);
        } elseif ($text === '📚 راهنمای اتصال') {
            $this->sendTutorialsMenu($chatId);
        } elseif ($text === '🧪 اکانت تست') {
            $telegramUsername = $message->getFrom()->getUsername();
            $this->handleTrialRequest($user, $telegramUsername);
        } elseif ($text === '/start') {
            Log::info("HTM_HANDLING_START_COMMAND");
            $telegramSettings = TelegramBotSetting::pluck('value', 'key');
            $startMessage = $telegramSettings->get('start_message', 'سلام مجدد! لطفاً یک گزینه را انتخاب کنید:');
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape($startMessage),
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $this->getReplyMainMenu()
            ]);
            Log::info("HTM_START_COMMAND_FINISHED");
        } else {
            Log::info("HTM_DEFAULT_CASE");
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'دستور شما نامفهوم است. لطفاً از دکمه‌های منو استفاده کنید.',
                'reply_markup' => $this->getReplyMainMenu()
            ]);
        }
    }

    protected function processUsername($user, $planId, $username)
    {
        $username = trim($username);

        if (strlen($username) < 3) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ نام کاربری باید حداقل ۳ کاراکتر باشد."),
                'parse_mode' => 'MarkdownV2'
            ]);
            $this->promptForUsername($user, $planId);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ نام کاربری فقط می‌تواند شامل حروف انگلیسی و اعداد باشد."),
                'parse_mode' => 'MarkdownV2'
            ]);
            $this->promptForUsername($user, $planId);
            return;
        }

        // بررسی یکتا بودن نام کاربری (فقط در سفارش‌های پرداخت شده)
        $existingOrder = Order::where('panel_username', $username)->where('status', 'paid')->first();
        if ($existingOrder) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ این نام کاربری قبلاً استفاده شده است. لطفاً نام دیگری وارد کنید."),
                'parse_mode' => 'MarkdownV2'
            ]);
            $this->promptForUsername($user, $planId);
            return;
        }

        $this->startPurchaseProcess($user, $planId, $username);
    }

    protected function promptForUsername($user, $planId, $messageId = null, $locationId = null)
    {
        $newState = 'awaiting_username_for_order|' . $planId;

        if ($locationId) {
            $newState .= '|selected_loc:' . $locationId;
        }
        elseif ($user->bot_state && Str::contains($user->bot_state, 'selected_loc:')) {
            $parts = explode('|', $user->bot_state);
            foreach ($parts as $part) {
                if (Str::startsWith($part, 'selected_loc:')) {
                    $newState .= '|' . $part;
                    break;
                }
            }
        }

        $user->update(['bot_state' => $newState]);

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action'])]);
        
        $message = "👤 *انتخاب نام کاربری سرویس*\n\n";
        $message .= $this->escape("لطفاً یک نام کاربری انگلیسی برای سرویس خود وارد کنید.") . "\n";
        $message .= $this->escape("🔹 فقط حروف انگلیسی و اعداد مجاز است (حداقل ۳ حرف).") . "\n";
        $message .= $this->escape("🔹 مثال:") . " `arvin123` " . $this->escape("یا") . " `myvpn`";

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    /**
     * ارسال مجدد لینک اکانت تست (برای کپی آسان)
     */
    protected function handleTrialCopyLink($user, $messageId = null)
    {
        try {
            $link = \Illuminate\Support\Facades\Cache::get("trial_link_{$user->id}");

            if (!$link) {
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => $this->escape("❌ لینک اکانت تست منقضی شده یا یافت نشد.\nلطفاً اکانت تست جدیدی دریافت کنید."),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => Keyboard::make()->inline()->row([
                        Keyboard::inlineButton(['text' => '🧪 دریافت اکانت تست', 'callback_data' => 'trial_request'])
                    ])
                ]);
                return;
            }

            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => "📋 *لینک اکانت تست شما:*\n\n`{$link}`\n\n" . $this->escape("روی لینک بالا کلیک کنید تا کپی شود."),
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => Keyboard::make()->inline()->row([
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت به منو', 'callback_data' => '/start'])
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Trial copy link error: ' . $e->getMessage());
        }
    }

    /**
     * ارسال QR Code برای اکانت تست
     */
    protected function sendTrialQRCode($user, $messageId = null)
    {
        try {
            $link = \Illuminate\Support\Facades\Cache::get("trial_link_{$user->id}");

            if (!$link) {
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => $this->escape("❌ لینک اکانت تست منقضی شده."),
                    'parse_mode' => 'MarkdownV2'
                ]);
                return;
            }

            $tempFile = null;
            try {
                $qrParams = [
                    'size' => '400x400',
                    'data' => $link,
                    'ecc' => 'M',
                    'margin' => 10,
                    'format' => 'png'
                ];

                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query($qrParams);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $qrUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 30
                ]);

                $qrData = curl_exec($ch);
                curl_close($ch);

                if (!$qrData) throw new \Exception("QR generation failed");

                $tempDir = storage_path('app/temp');
                if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);

                $tempFile = $tempDir . '/qr_trial_' . $user->id . '_' . time() . '.png';
                file_put_contents($tempFile, $qrData);

                Telegram::sendPhoto([
                    'chat_id' => $user->telegram_chat_id,
                    'photo' => InputFile::create($tempFile),
                    'caption' => $this->escape("📱 QR Code اکانت تست\n\nلینک:\n`{$link}`"),
                    'parse_mode' => 'MarkdownV2'
                ]);

            } finally {
                if ($tempFile && file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }

        } catch (\Exception $e) {
            Log::error('Trial QR error: ' . $e->getMessage());
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ خطا در ساخت QR Code"),
                'parse_mode' => 'MarkdownV2'
            ]);
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
            return;
        }



        if (!$user) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ کاربر یافت نشد. لطفاً با دستور /start ربات را مجدداً راه‌اندازی کنید."), 'parse_mode' => 'MarkdownV2']);
            return;
        }

        try {
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
        } catch (\Exception $e) { Log::warning('Could not answer callback query: ' . $e->getMessage()); }

        if (!Str::startsWith($data, ['/deposit_custom', '/support_new', 'reply_ticket_', 'enter_discount_'])) {
            $user->update(['bot_state' => null]);
        }

        if (Str::startsWith($data, 'select_loc_')) {
            $parts = explode('_', $data);

            if (count($parts) >= 5) {
                $locationId = $parts[2];
                $planId = $parts[4];

                if (class_exists('Modules\MultiServer\Models\Location')) {
                    $location = \Modules\MultiServer\Models\Location::find($locationId);
                    if ($location) {
                        $totalCapacity = $location->servers()->where('is_active', true)->sum('capacity');
                        $totalUsed = $location->servers()->where('is_active', true)->sum('current_users');

                        if ($totalUsed >= $totalCapacity) {
                            $settings = Setting::all()->pluck('value', 'key');
                            $msg = $settings->get('ms_full_location_message') ?? "❌ ظرفیت تکمیل است.";

                            Telegram::answerCallbackQuery([
                                'callback_query_id' => $callbackQuery->getId(),
                                'text' => $msg,
                                'show_alert' => true
                            ]);
                            return;
                        }
                    }
                }
                $this->promptForUsername($user, $planId, $messageId, $locationId);
                return;
            }
        }

        if (Str::startsWith($data, 'buy_plan_')) {
            $planId = Str::after($data, 'buy_plan_');

            $isMultiLocationEnabled = filter_var(
                $this->settings->get('enable_multilocation', false),
                FILTER_VALIDATE_BOOLEAN
            );

            if ($isMultiLocationEnabled && class_exists('Modules\MultiServer\Models\Location')) {
                $this->promptForLocation($user, $planId, $messageId);
                return;
            }

            $this->promptForUsername($user, $planId, $messageId);
            return;
        }
        elseif ($data === 'trial_request') {
            $telegramUsername = $callbackQuery->getFrom()->getUsername();
            $this->handleTrialRequest($user, $telegramUsername);
            return;
        }
        elseif (Str::startsWith($data, 'pay_wallet_')) {
            $input = Str::after($data, 'pay_wallet_');
            $this->processWalletPayment($user, $input, $messageId);
        } elseif (Str::startsWith($data, 'pay_card_')) {
            $orderId = Str::after($data, 'pay_card_');
            $this->sendCardPaymentInfo($chatId, $orderId, $messageId);
        }

        elseif (Str::startsWith($data, 'show_service_')) {
             $orderId = Str::after($data, 'show_service_');
             // اگر کاربر روی سرویس کلیک کرد، مستقیماً QR را نشان بده
             $this->showServiceDetailsWithQR($user, $orderId, $messageId);
        }

        elseif (Str::startsWith($data, 'copy_trial_link_')) {
            $userId = Str::after($data, 'copy_trial_link_');
            $this->handleTrialCopyLink($user, $messageId);
        }
        elseif (Str::startsWith($data, 'qr_trial_')) {
            $this->sendTrialQRCode($user, $messageId);
        }

        elseif (Str::startsWith($data, 'enter_discount_')) {
            $orderId = Str::after($data, 'enter_discount_');
            $this->promptForDiscount($user, $orderId, $messageId);
        }
        elseif (Str::startsWith($data, 'copy_link_')) {
            $orderId = Str::after($data, 'copy_link_');
            $this->handleCopyLinkRequest($user, $orderId);
        }

        elseif (Str::startsWith($data, 'remove_discount_')) {
            $orderId = Str::after($data, 'remove_discount_');
            $this->removeDiscount($user, $orderId, $messageId);
        } elseif (Str::startsWith($data, 'qrcode_order_')) {
            $orderId = Str::after($data, 'qrcode_order_');
            $this->sendQRCodeForOrder($user, $orderId);
        } elseif (Str::startsWith($data, 'renew_order_')) {
            $originalOrderId = Str::after($data, 'renew_order_');
            $this->startRenewalPurchaseProcess($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'renew_pay_wallet_')) {
            $originalOrderId = Str::after($data, 'renew_pay_wallet_');
            $this->processRenewalWalletPayment($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'renew_pay_card_')) {
            $originalOrderId = Str::after($data, 'renew_pay_card_');
            $this->handleRenewCardPayment($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'deposit_amount_')) {
            $amount = Str::after($data, 'deposit_amount_');
            $this->processDepositAmount($user, $amount, $messageId);
        } elseif ($data === '/deposit_custom') {
            $this->promptForCustomDeposit($user, $messageId);
        } elseif (Str::startsWith($data, 'close_ticket_')) {
            $ticketId = Str::after($data, 'close_ticket_');
            $this->closeTicket($user, $ticketId, $messageId, $callbackQuery->getId());
        } elseif (Str::startsWith($data, 'reply_ticket_')) {
            $ticketId = Str::after($data, 'reply_ticket_');
            $this->promptForTicketReply($user, $ticketId, $messageId);
        } elseif ($data === '/support_new') {
            $this->promptForNewTicket($user, $messageId);
        } else {
            switch ($data) {
                case '/start':
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => '🌟 منوی اصلی',
                        'reply_markup' => $this->getReplyMainMenu()
                    ]);
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
                    try { Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]); } catch (\Exception $e) {}
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

    protected function promptForLocation($user, $planId, $messageId)
    {
        $settings = Setting::all()->pluck('value', 'key');
        $showCapacity = filter_var($settings->get('ms_show_capacity', true), FILTER_VALIDATE_BOOLEAN);
        $hideFull = filter_var($settings->get('ms_hide_full_locations', false), FILTER_VALIDATE_BOOLEAN);

        $locations = \Modules\MultiServer\Models\Location::where('is_active', true)->with('servers')->get();

        $keyboard = Keyboard::make()->inline();
        $hasAvailableLocation = false;

        foreach ($locations as $loc) {
            $totalCapacity = $loc->servers->where('is_active', true)->sum('capacity');
            $totalUsed = $loc->servers->where('is_active', true)->sum('current_users');
            $remained = max(0, $totalCapacity - $totalUsed);
            $isFull = $remained <= 0;

            if ($isFull && $hideFull) {
                continue;
            }

            $hasAvailableLocation = true;
            $flag = $loc->flag ?? '🏳️';
            $btnText = "$flag {$loc->name}";

            if ($isFull) {
                $btnText .= " (تکمیل 🔒)";
            } elseif ($showCapacity) {
                $btnText .= " ({$remained} عدد)";
            }

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $btnText,
                    'callback_data' => "select_loc_{$loc->id}_plan_{$planId}"
                ])
            ]);
        }

        if (!$hasAvailableLocation) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ متأسفانه ظرفیت تمام سرورها تکمیل شده است."),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, "🌍 *انتخاب لوکیشن*\n\nلطفاً کشور مورد نظر خود را انتخاب کنید:", $keyboard, $messageId);
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

        if (!$user || !$user->bot_state) {
            $this->sendOrEditMainMenu($chatId, "❌ لطفاً ابتدا یک عملیات (مانند ثبت تیکت یا رسید) را شروع کنید.");
            return;
        }

        if (Str::startsWith($user->bot_state, 'awaiting_ticket_reply|') || Str::startsWith($user->bot_state, 'awaiting_new_ticket_message|')) {
            $text = $message->getCaption() ?? '[📎 فایل پیوست شد]';
            $this->processTicketConversation($user, $text, $update);
            return;
        }

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
                    $this->sendOrEditMainMenu($chatId, $this->escape("چه کار دیگری برایتان انجام دهم?"));

                    $adminChatId = $this->settings->get('telegram_admin_chat_id');
                    if ($adminChatId && is_numeric($adminChatId)) {
                        $orderType = $order->renews_order_id ? 'تمدید سرویس' : ($order->plan_id ? 'خرید سرویس' : 'شارژ کیف پول');

                        $adminMessage = "🧾 *رسید جدید برای سفارش \\#{$orderId}*\n\n";
                        $adminMessage .= "*کاربر:* " . $this->escape($user->name) . " \\(ID: `{$user->id}`\\)\n";
                        $adminMessage .= "*مبلغ:* " . $this->escape(number_format($order->amount) . ' تومان') . "\n";
                        $adminMessage .= "*نوع سفارش:* " . $this->escape($orderType) . "\n\n";
                        $adminMessage .= $this->escape("لطفا در پنل مدیریت بررسی و تایید کنید.");

                        try {
                            Telegram::sendPhoto([
                                'chat_id' => $adminChatId,
                                'photo' => InputFile::create(Storage::disk('public')->path($fileName)),
                                'caption' => $adminMessage,
                                'parse_mode' => 'MarkdownV2'
                            ]);
                        } catch (\Exception $e) {
                             Log::error("Failed to send receipt to admin: " . $e->getMessage());
                             // Silent failure for admin notification, user flow should continue
                        }
                    }

                } catch (\Exception $e) {
                    Log::error("Receipt processing failed for order {$orderId}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ خطا در پردازش رسید. لطفاً دوباره تلاش کنید."), 'parse_mode' => 'MarkdownV2']);
                    $this->sendOrEditMainMenu($chatId, $this->escape("لطفا دوباره تلاش کنید."));
                }
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ سفارش نامعتبر است یا در انتظار پرداخت نیست."), 'parse_mode' => 'MarkdownV2']);
                $this->sendOrEditMainMenu($chatId, $this->escape("لطفا وضعیت سفارش خود را بررسی کنید."));
            }
        }
    }

    // ========================================================================
    // 🛒 سیستم خرید و تخفیف
    // ========================================================================

    protected function startPurchaseProcess($user, $planId, $username, $messageId = null)
    {
        $plan = Plan::find($planId);
        if (!$plan) {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ پلن مورد نظر یافت نشد.", $messageId);
            return;
        }

        $serverId = null;
        $isMultiLocationEnabled = filter_var(
            $this->settings->get('enable_multilocation', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($user->bot_state && Str::contains($user->bot_state, 'selected_loc:')) {
            preg_match('/selected_loc:(\d+)/', $user->bot_state, $matches);
            if (!empty($matches[1])) {
                $locationId = (int) $matches[1];
            } else {
                $locationId = null;
            }

            if ($locationId) {
                // پیدا کردن خلوت‌ترین سرور فعال
                $bestServer = \Modules\MultiServer\Models\Server::where('location_id', $locationId)
                    ->where('is_active', true)
                    ->whereRaw('current_users < capacity')
                    ->orderBy('current_users', 'asc')
                    ->first();

                if ($bestServer) {
                    $serverId = $bestServer->id;
                } else {
                    $user->update(['bot_state' => null]);
                    Telegram::sendMessage([
                        'chat_id' => $user->telegram_chat_id,
                        'text' => $this->escape("❌ متأسفانه ظرفیت سرورهای این لوکیشن تکمیل شده است."),
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    return;
                }
            }
        }

        $order = $user->orders()->create([
            'plan_id' => $plan->id,
            'server_id' => $serverId,
            'status' => 'pending',
            'source' => 'telegram',
            'amount' => $plan->price,
            'discount_amount' => 0,
            'discount_code_id' => null,
            'panel_username' => $username
        ]);

        $user->update(['bot_state' => null]);
        $this->showInvoice($user, $order, $messageId);
    }

    protected function showInvoice($user, Order $order, $messageId = null)
    {
        $plan = $order->plan;
        $balance = $user->balance ?? 0;

        $message = "🛒 *تایید خرید*\n\n";
        $message .= "▫️ پلن: *{$this->escape($plan->name)}*\n";

        if ($order->discount_amount > 0) {
            $originalPrice = number_format($plan->price);
            $finalPrice = number_format($order->amount);
            $discount = number_format($order->discount_amount);
            $message .= "▫️ قیمت اصلی: ~*{$originalPrice} تومان*~\n";
            $message .= "🎉 *قیمت با تخفیف:* *{$finalPrice} تومان*\n";
            $message .= "💰 سود شما: *{$discount} تومان*\n";
        } else {
            $message .= "▫️ قیمت: *" . number_format($order->amount) . " تومان*\n";
        }

        $message .= "▫️ موجودی کیف پول: *" . number_format($balance) . " تومان*\n\n";
        $message .= "لطفاً روش پرداخت را انتخاب کنید:";

        $keyboard = Keyboard::make()->inline();

        if (!$order->discount_code_id) {
            $keyboard->row([Keyboard::inlineButton(['text' => '🎫 ثبت کد تخفیف', 'callback_data' => "enter_discount_{$order->id}"])]);
        } else {
            $keyboard->row([Keyboard::inlineButton(['text' => '❌ حذف کد تخفیف', 'callback_data' => "remove_discount_{$order->id}"])]);
        }

        if ($balance >= $order->amount) {
            $keyboard->row([Keyboard::inlineButton(['text' => '✅ پرداخت با کیف پول', 'callback_data' => "pay_wallet_order_{$order->id}"])]); // ✅ اصلاح: فرمت callback_data یکسان شد
        }
        $keyboard->row([Keyboard::inlineButton(['text' => '💳 کارت به کارت', 'callback_data' => "pay_card_{$order->id}"])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به پلن‌ها', 'callback_data' => '/plans'])]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function promptForDiscount($user, $orderId, $messageId)
    {
        $user->update(['bot_state' => 'awaiting_discount_code|' . $orderId]);
        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, "🎫 لطفاً کد تخفیف خود را ارسال کنید:", $keyboard, $messageId);
    }

    protected function processDiscountCode($user, $orderId, $codeText)
    {
        $order = Order::find($orderId);
        if (!$order || $order->status !== 'pending') {
            $user->update(['bot_state' => null]);
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سفارش منقضی شده است.");
            return;
        }

        $code = DiscountCode::where('code', $codeText)->first();
        $error = null;

        if (!$code) $error = '❌ کد تخفیف نامعتبر است.';
        elseif (!$code->is_active) $error = '❌ کد تخفیف غیرفعال است.';
        elseif ($code->starts_at && $code->starts_at > now()) $error = '❌ زمان استفاده از کد نرسیده است.';
        elseif ($code->expires_at && $code->expires_at < now()) $error = '❌ کد تخفیف منقضی شده است.';
        else {
            $totalAmount = $order->plan_id ? $order->plan->price : $order->amount;
            // ⚠️ نکته: اطمینان حاصل کنید که مدل DiscountCode متدهای isValidForOrder و calculateDiscount را دارد
            if (!$code->isValidForOrder($totalAmount, $order->plan_id, !$order->plan_id, (bool)$order->renews_order_id)) {
                $error = '❌ کد تخفیف شامل شرایط این سفارش نمی‌شود.';
            }
        }

        if ($error) {
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $this->escape($error), 'parse_mode' => 'MarkdownV2']);
            return;
        }

        $discountAmount = $code->calculateDiscount($order->plan->price ?? $order->amount);
        $finalAmount = ($order->plan->price ?? $order->amount) - $discountAmount;

        $order->update([
            'discount_amount' => $discountAmount,
            'discount_code_id' => $code->id,
            'amount' => $finalAmount
        ]);

        $user->update(['bot_state' => null]);
        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $this->escape("✅ کد تخفیف اعمال شد!"), 'parse_mode' => 'MarkdownV2']);
        $this->showInvoice($user, $order);
    }

    protected function removeDiscount($user, $orderId, $messageId)
    {
        $order = Order::find($orderId);
        if ($order && $order->status === 'pending') {
            $originalPrice = $order->plan->price ?? ($order->amount + $order->discount_amount);
            $order->update([
                'discount_amount' => 0,
                'discount_code_id' => null,
                'amount' => $originalPrice
            ]);
            $this->showInvoice($user, $order, $messageId);
        }
    }


    protected function processWalletPayment($user, $input, $messageId)
    {
        $order = null;
        $plan = null;

        try {
            DB::transaction(function () use ($user, $input, &$order, &$plan) { // ✅ اضافه کردن &
                // 🔒 قفل کردن رکورد کاربر برای جلوگیری از دسترسی همزمان
                $lockedUser = User::lockForUpdate()->find($user->id);

                if (!$lockedUser) {
                    throw new \Exception('User not found');
                }

                // تشخیص سفارش موجود یا ساخت سفارش جدید
                if (Str::startsWith($input, 'order_')) {
                    $orderId = Str::after($input, 'order_');
                    $order = Order::where('id', $orderId)
                        ->where('user_id', $lockedUser->id)
                        ->where('status', 'pending')
                        ->first();

                    if (!$order) {
                        throw new \Exception('سفارش نامعتبر است یا منقضی شده.');
                    }

                    $plan = $order->plan;
                } else {
                    $planId = $input;
                    $plan = Plan::find($planId);

                    if (!$plan) {
                        throw new \Exception('پلن مورد نظر یافت نشد.');
                    }

                    // ساخت سفارش داخل تراکنش
                    $order = $lockedUser->orders()->create([
                        'plan_id' => $plan->id,
                        'status' => 'pending',
                        'source' => 'telegram',
                        'amount' => $plan->price,
                        'discount_amount' => 0,
                        'discount_code_id' => null,
                    ]);
                }

                // ✅ بررسی موجودی داخل تراکنش (با رکورد قفل شده)
                if ($lockedUser->balance < $order->amount) {
                    throw new \Exception('موجودی کافی نیست');
                }

                // کسر موجودی (Atomic)
                $lockedUser->decrement('balance', $order->amount);

                // بروزرسانی سفارش به پرداخت شده
                $order->update([
                    'status' => 'paid',
                    'payment_method' => 'wallet',
                    'expires_at' => now()->addDays($plan->duration_days)
                ]);

                // ثبت استفاده از کد تخفیف
                if ($order->discount_code_id) {
                    $dc = DiscountCode::lockForUpdate()->find($order->discount_code_id);
                    if ($dc) {
                        DiscountCodeUsage::create([
                            'discount_code_id' => $dc->id,
                            'user_id' => $lockedUser->id,
                            'order_id' => $order->id,
                            'discount_amount' => $order->discount_amount,
                            'original_amount' => $plan->price
                        ]);
                        $dc->increment('used_count');
                    }
                }

                // ثبت تراکنش مالی
                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'amount' => -$order->amount,
                    'type' => 'purchase',
                    'status' => 'completed',
                    'description' => "خرید سرویس {$plan->name} از طریق کیف پول"
                ]);

                // ساخت اکانت در پنل (X-UI یا Marzban)
                $provisionData = $this->provisionUserAccount($order, $plan);

                if ($provisionData && $provisionData['link']) {
                    $order->update([
                        'config_details' => $provisionData['link'],
                        'panel_username' => $provisionData['username'],
                        'panel_client_id' => $provisionData['panel_client_id'] ?? null,
                        'panel_sub_id' => $provisionData['panel_sub_id'] ?? null,
                    ]);
                } else {
                    throw new \Exception('خطا در ایجاد کانفیگ در پنل. لطفاً با پشتیبانی تماس بگیرید.');
                }
            });

            // ارسال پیام موفقیت (خارج از تراکنش)
            // ✅ اصلاح: حالا $order و $plan در دسترس هستند چون با & پاس شده‌اند
            $link = $order->config_details;

            // بارگذاری اطلاعات کامل سفارش
            $order->load(['server.location', 'plan']);

            // آماده‌سازی اطلاعات سرور و کشور
            $serverName = 'سرور اصلی';
            $locationFlag = '🏳️';
            $locationName = 'نامشخص';

            if ($order->server) {
                $serverName = $order->server->name;
                if ($order->server->location) {
                    $locationFlag = $order->server->location->flag ?? '🏳️';
                    $locationName = $order->server->location->name;
                }
            } elseif ($this->settings->get('panel_type') === 'pasargad') {
                $serverName = 'PasarGuard Eagle';
                $locationName = 'سرویس Eagle';
                $locationFlag = '🦅';
            }

            // ساخت پیام کامل با ظاهر پریمیوم
            $message = "🔑 *اشتراک شما با موفقیت فعال شد*\n\n";
            $message .= "📦 *پلن:* `{$this->escape($order->plan->name)}`\n";
            $message .= "🌍 *موقعیت:* {$locationFlag} {$this->escape($locationName)}\n";
            $message .= "👤 *نام کاربری:* `{$order->panel_username}`\n";
            $message .= "💾 *حجم:* {$order->plan->volume_gb} گیگابایت\n";
            $message .= "⏳ *انقضا:* `{$order->expires_at->format('Y/m/d H:i')}`\n\n";
            $message .= "🔗 *لینک اشتراک اختصاصی:*\n";
            $message .= "`{$link}`\n\n";
            $message .= "👆🏻 " . $this->escape("برای کپی روی لینک بالا بزنید!") . "\n\n";
            $message .= $this->escape("⚠️ توصیه می‌شود از اپلیکیشن رسمی پیشنهادی استفاده کنید.");

            // کیبورد با دکمه کپی لینک
            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => '📋 کپی لینک اشتراک', 'callback_data' => "copy_link_{$order->id}"]),
                    Keyboard::inlineButton(['text' => '📱 QR Code', 'callback_data' => "qrcode_order_{$order->id}"])
                ])
                ->row([
                    Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
                    Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
                ]);

            $this->sendOrEditMessage(
                $user->telegram_chat_id,
                $message,
                $keyboard,
                $messageId
            );

        } catch (\Exception $e) {
            Log::error('Wallet Payment Failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'input' => $input,
                'trace' => $e->getTraceAsString()
            ]);

            $errorMsg = $e->getMessage();
            $keyboard = Keyboard::make()->inline();

            // تشخیص نوع خطا و نمایش پیام مناسب
            if ($errorMsg === 'موجودی کافی نیست') {
                $keyboard->row([
                    Keyboard::inlineButton(['text' => '💳 شارژ کیف پول', 'callback_data' => '/deposit']),
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/plans'])
                ]);
                $this->sendOrEditMessage(
                    $user->telegram_chat_id,
                    "❌ موجودی کیف پول شما کافی نیست.\n\n💡 لطفاً ابتدا کیف پول خود را شارژ کنید.",
                    $keyboard,
                    $messageId
                );
            } elseif ($errorMsg === 'سفارش نامعتبر است یا منقضی شده.') {
                $keyboard->row([Keyboard::inlineButton(['text' => '🛒 مشاهده پلن‌ها', 'callback_data' => '/plans'])]);
                $this->sendOrEditMessage(
                    $user->telegram_chat_id,
                    "❌ " . $errorMsg,
                    $keyboard,
                    $messageId
                );
            } else {
                // خطای عمومی یا خطای پر کردن اکانت
                $keyboard->row([Keyboard::inlineButton(['text' => '💬 تماس با پشتیبانی', 'callback_data' => '/support_menu'])]);
                $this->sendOrEditMessage(
                    $user->telegram_chat_id,
                    "⚠️ خطایی در پردازش خرید رخ داد: " . $this->escape($errorMsg) . "\n\nلطفاً با پشتیبانی تماس بگیرید.",
                    $keyboard,
                    $messageId
                );
            }
        }
    }

    protected function sendCardPaymentInfo($chatId, $orderId, $messageId)
    {
        $order = Order::find($orderId);
        if (!$order->server_id) {

            $user = $order->user;
            if ($user->bot_state && Str::contains($user->bot_state, 'selected_loc:')) {
                preg_match('/selected_loc:(\d+)/', $user->bot_state, $matches);
                if (!empty($matches[1])) {
                    $locationId = (int) $matches[1];


                    if (class_exists('Modules\MultiServer\Models\Server')) {
                        $bestServer = \Modules\MultiServer\Models\Server::where('location_id', $locationId)
                            ->where('is_active', true)
                            ->whereRaw('current_users < capacity')
                            ->orderBy('current_users', 'asc')
                            ->first();

                        if ($bestServer) {
                            $order->update(['server_id' => $bestServer->id]);
                        }
                    }
                }
            }
        }

        $user = $order->user;
        $user->update(['bot_state' => 'waiting_receipt_' . $orderId]);
        $user = $order->user;
        $user->update(['bot_state' => 'waiting_receipt_' . $orderId]);

        $cardNumber = $this->settings->get('payment_card_number', 'شماره کارتی تنظیم نشده');
        $cardHolder = $this->settings->get('payment_card_holder_name', 'صاحب حسابی تنظیم نشده');
        $amountToPay = number_format($order->amount);

        $message = "💳 *پرداخت کارت به کارت*\n\n";
        $message .= "لطفاً مبلغ *" . $this->escape($amountToPay) . " تومان* را به حساب زیر واریز نمایید:\n\n";
        $message .= "👤 *به نام:* " . $this->escape($cardHolder) . "\n";
        $message .= "💳 *شماره کارت:*\n`" . $this->escape($cardNumber) . "`\n\n";
        $message .= "🔔 *مهم:* پس از واریز، *فقط عکس رسید* را در همین چت ارسال کنید\\.";

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '❌ انصراف از پرداخت', 'callback_data' => '/cancel_action'])]);

        $this->sendRawMarkdownMessage($chatId, $message, $keyboard, $messageId);
    }

    // ========================================================================
    // سایر متدها (پلان‌ها، تمدید، تیکت، آموزش و ...)
    // ========================================================================

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

            $durations = $activePlans->pluck('duration_days')->unique()->sort();

            $message = "🛒 *انتخاب سرویس VPN*\n";
            $message .= "━━━━━━━━━━━━━━━\n\n";
            $message .= "لطفاً مدت‌زمان سرویس مورد نظر را انتخاب کنید:\n\n";
            $message .= "👇 یکی از گزینه‌های زیر را انتخاب کنید:";

            $keyboard = Keyboard::make()->inline();

            foreach ($durations as $durationDays) {
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
        if ($days % 30 === 0) {
            $months = $days / 30;
            return match ($months) {
                1 => '📅 یک ماهه',
                2 => '📅 دو ماهه',
                3 => '📅 سه ماهه',
                6 => '📅 شش ماهه',
                12 => '📅 یک ساله',
                default => "📅 {$months} ماهه",
            };
        }
        return "📅 {$days} روزه";
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

            $durationLabel = $plans->first()->duration_label ?? "{$durationDays} روزه";
            $message = "💎 *سرویس‌های {$durationLabel}*\n";
            $message .= "━━━━━━━━━━━━━━━\n\n";

            foreach ($plans as $index => $plan) {
                $statusIcon = '✅';
                $message .= "{$statusIcon} *" . $this->escape($plan->name) . "*\n";
                $message .= "   📦 " . $this->escape($plan->volume_gb . ' گیگابایت') . "\n";
                $message .= "   💳 " . $this->escape(number_format($plan->price) . ' تومان') . "\n";
                $message .= "┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄\n";
            }
            $message .= "\n👇 پلن مورد نظر را انتخاب کنید:";

            $keyboard = Keyboard::make()->inline();

            foreach ($plans as $plan) {
                // ✅ اصلاح: حذف escape از نام دکمه چون دکمه‌ها plain text هستند
                $buttonText = $plan->name . ' | ' . number_format($plan->price) . ' تومان';
                $keyboard->row([
                    Keyboard::inlineButton([
                        'text' => $buttonText,
                        'callback_data' => "buy_plan_{$plan->id}"
                    ])
                ]);
            }

            $keyboard->row([
                Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/plans']),
                Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
            ]);

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

        if (empty($order->config_details) || !is_string($order->config_details)) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ لینک کانفیگ هنوز آماده نشده است."),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        $configLink = trim($order->config_details);

        // ✅ اعتبارسنجی فرمت لینک
        if (empty($configLink)) {
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ لینک کانفیگ خالی است."),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        $tempFile = null;

        try {

            $qrParams = [
                'size' => '400x400',
                'data' => $configLink,
                'ecc' => 'M',
                'margin' => 10,
                'color' => '000000',
                'bgcolor' => 'FFFFFF',
                'format' => 'png'
            ];

            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query($qrParams);


            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $qrUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TelegramBot/1.0)'
            ]);

            $qrData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($qrData === false || $httpCode !== 200 || empty($qrData)) {
                throw new \Exception("HTTP {$httpCode} - {$curlError}");
            }


            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempFile = $tempDir . '/qr_' . $order->id . '_' . time() . '.png';

            if (file_put_contents($tempFile, $qrData) === false) {
                throw new \Exception("عدم توانایی در ذخیره فایل موقت");
            }

            // ✅ ساخت کیبورد
            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => '🔄 تمدید سرویس', 'callback_data' => "renew_order_{$order->id}"]),
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت به جزئیات', 'callback_data' => "show_service_{$order->id}"])
                ])
                ->row([
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت به لیست سرویس‌ها', 'callback_data' => '/my_services'])
                ]);

            // ✅ ارسال عکس با InputFile (Premium Look)
            $photoCaption = "🔑 *📱 QR Code اشتراک \\#{$order->id}*\n\n";
            $photoCaption .= "👤 *نام کاربری:* `" . $this->escapeCode($order->panel_username) . "`\n";
            $photoCaption .= "🔗 *لینک اشتراک:*\n";
            $photoCaption .= "`" . $this->escapeCode($configLink) . "`\n\n";
            $photoCaption .= "👆🏻 " . $this->escape("برای کپی سریع روی لینک بالا بزنید!") . "\n\n";
            $photoCaption .= $this->escape("⚠️ این کد را در اپلیکیشن خود اسکن یا لینک را وارد کنید.");

            Telegram::sendPhoto([
                'chat_id' => $user->telegram_chat_id,
                'photo' => InputFile::create($tempFile, "qr_code_{$order->id}.png"),
                'caption' => $photoCaption,
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $keyboard
            ]);

        } catch (\Exception $e) {
            Log::error('QR Code Generation Failed', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'config_length' => strlen($configLink ?? ''),
                'trace' => $e->getTraceAsString()
            ]);


            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => '🔄 تمدید سرویس', 'callback_data' => "renew_order_{$order->id}"]),
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => "show_service_{$order->id}"])
                ]);

            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ خطا در تولید QR Code.\n\n🔧 لطفاً از لینک زیر استفاده کنید:") . "\n`" . $this->escapeCode($configLink) . "`",
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $keyboard
            ]);

        } finally {

            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
    protected function sendMyServices($user, $messageId = null)
    {
        // فقط سفارش‌هایی که plan_id دارند را بگیر
        $orders = $user->orders()
            ->where('status', 'paid')
            ->whereNotNull('plan_id')
            ->with('plan')
            ->get();
        
        Log::info("SERVICES_QUERY", [
            'user_id' => $user->id,
            'total_paid_orders' => $user->orders()->where('status', 'paid')->count(),
            'orders_with_plan' => $orders->count(),
            'order_ids' => $orders->pluck('id')->toArray(),
            'plan_ids' => $orders->pluck('plan_id')->toArray()
        ]);

        // لاگ دیباگ برای بررسی orders بدون plan
        $ordersWithoutPlan = $user->orders()
            ->where('status', 'paid')
            ->whereNull('plan_id')
            ->get();
        
        if ($ordersWithoutPlan->isNotEmpty()) {
            Log::warning("ORDERS_WITHOUT_PLAN", [
                'user_id' => $user->id,
                'count' => $ordersWithoutPlan->count(),
                'order_ids' => $ordersWithoutPlan->pluck('id')->toArray(),
                'details' => $ordersWithoutPlan->map(function($o) {
                    return [
                        'id' => $o->id,
                        'amount' => $o->amount,
                        'created_at' => $o->created_at,
                        'panel_username' => $o->panel_username
                    ];
                })->toArray()
            ]);
        }

        if ($orders->isEmpty()) {
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '🛒 خرید سرویس جدید', 'callback_data' => '/plans']),
                Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start']),
            ]);
            $this->sendOrEditMessage($user->telegram_chat_id, $this->escape("⚠️ شما هیچ سرویس فعالی ندارید."), $keyboard, $messageId);
            return;
        }

    $message = "🛠 *سرویس‌های شما*\n";
    $message .= "━━━━━━━━━━━━━━━\n\n";
    $message .= $this->escape("لطفاً یک سرویس را برای مشاهده جزئیات انتخاب کنید:") . "\n";

    $keyboard = Keyboard::make()->inline();
    $validServicesCount = 0;

    foreach ($orders as $order) {
        // چون با whereNotNull و with گرفتیم، همه باید plan داشته باشند
        if (!$order->plan) {
            Log::error("SERVICE_MISSING_PLAN", [
                'order_id' => $order->id,
                'plan_id' => $order->plan_id
            ]);
            continue;
        }

        $validServicesCount++;
        $expiresAt = Carbon::parse($order->expires_at);
        $now = now();
        $statusIcon = '🟢';

        if ($expiresAt->isPast()) {
            $statusIcon = '⚫️';
        } elseif ($expiresAt->diffInDays($now) <= 7) {
            $statusIcon = '🟡';
        }

        $username = $order->panel_username ?: "سرویس-{$order->id}";
        $buttonText = "{$statusIcon} {$username} (ID: #{$order->id})";

        $keyboard->row([
            Keyboard::inlineButton([
                'text' => $buttonText,
                'callback_data' => "show_service_{$order->id}"
            ])
        ]);
    }

    Log::info("SERVICES_DISPLAYED", [
        'user_id' => $user->id,
        'valid_services' => $validServicesCount
    ]);

    $keyboard->row([
        Keyboard::inlineButton(['text' => '🛒 خرید سرویس جدید', 'callback_data' => '/plans']),
        Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
    ]);

    $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
}

    protected function showServiceDetails($user, $orderId, $messageId = null)
    {
        $order = $user->orders()->with('plan')->find($orderId);

        if (!$order || !$order->plan || $order->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر یافت نشد یا معتبر نیست.", $messageId);
            return;
        }

        $panelUsername = $order->panel_username;
        if (empty($panelUsername)) {
            $panelUsername = "user-{$user->id}-order-{$order->id}";
        }

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

        $locationFlag = '🏳️';
        $locationName = 'نامشخص';
        // بهبود تشخیص لوکیشن
        $panelType = $this->settings->get('panel_type');
        if ($order->plan && ($panelType === 'pasargad' || !$panelType)) {
             $locationFlag = '🦅';
             $locationName = 'سرویس Eagle';
        }

        $message = "🔍 *جزئیات اشتراک \\#{$order->id}*\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";
        $message .= "💎 *سرویس:* " . $this->escape($order->plan->name) . "\n";
        $message .= "🌍 *موقعیت:* {$locationFlag} " . $this->escape($locationName) . "\n";
        $message .= "👤 *نام کاربری:* `" . $this->escapeCode($panelUsername) . "`\n";
        $message .= "🗓 *انقضا:* " . $this->escape($expiresAt->format('Y/m/d')) . "\n";
        $message .= "⏱ *وضعیت:* " . $remainingText . "\n";
        $message .= "📦 *حجم کل:* " . $this->escape($order->plan->volume_gb . ' گیگابایت') . "\n\n";
        
        if (!empty($order->config_details)) {
            $message .= "🔗 *لینک اشتراک اختصاصی:*\n";
            // اصلاح: استفاده از escapeCode برای محتوای داخل کد بلاک
            $message .= "`" . $this->escapeCode($order->config_details) . "`\n\n";
            $message .= "👆🏻 " . $this->escape("برای کپی سریع روی لینک بالا بزنید!") . "\n";
        } else {
            $message .= "⏳ " . $this->escape("در حال آماده‌سازی کانفیگ...");
        }

        $keyboard = Keyboard::make()->inline();

        if (!empty($order->config_details)) {
            $keyboard->row([
                Keyboard::inlineButton(['text' => "📱 دریافت QR Code", 'callback_data' => "qrcode_order_{$order->id}"]),
                Keyboard::inlineButton(['text' => "📋 کپی لینک", 'callback_data' => "copy_link_{$order->id}"])
            ]);
        }

        $keyboard->row([
            Keyboard::inlineButton(['text' => "🔄 تمدید اشتراک", 'callback_data' => "renew_order_{$order->id}"])
        ]);

        $keyboard->row([
            Keyboard::inlineButton(['text' => '⬅️ بازگشت به لیست سرویس‌ها', 'callback_data' => '/my_services']),
            Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
        ]);

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function showServiceDetailsWithQR($user, $orderId, $messageId = null)
    {
        // 1. اگر پیام قبلی وجود دارد، آن را حذف کن (چون نمی‌توان متن را به عکس تبدیل کرد)
        if ($messageId) {
            try {
                Telegram::deleteMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'message_id' => $messageId
                ]);
            } catch (\Exception $e) {
                // اگر پیام قبلاً حذف شده بود، مشکلی نیست
            }
        }

        $order = $user->orders()->with('plan')->find($orderId);

        if (!$order || !$order->plan || $order->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر یافت نشد یا معتبر نیست.", null);
            return;
        }

        // اگر کانفیگ ندارد، همان متن ساده را بفرست
        if (empty($order->config_details)) {
             $this->showServiceDetails($user, $orderId, null);
             return;
        }

        $configLink = trim($order->config_details);
        $panelUsername = $order->panel_username ?: (($user->telegram_username ? "@" . $user->telegram_username : "user-" . $user->id) . "-order-" . $order->id);
        
        $expiresAt = Carbon::parse($order->expires_at);
        $now = now();
        $daysRemaining = (int) $now->diffInDays($expiresAt, false);
        
        if ($expiresAt->isPast()) {
            $remainingText = "*منقضی شده*";
            $statusIcon = '⚫️';
        } elseif ($daysRemaining <= 7) {
            $remainingText = "*" . $this->escape($daysRemaining . ' روز') . "* باقی‌مانده (تمدید کنید)";
            $statusIcon = '🟡';
        } else {
            $remainingText = "*" . $this->escape($daysRemaining . ' روز') . "* باقی‌مانده";
            $statusIcon = '🟢';
        }

        $locationFlag = '🏳️';
        $locationName = 'نامشخص';
        $panelType = $this->settings->get('panel_type');
        if ($order->plan && ($panelType === 'pasargad' || !$panelType)) {
             $locationFlag = '🦅';
             $locationName = 'سرویس Eagle';
        }

        // متن کپشن (مشابه showServiceDetails)
        $caption = "🔍 *جزئیات اشتراک \\#{$order->id}*\n";
        $caption .= "━━━━━━━━━━━━━━━\n\n";
        $caption .= "💎 *سرویس:* " . $this->escape($order->plan->name) . "\n";
        $caption .= "🌍 *موقعیت:* {$locationFlag} " . $this->escape($locationName) . "\n";
        $caption .= "👤 *نام کاربری:* `" . $this->escapeCode($panelUsername) . "`\n";
        $caption .= "🗓 *انقضا:* " . $this->escape($expiresAt->format('Y/m/d')) . "\n";
        $caption .= "⏱ *وضعیت:* " . $remainingText . "\n";
        $caption .= "📦 *حجم کل:* " . $this->escape($order->plan->volume_gb . ' گیگابایت') . "\n\n";
        
        $caption .= "🔗 *لینک اشتراک اختصاصی:*\n";
        $caption .= "`" . $this->escapeCode($configLink) . "`\n\n";
        $caption .= "👆🏻 " . $this->escape("برای کپی سریع روی لینک بالا بزنید!") . "\n";

        // کیبورد
        $keyboard = Keyboard::make()->inline();
        $keyboard->row([
            Keyboard::inlineButton(['text' => "📋 کپی لینک", 'callback_data' => "copy_link_{$order->id}"]),
            Keyboard::inlineButton(['text' => "🔄 تمدید اشتراک", 'callback_data' => "renew_order_{$order->id}"])
        ]);
        $keyboard->row([
            Keyboard::inlineButton(['text' => '⬅️ بازگشت به لیست سرویس‌ها', 'callback_data' => '/my_services']),
            Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
        ]);

        // تولید و ارسال QR
        $tempFile = null;
        try {
            $qrParams = [
                'size' => '400x400',
                'data' => $configLink,
                'ecc' => 'M',
                'margin' => 10,
                'color' => '000000',
                'bgcolor' => 'FFFFFF',
                'format' => 'png'
            ];
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query($qrParams);
            
            // دانلود فایل با چک کردن HTTP Code
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $qrUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30, // افزایش تایم‌اوت
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            $qrData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($qrData)) {
                throw new \Exception("QR Service Failed with HTTP Code: $httpCode");
            }

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
            $tempFile = $tempDir . '/qr_auto_' . $order->id . '_' . time() . '.png';
            if (file_put_contents($tempFile, $qrData) === false) {
                 throw new \Exception("Could not write temp file");
            }

            Telegram::sendPhoto([
                'chat_id' => $user->telegram_chat_id,
                'photo' => InputFile::create($tempFile, "qr_{$order->id}.png"),
                'caption' => $caption,
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $keyboard
            ]);

        } catch (\Exception $e) {
            Log::error("QR Auto-Send Failed: " . $e->getMessage());
            // فال‌بک به متن معمولی
            $this->showServiceDetails($user, $orderId, null); // null messageId to force new message
        } finally {
            if ($tempFile && file_exists($tempFile)) @unlink($tempFile);
        }
    }

    protected function sendWalletMenu($user, $messageId = null)
    {
        $balance = number_format($user->balance ?? 0);
        $message = "💰 *کیف پول شما*\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";
        $message .= "💵 موجودی فعلی: *" . $this->escape($balance . ' تومان') . "*\n\n";
        $message .= "می‌توانید حساب خود را شارژ کنید یا تاریخچه تراکنش‌ها را مشاهده نمایید:";

        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '💳 شارژ حساب', 'callback_data' => '/deposit']),
                Keyboard::inlineButton(['text' => '📜 تراکنش‌ها', 'callback_data' => '/transactions']),
            ])
            ->row([Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])]);

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

        $message = "🎁 *دعوت از دوستان*\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";
        $message .= $this->escape("با اشتراک‌گذاری لینک زیر، دوستان خود را به ربات دعوت کنید و هدیه بگیرید!") . "\n\n";
        
        $rewardText = "💸 " . $this->escape("با هر خرید موفق دوستانتان، ") . "*" . $this->escape($referrerReward . " تومان") . "*" . $this->escape(" به کیف پول شما اضافه می‌شود.") . "\n\n";
        $message .= $rewardText;
        
        $message .= "🔗 " . $this->escape("لینک دعوت شما (برای کپی لمس کنید):") . "\n";
        $message .= "`" . $this->escapeCode($referralLink) . "`\n\n";
        
        $message .= "👥 " . $this->escape("دعوت‌های موفق شما:") . " *" . $this->escape($referralCount . " نفر") . "*";

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
        ]);
        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }


    protected function sendTransactions($user, $messageId = null)
    {
        $transactions = $user->transactions()->with('order.plan')->latest()->take(10)->get();

        $message = "📜 *۱۰ تراکنش اخیر شما*\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";

        if ($transactions->isEmpty()) {
            $message .= $this->escape("شما تاکنون هیچ تراکنشی ثبت نکرده‌اید.");
        } else {
            foreach ($transactions as $transaction) {
                $type = 'نامشخص';
                switch ($transaction->type) {
                    case 'deposit': $type = '💰 شارژ کیف پول'; break;
                    case 'purchase':
                        if ($transaction->order?->renews_order_id) {
                            $type = '🔄 تمدید سرویس';
                        } else {
                            $type = '🛒 خرید سرویس';
                        }
                        break;
                    case 'referral_reward': $type = '🎁 پاداش دعوت'; break;
                    case 'withdraw': $type = '📤 برداشت وجه'; break;
                    case 'refund': $type = '↩️ بازگشت وجه'; break;
                    case 'manual adjustment': $type = '✏️ اصلاح دستی'; break;
                }

                $status = '⚪️';
                switch ($transaction->status) {
                    case 'completed': $status = '✅'; break;
                    case 'pending': $status = '⏳'; break;
                    case 'failed': $status = '❌'; break;
                }

                $amount = number_format(abs($transaction->amount));
                $date = Carbon::parse($transaction->created_at)->format('Y/m/d');

                $message .= "{$status} *" . $this->escape($type) . "*\n";
                $message .= "   💸 *مبلغ:* " . $this->escape($amount . " تومان") . "\n";
                $message .= "   📅 *تاریخ:* " . $this->escape($date) . "\n";
                if ($transaction->order && $transaction->order->plan) {
                    $message .= "   🏷 *پلن:* " . $this->escape($transaction->order->plan->name) . "\n";
                }
                $message .= "┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄\n";
            }
        }

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton(['text' => '⬅️ بازگشت به کیف پول', 'callback_data' => '/wallet'])
        ]);

        $this->sendRawMarkdownMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function sendTutorialsMenu($chatId, $messageId = null)
    {
        $message = "📚 *راهنمای اتصال*\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";
        $message .= "لطفاً سیستم‌عامل خود را برای دریافت راهنما انتخاب کنید:";
        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '📱 اندروید', 'callback_data' => '/tutorial_android']),
                Keyboard::inlineButton(['text' => '🍏 آیفون', 'callback_data' => '/tutorial_ios']),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '💻 ویندوز', 'callback_data' => '/tutorial_windows']),
                Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start']),
            ]);
        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function sendTutorial($platform, $chatId, $messageId = null)
    {
        $telegramSettings = TelegramBotSetting::pluck('value', 'key');

        $settingKey = match($platform) {
            'android' => 'tutorial_android',
            'ios' => 'tutorial_ios',
            'windows' => 'tutorial_windows',
            default => null
        };

        $message = $settingKey ? ($telegramSettings->get($settingKey) ?? "آموزشی برای این پلتفرم یافت نشد.")
            : "پلتفرم نامعتبر است.";

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
            'text'         => $message,
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

    /**
     * ⚠️ نکته: اطمینان حاصل کنید که XUIService و MarzbanService وجود دارند و متدهای لازم را دارند
     */
    protected function provisionUserAccount(Order $order, Plan $plan)
    {
        $settings = $this->settings;
        $uniqueUsername = $order->panel_username ?? "user-{$order->user_id}-order-{$order->id}";
        $configData = [
            'link' => null,
            'username' => null,
            'panel_client_id' => null,
            'panel_sub_id' => null
        ];

        $isMultiLocationEnabled = filter_var(
            $settings->get('enable_multilocation', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $isMultiServer = false;
        $panelType = $settings->get('panel_type') ?? 'marzban';
        $targetServer = null; // ✅ تعریف اولیه

        // مقادیر پیش‌فرض
        $xuiHost = $settings->get('xui_host');
        $xuiUser = $settings->get('xui_user');
        $xuiPass = $settings->get('xui_pass');
        $inboundId = (int) $settings->get('xui_default_inbound_id');

        // بررسی مولتی سرور
        if ($isMultiLocationEnabled && class_exists('Modules\MultiServer\Models\Server') && $order->server_id) {
            $targetServer = \Modules\MultiServer\Models\Server::find($order->server_id);
            if ($targetServer && $targetServer->is_active) {
                $isMultiServer = true;
                $panelType = 'xui';
                $xuiHost = $targetServer->full_host;
                $xuiUser = $targetServer->username;
                $xuiPass = $targetServer->password;
                $inboundId = $targetServer->inbound_id;

                Log::info("🚀 Provisioning on MultiServer", [
                    'server_name' => $targetServer->name,
                    'server_id' => $targetServer->id,
                    'host' => parse_url($xuiHost, PHP_URL_HOST),
                    'link_type' => $targetServer->link_type ?? 'not set'
                ]);
            }
        }

        try {
            // ==========================================
            // پنل MARZBAN
            // ==========================================
            if ($panelType === 'marzban' && !$isMultiServer) {
                $marzban = new MarzbanService(
                    $settings->get('marzban_host'),
                    $settings->get('marzban_sudo_username'),
                    $settings->get('marzban_sudo_password'),
                    $settings->get('marzban_node_hostname')
                );
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
                    Log::error('Marzban user creation failed.', ['response' => $response]);
                    return null;
                }
            }
            // ==========================================
            // پنل X-UI
            // ==========================================
            elseif ($panelType === 'xui') {
                if ($inboundId <= 0) {
                    throw new \Exception("Inbound ID نامعتبر است: {$inboundId}");
                }

                $xui = new XUIService($xuiHost, $xuiUser, $xuiPass);

                if (!$xui->login()) {
                    throw new \Exception("❌ خطا در لاگین به پنل X-UI");
                }

                // دریافت اینباند
                $inboundData = null;
                if ($isMultiServer) {
                    $allInbounds = $xui->getInbounds();
                    foreach ($allInbounds as $remoteInbound) {
                        if ($remoteInbound['id'] == $inboundId) {
                            $inboundData = $remoteInbound;
                            break;
                        }
                    }
                    if (!$inboundData) throw new \Exception("اینباند در سرور یافت نشد.");
                } else {
                    $inboundModel = Inbound::whereJsonContains('inbound_data->id', (int)$inboundId)->first();
                    if ($inboundModel) {
                        $inboundData = is_string($inboundModel->inbound_data) ? json_decode($inboundModel->inbound_data, true) : $inboundModel->inbound_data;
                    } else {
                        throw new \Exception("اینباند پیش‌فرض یافت نشد.");
                    }
                }

                // تعیین نوع لینک
                $linkType = ($isMultiServer && $targetServer) ? ($targetServer->link_type ?? 'single') : $settings->get('xui_link_type', 'single');

                $clientData = [
                    'email' => $uniqueUsername,
                    'total' => $plan->volume_gb * 1024 * 1024 * 1024,
                    'expiryTime' => $order->expires_at->timestamp * 1000,
                ];

                if ($linkType === 'subscription') {
                    $clientData['subId'] = Str::random(16);
                }

                Log::info("Creating XUI client", ['email' => $uniqueUsername, 'link_type' => $linkType]);

                // ساخت کاربر
                $response = $xui->addClient($inboundId, $clientData);


                if ($response && isset($response['success']) && $response['success']) {
                    // استخراج اطلاعات
                    $uuid = $response['generated_uuid'] ?? null;
                    if (!$uuid && isset($response['obj']['settings'])) {
                        $cSettings = json_decode($response['obj']['settings'], true);
                        $uuid = $cSettings['clients'][0]['id'] ?? null;
                    }
                    $subId = $response['generated_subId'] ?? $clientData['subId'] ?? null;

                    $streamSettings = json_decode($inboundData['streamSettings'] ?? '{}', true);
                    $protocol = $inboundData['protocol'] ?? 'vless';
                    $inboundPort = $inboundData['port'] ?? 443;
                    $serverAddress = parse_url($xuiHost, PHP_URL_HOST);

                    switch ($linkType) {
                        case 'subscription':
                            if ($isMultiServer && $targetServer) {
                                $subDomain = $targetServer->subscription_domain ?? $serverAddress;
                                $subPort = $targetServer->subscription_port ?? 2053;
                                $subPath = $targetServer->subscription_path ?? '/sub/';
                                $isHttps = $targetServer->is_https ?? true;

                                $baseUrl = rtrim($subDomain, '/');
                                // اگر پورت هست اضافه کن
                                if ($subPort) $baseUrl .= ":{$subPort}";
                                // پروتکل
                                $protocolScheme = $isHttps ? 'https' : 'http';

                                $configLink = "{$protocolScheme}://{$baseUrl}" . rtrim($subPath, '/') . '/' . $subId;
                            } else {
                                $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                                $configLink = $subBaseUrl . '/sub/' . $subId;
                            }
                            break;

                        case 'tunnel':
                            if (!$uuid) throw new \Exception("UUID missing for tunnel link");

                            $tunnelAddress = $targetServer->tunnel_address;
                            $tunnelPort = $targetServer->tunnel_port ?? 443;

                            // 🔥 اصلاح مهم: خواندن وضعیت دقیق HTTPS از دیتابیس
                            $tunnelHasTls = filter_var($targetServer->tunnel_is_https, FILTER_VALIDATE_BOOLEAN);

                            $params = [];
                            $params['type'] = $streamSettings['network'] ?? 'tcp';

                            if ($tunnelHasTls) {
                                $params['security'] = 'tls';
                                $params['sni'] = $tunnelAddress;
                            } else {
                                $params['security'] = 'none';
                                // 🔥 اگر TLS خاموش است، حتما این گزینه اضافه شود
                                if ($protocol === 'vless') {
                                    $params['encryption'] = 'none';
                                }
                            }

                            if ($params['type'] === 'ws' && isset($streamSettings['wsSettings'])) {
                                $params['path'] = $streamSettings['wsSettings']['path'] ?? '/';
                                $params['host'] = $streamSettings['wsSettings']['headers']['Host'] ?? $tunnelAddress;
                            }



                            $locFlag = $targetServer->location->flag ?? '🏳️';
                            $remarkText = $locFlag . "-" . $uniqueUsername;

                            $queryString = http_build_query($params);
                            // ساخت لینک نهایی
                            $configLink = "vless://{$uuid}@{$tunnelAddress}:{$tunnelPort}?{$queryString}#" . rawurlencode($remarkText);
                            break;
                        default: // single
                            if (!$uuid) throw new \Exception("UUID missing for single link");

                            $params = [];
                            $params['type'] = $streamSettings['network'] ?? 'tcp';
                            $params['security'] = $streamSettings['security'] ?? 'none';

                            if ($params['type'] === 'ws' && isset($streamSettings['wsSettings'])) {
                                $params['path'] = $streamSettings['wsSettings']['path'] ?? '/';
                                $params['host'] = $streamSettings['wsSettings']['headers']['Host'] ?? $serverAddress;
                            }

                            if ($params['security'] === 'tls' && isset($streamSettings['tlsSettings'])) {
                                $params['sni'] = $streamSettings['tlsSettings']['serverName'] ?? $serverAddress;
                            }

                            $queryString = http_build_query(array_filter($params));
                            $configLink = "vless://{$uuid}@{$serverAddress}:{$inboundPort}?{$queryString}#" . rawurlencode($plan->name);
                            break;
                    }

                    $configData['link'] = $configLink;
                    $configData['username'] = $uniqueUsername;
                    $configData['panel_client_id'] = $uuid;
                    $configData['panel_sub_id'] = $subId;

                }
            }
            // ==========================================
            // پنل PASARGAD
            // ==========================================
            elseif ($panelType === 'pasargad') {
                $pasargad = new PasargadService(
                    $settings->get('pasargad_host'),
                    $settings->get('pasargad_sudo_username'),
                    $settings->get('pasargad_sudo_password'),
                    $settings->get('pasargad_node_hostname')
                );
                
                $response = $pasargad->createUser([
                    'username' => $uniqueUsername,
                    'expire' => $order->expires_at->timestamp,
                    'data_limit' => $plan->volume_gb * 1024 * 1024 * 1024,
                    'group_ids' => [(int)($plan->pasargad_group_id ?? $settings->get('pasargad_paid_group_id') ?? 1)],
                ]);

                if (!empty($response['subscription_url'])) {
                    $configData['link'] = $response['subscription_url'];
                    $configData['username'] = $uniqueUsername;
                } else {
                    Log::error('Pasargad user creation failed.', ['response' => $response]);
                    return null;
                }
            } else {
                throw new \Exception("Panel type not supported: {$panelType}");
            }

            if ($isMultiServer && isset($targetServer)) {
                $targetServer->increment('current_users');
            }

        } catch (\Exception $e) {
            Log::error("Failed to provision account for Order {$order->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'server_id' => $order->server_id ?? null
            ]);

            if ($isMultiServer && isset($targetServer)) {
                $targetServer->decrement('current_users');
            }
            return null;
        }

        return $configData;
    }

    protected function showDepositOptions($user, $messageId)
    {
        $message = "💳 *شارژ کیف پول*\n\nلطفاً مبلغ مورد نظر برای شارژ را انتخاب کنید یا مبلغ دلخواه خود را وارد نمایید:";
        $keyboard = Keyboard::make()->inline();

        $telegramSettings = TelegramBotSetting::pluck('value', 'key');
        $depositAmountsJson = $telegramSettings->get('deposit_amounts', '[]');
        $depositAmountsData = json_decode($depositAmountsJson, true);

        $depositAmounts = [];
        if (is_array($depositAmountsData)) {
            foreach ($depositAmountsData as $item) {
                if (isset($item['amount']) && is_numeric($item['amount'])) {
                    $depositAmounts[] = (int)$item['amount'];
                }
            }
        }

        if (empty($depositAmounts)) {
            $depositAmounts = [50000, 100000, 200000, 500000];
        }

        sort($depositAmounts);

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

    /**
     * ✅ اصلاح: استفاده از & برای دسترسی به متغیرها پس از تراکنش
     */
    protected function processRenewalWalletPayment($user, $originalOrderId, $messageId)
    {
        $originalOrder = $user->orders()->with('plan')->find($originalOrderId);
        $newRenewalOrder = null; // ✅ تعریف اولیه
        $provisionData = null;   // ✅ تعریف اولیه

        // بررسی‌های اولیه
        if (!$originalOrder || !$originalOrder->plan || $originalOrder->status !== 'paid') {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ سرویس مورد نظر برای تمدید یافت نشد.", $messageId);
            return;
        }

        $plan = $originalOrder->plan;

        // بررسی موجودی قبل از هر کاری
        if ($user->balance < $plan->price) {
            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => '💳 شارژ کیف پول', 'callback_data' => '/deposit']),
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/my_services'])
                ]);
            $this->sendOrEditMessage($user->telegram_chat_id, "❌ موجودی کیف پول شما برای تمدید کافی نیست.", $keyboard, $messageId);
            return;
        }

        try {
            DB::transaction(function () use ($user, $originalOrder, $plan, &$newRenewalOrder, &$provisionData) { // ✅ & اضافه شد

                $user->decrement('balance', $plan->price);

                $newRenewalOrder = $user->orders()->create([
                    'plan_id' => $plan->id,
                    'status' => 'paid',

                    'source' => 'telegram_renewal',
                    'amount' => $plan->price,
                    'expires_at' => null,
                    'payment_method' => 'wallet',
                    'panel_username' => $originalOrder->panel_username,
                ]);

                $newRenewalOrder->renews_order_id = $originalOrder->id;
                $newRenewalOrder->save();

                Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $newRenewalOrder->id,
                    'amount' => -$plan->price,
                    'type' => 'purchase',
                    'status' => 'completed',
                    'description' => "تمدید سرویس {$plan->name} (سفارش اصلی #{$originalOrder->id})"
                ]);

                $provisionData = $this->renewUserAccount($originalOrder, $plan);

                if (!$provisionData) {
                    throw new \Exception('تمدید در پنل با خطا مواجه شد.');
                }
            });

            // حالا متغیرها پر شده‌اند
            $newExpiryDate = Carbon::parse($originalOrder->refresh()->expires_at);
            $daysText = $this->escape($plan->duration_days . ' روز');
            $dateText = $this->escape($newExpiryDate->format('Y/m/d'));
            $planName = $this->escape($plan->name);

            $linkCode = $provisionData['link'];

            $successMessage = "⚡️ *سرویس شما با قدرت تمدید شد!* ⚡️\n\n";
            $successMessage .= "💎 *پلن:* {$planName}\n";
            $successMessage .= "⏳ *مدت افزوده شده:* {$daysText}\n";
            $successMessage .= "📅 *انقضای جدید:* {$dateText}\n\n";
            $successMessage .= "🔗 *لینک اتصال شما (بدون تغییر):*\n";
            $successMessage .= "👇 _برای کپی روی لینک زیر ضربه بزنید_\n";
            $successMessage .= "{$linkCode}";
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
                Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
            ]);

            $this->sendOrEditMessage($user->telegram_chat_id, $successMessage, $keyboard, $messageId);

        } catch (\Exception $e) {
            Log::error('Renewal Wallet Payment Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'original_order_id' => $originalOrderId,
                'user_id' => $user->id
            ]);

            if ($newRenewalOrder) {
                try {
                    $user->increment('balance', $plan->price);
                } catch (\Exception $refundEx) {
                    Log::critical("Failed to refund user {$user->id}: " . $refundEx->getMessage());
                }
                $newRenewalOrder->delete();
            }

            $errorKeyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '💬 پشتیبانی', 'callback_data' => '/support_menu'])
            ]);

            $errorMessage = $this->escape("⚠️ تمدید با خطا مواجه شد. مبلغ {$plan->price} تومان به کیف پول شما بازگردانده شد.");
            $this->sendOrEditMessage($user->telegram_chat_id, $errorMessage, $errorKeyboard, $messageId);
        }
    }

    /**
     * ارسال لینک خام (بدون فرمت) برای کپی آسان
     */
    protected function handleCopyLinkRequest($user, $orderId, $messageId = null)
    {
        try {
            $order = $user->orders()->with('plan')->find($orderId);

            if (!$order || $order->status !== 'paid') {
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => $this->escape("❌ سفارش یافت نشد یا معتبر نیست."),
                    'parse_mode' => 'MarkdownV2'
                ]);
                return;
            }

            if (empty($order->config_details)) {
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => $this->escape("❌ لینک کانفیگ هنوز آماده نیست."),
                    'parse_mode' => 'MarkdownV2'
                ]);
                return;
            }

            // ارسال لینک خالی (بدون markdown) که کاربر بتواند کپی کند
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $order->config_details, // فقط لینک خالی بدون هیچ فرمتی
                'reply_markup' => Keyboard::make()->inline()->row([
                    Keyboard::inlineButton(['text' => '⬅️ بازگشت به جزئیات سرویس', 'callback_data' => "show_service_{$orderId}"])
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Copy link error: ' . $e->getMessage());
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $this->escape("❌ خطا در ارسال لینک."),
                'parse_mode' => 'MarkdownV2'
            ]);
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
            'plan_id' => $plan->id,
            'server_id' => $originalOrder->server_id,
            'status' => 'pending',
            'source' => 'telegram_renewal',
            'amount' => $plan->price,
            'expires_at' => null,
            'panel_username' => $originalOrder->panel_username,
        ]);

        $newRenewalOrder->renews_order_id = $originalOrder->id;
        $newRenewalOrder->save();

        $this->sendCardPaymentInfo($user->telegram_chat_id, $newRenewalOrder->id, $messageId);
    }

    /**
     * ⚠️ نکته: اطمینان حاصل کنید که متدهای updateUser و resetUserTraffic در MarzbanService
     * و updateClient و resetClientTraffic در XUIService وجود دارند
     */
    protected function renewUserAccount(Order $originalOrder, Plan $plan)
    {
        $settings = $this->settings;
        $user = $originalOrder->user;
        $uniqueUsername = $originalOrder->panel_username ?? "user-{$user->id}-order-{$originalOrder->id}";

        $isMultiLocationEnabled = filter_var(
            $settings->get('enable_multilocation', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $currentExpiresAt = Carbon::parse($originalOrder->expires_at);
        $baseDate = $currentExpiresAt->isPast() ? now() : $currentExpiresAt;
        $newExpiryDate = $baseDate->copy()->addDays($plan->duration_days);

        $isMultiServer = false;
        $panelType = $settings->get('panel_type') ?? 'marzban';
        $targetServer = null;

        $xuiHost = $settings->get('xui_host');
        $xuiUser = $settings->get('xui_user');
        $xuiPass = $settings->get('xui_pass');
        $inboundId = (int) $settings->get('xui_default_inbound_id');

        // بررسی مولتی سرور
        if ($isMultiLocationEnabled && class_exists('Modules\MultiServer\Models\Server') && $originalOrder->server_id) {
            $targetServer = \Modules\MultiServer\Models\Server::find($originalOrder->server_id);
            if ($targetServer && $targetServer->is_active) {
                $isMultiServer = true;
                $panelType = 'xui';
                $xuiHost = $targetServer->full_host;
                $xuiUser = $targetServer->username;
                $xuiPass = $targetServer->password;
                $inboundId = $targetServer->inbound_id;
            }
        }

        try {
            // --- MARZBAN ---
            if ($panelType === 'marzban') {
                $marzban = new MarzbanService(
                    $settings->get('marzban_host'),
                    $settings->get('marzban_sudo_username'),
                    $settings->get('marzban_sudo_password'),
                    $settings->get('marzban_node_hostname')
                );

                $updateResponse = $marzban->updateUser($uniqueUsername, [
                    'expire' => $newExpiryDate->timestamp,
                    'data_limit' => $plan->volume_gb * 1073741824,
                ]);
                $resetResponse = $marzban->resetUserTraffic($uniqueUsername);

                if ($updateResponse !== null && $resetResponse !== null) {
                    $originalOrder->update(['expires_at' => $newExpiryDate]);
                    return [
                        'link' => $originalOrder->config_details,
                        'username' => $uniqueUsername
                    ];
                } else {
                    return null;
                }
            }
            // --- X-UI (SANAEI) ---
            elseif ($panelType === 'xui') {
                if ($inboundId <= 0) {
                    throw new \Exception("❌ Inbound ID نامعتبر: {$inboundId}");
                }

                $xui = new XUIService($xuiHost, $xuiUser, $xuiPass);

                if (!$xui->login()) {
                    throw new \Exception("❌ خطا در لاگین به پنل X-UI");
                }

                // گرفتن اطلاعات اینباند
                $inboundData = null;
                if ($isMultiServer) {
                    $allInbounds = $xui->getInbounds();
                    foreach ($allInbounds as $remoteInbound) {
                        if ($remoteInbound['id'] == $inboundId) {
                            $inboundData = $remoteInbound;
                            break;
                        }
                    }
                    if (!$inboundData) throw new \Exception("اینباند در سرور یافت نشد.");
                } else {
                    $inboundModel = Inbound::whereJsonContains('inbound_data->id', (int)$inboundId)->first();
                    if ($inboundModel) {
                        $inboundData = is_string($inboundModel->inbound_data) ? json_decode($inboundModel->inbound_data, true) : $inboundModel->inbound_data;
                    } else {
                        throw new \Exception("اینباند پیش‌فرض یافت نشد.");
                    }
                }

                // پیدا کردن کلاینت قبلی
                $clients = $xui->getClients($inboundData['id']);
                $client = collect($clients)->firstWhere('email', $uniqueUsername);

                if (!$client) {
                    throw new \Exception("❌ کلاینت با ایمیل {$uniqueUsername} یافت نشد.");
                }

                $linkType = ($isMultiServer && $targetServer) ? ($targetServer->link_type ?? 'single') : $settings->get('xui_link_type', 'single');

                $clientData = [
                    'id' => $client['id'],
                    'email' => $uniqueUsername,
                    'total' => $plan->volume_gb * 1073741824, // حجم جدید بر حسب بایت
                    'expiryTime' => $newExpiryDate->timestamp * 1000, // زمان انقضای جدید
                ];

                if ($linkType === 'subscription' && isset($client['subId'])) {
                    $clientData['subId'] = $client['subId'];
                }

                // ۱. آپدیت کردن زمان و حجم کلی
                $response = $xui->updateClient($inboundData['id'], $client['id'], $clientData);

                if ($response && isset($response['success']) && $response['success']) {

                    // 🔥 ۲. ریست کردن ترافیک مصرفی (مهم برای تمدید) 🔥
                    $resetResult = $xui->resetClientTraffic($inboundData['id'], $uniqueUsername);

                    if ($resetResult) {
                        Log::info("Traffic reset successful for user: $uniqueUsername");
                    } else {
                        Log::warning("Traffic reset FAILED for user: $uniqueUsername");
                    }

                    $originalOrder->update(['expires_at' => $newExpiryDate]);
                    return [
                        'link' => $originalOrder->config_details,
                        'username' => $uniqueUsername
                    ];
                }
            }
            // --- PASARGAD ---
            elseif ($panelType === 'pasargad') {
                $pasargad = new PasargadService(
                    $settings->get('pasargad_host'),
                    $settings->get('pasargad_sudo_username'),
                    $settings->get('pasargad_sudo_password'),
                    $settings->get('pasargad_node_hostname')
                );

                $updateResponse = $pasargad->updateUser($uniqueUsername, [
                    'expire' => $newExpiryDate->timestamp,
                    'data_limit' => $plan->volume_gb * 1073741824,
                    'status' => 'active',
                ]);
                $resetResponse = $pasargad->resetUserTraffic($uniqueUsername);

                if ($updateResponse !== null && $resetResponse !== false) {
                    $originalOrder->update(['expires_at' => $newExpiryDate]);
                    return [
                        'link' => $originalOrder->config_details,
                        'username' => $uniqueUsername
                    ];
                } else {
                    Log::error('Pasargad renewal failed.', ['update' => $updateResponse, 'reset' => $resetResponse]);
                    return null;
                }
            } else {
                throw new \Exception("❌ نوع پنل پشتیبانی نمی‌شود: {$panelType}");
            }
        } catch (\Exception $e) {
            Log::error("❌ تمدید انجام نشد ({$uniqueUsername}): " . $e->getMessage(), [
                'is_multi_server' => $isMultiServer,
                'server_id' => $originalOrder->server_id ?? null
            ]);
            return null;
        }
    }
    protected function showSupportMenu($user, $messageId = null)
    {
        $tickets = $user->tickets()->latest()->take(5)->get();
        $message = "💬 *مرکز پشتیبانی*\n";
        $message .= "━━━━━━━━━━━━━━━\n\n";
        if ($tickets->isEmpty()) {
            $message .= $this->escape("شما تاکنون هیچ تیکتی ثبت نکرده‌اید.");
        } else {
            $message .= $this->escape("لیست تیکت‌های اخیر شما:") . "\n";
            foreach ($tickets as $ticket) {
                $status = match ($ticket->status) {
                    'open' => '🔵 باز',
                    'answered' => '🟢 پاسخ ادمین',
                    'closed' => '⚪️ بسته',
                    default => '⚪️ نامشخص',
                };
                $ticketIdEscaped = $ticket->id;
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
            $this->showSupportMenu($user, $messageId);
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

                $ticket = $user->tickets()->create([
                    'subject' => $subject,
                    'message' => $messageText,
                    'priority' => 'medium', 'status' => 'open', 'source' => 'telegram', 'user_id' => $user->id
                ]);

                $replyData = ['user_id' => $user->id, 'message' => $messageText];
                if ($update->getMessage()->has('photo')) {
                    try { $replyData['attachment_path'] = $this->savePhotoAttachment($update, 'ticket_attachments'); }
                    catch (\Exception $e) { Log::error("Error saving photo for new ticket {$ticket->id}: " . $e->getMessage()); }
                }
                $reply = $ticket->replies()->create($replyData);

                $user->update(['bot_state' => null]);
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("✅ تیکت #{$ticket->id} با موفقیت ثبت شد."), 'parse_mode' => 'MarkdownV2']);
                $this->sendOrEditMainMenu($chatId, "پشتیبانی به زودی پاسخ شما را خواهد داد.");

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

                event(new TicketReplied($reply));
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

    /**
     * ✅ اصلاح: حذف فاصله اضافی از URL و اضافه کردن import Http facade
     */
    protected function isUserMemberOfChannel($user)
    {
        $forceJoin = $this->settings->get('force_join_enabled', '0');

        if (!in_array($forceJoin, ['1', 1, true, 'on'], true)) {
            return true;
        }

        $channelId = $this->settings->get('telegram_required_channel_id');
        if (empty($channelId)) {
            Log::error('FORCE JOIN IS ENABLED BUT NO CHANNEL ID IS SET!');
            return false;
        }

        try {
            $botToken = $this->settings->get('telegram_bot_token');
            // ✅ اصلاح: حذف space بین bot و token



            $apiUrl = "https://api.telegram.org/bot{$botToken}/getChatMember";


            $response = Http::timeout(10)->get($apiUrl, [
                'chat_id' => $channelId,
                'user_id' => $user->telegram_chat_id,
            ]);

            if (!$response->successful()) {
                return false;
            }

            $data = $response->json();
            $status = $data['result']['status'] ?? 'left';

            return in_array($status, ['member', 'administrator', 'creator'], true);

        } catch (\Exception $e) {
            Log::error("Membership check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ اصلاح: حذف فاصله اضافی از URL
     */
    protected function showChannelRequiredMessage($chatId, $messageId = null)
    {
        $channelId = $this->settings->get('telegram_required_channel_id');

        if (empty($channelId)) {
            $message = "❌ خطا: کانال عضویت اجباری تنظیم نشده است.";
            $this->sendOrEditMessage($chatId, $message, null, $messageId);
            return;
        }

        $channelLink = null;
        $channelDisplayName = $channelId;

        if (str_starts_with($channelId, '@')) {
            $username = ltrim($channelId, '@');
            // ✅ اصلاح: حذف space بعد از t.me/



            $channelLink = "https://t.me/{$username}";


            $channelDisplayName = "@" . $username;
        } elseif (preg_match('/^-100\d+$/', $channelId)) {
            $channelDisplayName = "کانال خصوصی";
            $channelLink = $this->settings->get('telegram_private_channel_invite_link');
        }

        $message = "⛔️ *عضویت در کانال الزامی است!*\n\n";
        $message .= "برای ادامه استفاده از ربات، باید در کانال زیر عضو شوید:\n\n";
        $message .= "📢 {$channelDisplayName}\n\n";
        $message .= "🔹 پس از عضویت، روی دکمه «✅ بررسی عضویت» بزنید.";

        $keyboard = Keyboard::make()->inline();

        if (!empty($channelLink)) {
            $keyboard->row([Keyboard::inlineButton(['text' => '📲 عضویت در کانال', 'url' => $channelLink])]);
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '✅ بررسی عضویت', 'callback_data' => '/check_membership'])]);

        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    /**
     * ✅ اصلاح: حذف فاصله اضافی از URL دانلود فایل
     */

    /**
     * ارسال پیام موفقیت‌آمیز بودن خرید با دکمه‌ها
     * این متد هم برای پرداخت کیف پول و هم کارت به کارت استفاده می‌شه
     */
    protected function sendPurchaseSuccessMessage($user, Order $order, $messageId = null)
    {
        // بارگذاری اطلاعات کامل سفارش
        $order->load(['server.location', 'plan']);

        $link = $order->config_details;

        // آماده‌سازی اطلاعات سرور و کشور
        $serverName = 'سرور اصلی';
        $locationFlag = '🏳️';
        $locationName = 'نامشخص';

        if ($order->server) {
            $serverName = $order->server->name;
            if ($order->server->location) {
                $locationFlag = $order->server->location->flag ?? '🏳️';
                $locationName = $order->server->location->name;
            }
        }

        // ساخت پیام کامل و خفن
        $message = "✅ *خرید موفق!*\n\n";
        $message .= "📦 *پلن:* `{$this->escape($order->plan->name)}`\n";
        $message .= "🌍 *موقعیت:* {$locationFlag} {$this->escape($locationName)}\n";
        $message .= "🖥 *سرور:* {$this->escape($serverName)}\n";
        $message .= "💾 *حجم:* {$order->plan->volume_gb} گیگابایت\n";
        $message .= "📅 *مدت:* {$order->plan->duration_days} روز\n";
        $message .= "⏳ *انقضا:* `{$order->expires_at->format('Y/m/d H:i')}`\n";
        $message .= "👤 *یوزرنیم:* `{$order->panel_username}`\n\n";
        $message .= "🔗 *لینک کانفیگ شما:*\n";
        $message .= "`{$link}`\n\n";
        $message .= "⚠️ روی لینک بالا کلیک کنید تا کپی شود";

        // کیبورد با دکمه‌های کاربردی
        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '📋 کپی لینک کانفیگ', 'callback_data' => "copy_link_{$order->id}"]),
                Keyboard::inlineButton(['text' => '📱 QR Code', 'callback_data' => "qrcode_order_{$order->id}"])
            ])
            ->row([
                Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
                Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
            ]);

        try {
            if ($messageId) {
                // ویرایش پیام قبلی (اگر وجود داشته باشه)
                Telegram::editMessageText([
                    'chat_id' => $user->telegram_chat_id,
                    'message_id' => $messageId,
                    'text' => $message,
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
            } else {
                // ارسال پیام جدید
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => $message,
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending purchase success message: ' . $e->getMessage());
            // اگر خطا بود، بدون کیبورد بفرست (fallback)
            Telegram::sendMessage([
                'chat_id' => $user->telegram_chat_id,
                'text' => $message,
                'parse_mode' => 'MarkdownV2'
            ]);
        }
    }

    protected function savePhotoAttachment($update, $directory)
    {
        $photo = collect($update->getMessage()->getPhoto())->last();
        if(!$photo) return null;

        $botToken = $this->settings->get('telegram_bot_token');
        if (!$botToken) $botToken = config('telegrambot.bot_token');
        $botToken = trim($botToken, '"\' ');

        try {
            $file = Telegram::getFile(['file_id' => $photo->getFileId()]);
            // Handle both array and object responses from Telegram SDK
            if (is_array($file)) {
                $filePath = $file['file_path'] ?? null;
            } else {
                $filePath = method_exists($file, 'getFilePath') ? $file->getFilePath() : ($file['file_path'] ?? null);
            }
            
            if(!$filePath) { throw new \Exception('File path not found in Telegram response.'); }

            $url = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            
            // Use stream context for better error handling
            $opts = [
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: VPNMarketBot/1.0\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $fileContents = file_get_contents($url, false, $context);
            
            if ($fileContents === false) { 
                $error = error_get_last();
                throw new \Exception('Failed to download file content from: ' . $url . ' - Error: ' . ($error['message'] ?? 'Unknown'));
            }

            $storagePath = storage_path("app/public/{$directory}");
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = $directory . '/' . Str::random(40) . '.' . $extension;
            
            // Explicitly use public disk
            $success = Storage::disk('public')->put($fileName, $fileContents);

            if (!$success) { throw new \Exception('Failed to save file to storage.'); }

            return $fileName;

        } catch (\Exception $e) {
            Log::error('Error saving photo attachment: ' . $e->getMessage(), ['file_id' => $photo->getFileId()]);
            return null;
        }
    }


    protected function handleTrialRequest($user, $extraUsername = null)
    {
        $settings = $this->settings;
        $chatId = $user->telegram_chat_id;

        Log::info('Trial request initiated', [
            'user_id' => $user->id,
            'trial_enabled' => $settings->get('trial_enabled'),
        ]);

        $trialEnabled = filter_var($settings->get('trial_enabled') ?? '0', FILTER_VALIDATE_BOOLEAN);
        if (!$trialEnabled) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape('❌ قابلیت دریافت اکانت تست در حال حاضر غیرفعال است.'),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        $limit = (int) $settings->get('trial_limit_per_user', 1);
        $currentTrials = $user->trial_accounts_taken ?? 0;

        if ($currentTrials >= $limit) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape('❗️شما قبلاً از اکانت تست خود استفاده کرده‌اید و دیگر مجاز به دریافت آن نیستید.'),
                'parse_mode' => 'MarkdownV2'
            ]);
            return;
        }

        try {
            $volumeMB = (int) $settings->get('trial_volume_mb', 500);
            $durationHours = (int) $settings->get('trial_duration_hours', 24);

            // ایجاد یوزرنیم: اولویت با یوزرنیم تلگرام، وگرنه آیدی عددی
            $usernameBase = !empty($extraUsername) ? $extraUsername : $user->telegram_chat_id;
            
            // تمیز کردن یوزرنیم (حذف کاراکترهای غیرمجاز)
            $usernameBase = preg_replace('/[^a-zA-Z0-9_]/', '', $usernameBase);
            
            $uniqueUsername = "trial_" . $usernameBase;
            
            // اگر قبلاً تستی گرفته بود یا یوزرنیم کوتاه بود، پسوند اضافه کن
            if ($currentTrials > 0 || strlen($usernameBase) < 3) {
                $uniqueUsername .= "_" . ($currentTrials + 1);
            }

            $expiresAt = now()->addHours($durationHours);
            $dataLimitBytes = $volumeMB * 1024 * 1024;

            $configLink = null;
            $panelType = $settings->get('panel_type') ?? 'marzban';
            $targetServer = null;
            $locationFlag = '🏳️';
            $locationName = 'نامشخص';

            // --- تنظیمات سرور (Multi-Server Logic) ---
            $isMultiLocationEnabled = filter_var($settings->get('enable_multilocation', false), FILTER_VALIDATE_BOOLEAN);

            // مقادیر پیش‌فرض X-UI (اگر نخواهیم از مولتی لوکیشن استفاده کنیم)
            $xuiHost = $settings->get('xui_host');
            $xuiUser = $settings->get('xui_user');
            $xuiPass = $settings->get('xui_pass');
            $inboundId = (int) $settings->get('xui_default_inbound_id');
            $linkType = $settings->get('xui_link_type', 'single');

            if ($isMultiLocationEnabled && class_exists('Modules\MultiServer\Models\Server')) {
                // 1. خواندن آیدی سرور تنظیم شده برای تست (از تنظیمات جدید)
                $forcedServerId = $settings->get('trial_server_id');

                // الف) اگر ادمین سرور خاصی را در تنظیمات انتخاب کرده باشد
                if (!empty($forcedServerId)) {
                    $targetServer = \Modules\MultiServer\Models\Server::where('id', $forcedServerId)
                        ->where('is_active', true)
                        ->first();
                }

                // ب) اگر سرور انتخاب شده پیدا نشد یا ادمین چیزی انتخاب نکرده بود (انتخاب خودکار)
                if (!$targetServer) {
                    $targetServer = \Modules\MultiServer\Models\Server::where('is_active', true)
                        ->whereRaw('current_users < capacity')
                        ->first();
                }

                // اعمال تنظیمات سرور انتخاب شده (فقط برای X-UI در حال حاضر)
                if ($targetServer) {
                    $panelType = 'xui';
                    $xuiHost = $targetServer->full_host;
                    $xuiUser = $targetServer->username;
                    $xuiPass = $targetServer->password;
                    $inboundId = $targetServer->inbound_id;
                    $linkType = $targetServer->link_type ?? 'single';
                    
                    if ($targetServer->location) {
                        $locationFlag = $targetServer->location->flag ?? '🏳️';
                        $locationName = $targetServer->location->name;
                    }
                }
            }

            // اگر پنل پاسارگاد است و نام سرور/لوکیشن نداریم، یک مقدار بهتر از "نامشخص" بگذاریم
            if ($panelType === 'pasargad' && $locationName === 'نامشخص') {
                $locationName = 'سرویس Eagle';
                $locationFlag = '🦅';
            }

            if ($panelType === 'marzban') {
                $marzbanService = new MarzbanService(
                    $settings->get('marzban_host'),
                    $settings->get('marzban_sudo_username'),
                    $settings->get('marzban_sudo_password'),
                    $settings->get('marzban_node_hostname')
                );
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
                $xuiService = new XUIService($xuiHost, $xuiUser, $xuiPass);

                if (!$xuiService->login()) {
                    throw new \Exception('خطا در لاگین به پنل X-UI.');
                }

                // گرفتن اطلاعات اینباند
                $inboundData = null;
                if ($targetServer) {
                    $inbounds = $xuiService->getInbounds();
                    foreach ($inbounds as $rem) {
                        if ($rem['id'] == $inboundId) { $inboundData = $rem; break; }
                    }
                } else {
                    $inboundModel = Inbound::whereJsonContains('inbound_data->id', (int)$inboundId)->first();
                    if ($inboundModel) {
                        $inboundData = is_string($inboundModel->inbound_data) ? json_decode($inboundModel->inbound_data, true) : $inboundModel->inbound_data;
                    }
                }

                if (!$inboundData) throw new \Exception('اینباند مورد نظر یافت نشد.');

                $clientData = [
                    'email' => $uniqueUsername,
                    'total' => $dataLimitBytes,
                    'expiryTime' => $expiresAt->timestamp * 1000,
                ];

                if ($linkType === 'subscription') $clientData['subId'] = Str::random(16);

                $response = $xuiService->addClient($inboundData['id'], $clientData);

                if ($response && isset($response['success']) && $response['success']) {
                    $uuid = $response['generated_uuid'] ?? null;
                    if (!$uuid && isset($response['obj']['settings'])) {
                        $cSettings = json_decode($response['obj']['settings'], true);
                        $uuid = $cSettings['clients'][0]['id'] ?? null;
                    }
                    $subId = $response['generated_subId'] ?? $clientData['subId'] ?? null;

                    // ساخت لینک کانفیگ
                    $streamSettings = json_decode($inboundData['streamSettings'] ?? '{}', true);
                    $protocol = $inboundData['protocol'] ?? 'vless';
                    $inboundPort = $inboundData['port'] ?? 443;
                    $serverAddress = parse_url($xuiHost, PHP_URL_HOST);

                    switch ($linkType) {
                        case 'subscription':
                            if ($targetServer) {
                                $subDomain = $targetServer->subscription_domain ?? $serverAddress;
                                $subPort = $targetServer->subscription_port ?? 2053;
                                $subPath = $targetServer->subscription_path ?? '/sub/';
                                $isHttps = $targetServer->is_https ?? true;
                                $baseUrl = rtrim($subDomain, '/');
                                if ($subPort) $baseUrl .= ":{$subPort}";
                                $prot = $isHttps ? 'https' : 'http';
                                $configLink = "{$prot}://{$baseUrl}" . rtrim($subPath, '/') . '/' . $subId;
                            } else {
                                $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                                $configLink = $subBaseUrl . '/sub/' . $subId;
                            }
                            break;

                        case 'tunnel':
                            if (!$uuid) throw new \Exception("UUID extracted failed");
                            $tunnelAddress = $targetServer->tunnel_address;
                            $tunnelPort = $targetServer->tunnel_port ?? 443;

                            $tls = filter_var($targetServer->tunnel_is_https, FILTER_VALIDATE_BOOLEAN);

                            $params = ['type' => $streamSettings['network'] ?? 'tcp'];
                            if ($tls) {
                                $params['security'] = 'tls';
                                $params['sni'] = $tunnelAddress;
                            } else {
                                $params['security'] = 'none';
                                if($protocol === 'vless') $params['encryption'] = 'none';
                            }

                            if (($params['type'] ?? '') === 'ws') {
                                $params['path'] = $streamSettings['wsSettings']['path'] ?? '/';
                                $params['host'] = $streamSettings['wsSettings']['headers']['Host'] ?? $tunnelAddress;
                            }

                            $remarkText = $locationFlag . "-" . $uniqueUsername;
                            $qs = http_build_query($params);
                            $configLink = "vless://{$uuid}@{$tunnelAddress}:{$tunnelPort}?{$qs}#" . rawurlencode($remarkText);
                            break;

                        default: // single
                            if (!$uuid) throw new \Exception("UUID extracted failed");
                            $params = ['type' => $streamSettings['network'] ?? 'tcp', 'security' => $streamSettings['security'] ?? 'none'];
                            if ($params['security'] === 'tls') $params['sni'] = $serverAddress;
                            $qs = http_build_query(array_filter($params));
                            $configLink = "vless://{$uuid}@{$serverAddress}:{$inboundPort}?{$qs}#" . rawurlencode("Trial Account");
                            break;
                    }

                    if ($targetServer) $targetServer->increment('current_users');

                } else {
                    throw new \Exception($response['msg'] ?? 'خطا در ساخت کاربر در پنل X-UI');
                }
            }
            // --- PASARGAD ---
            elseif ($panelType === 'pasargad') {
                $pasargad = new PasargadService(
                    $settings->get('pasargad_host'),
                    $settings->get('pasargad_sudo_username'),
                    $settings->get('pasargad_sudo_password'),
                    $settings->get('pasargad_node_hostname')
                );
                
                $response = $pasargad->createUser([
                    'username' => $uniqueUsername,
                    'expire' => $expiresAt->timestamp,
                    'data_limit' => $dataLimitBytes,
                    'group_ids' => $settings->get('pasargad_trial_group_id') ? [(int)$settings->get('pasargad_trial_group_id')] : [1],
                ]);

                if ($response && !empty($response['subscription_url'])) {
                    $configLink = $response['subscription_url'];
                } else {
                    throw new \Exception('خطا در ارتباط با پنل پاسارگاد.');
                }
            } else {
                throw new \Exception('نوع پنل در تنظیمات مشخص نشده است.');
            }

            if ($configLink) {
                $user->increment('trial_accounts_taken');
                \Illuminate\Support\Facades\Cache::put("trial_link_{$user->id}", $configLink, now()->addMinutes(10));

                    // ساخت پیام کامل با ظاهر پریمیوم
                    $message = "🔑 *اشتراک تست شما با موفقیت ساخته شد*\n\n";
                    $message .= "👤 *نام کاربری شما:* \n`" . $this->escape($uniqueUsername) . "`\n\n";
                    $message .= "🌍 *موقعیت:* {$locationFlag} " . $this->escape($locationName) . "\n";
                    $message .= "📦 *حجم:* `{$volumeMB}` " . $this->escape("مگابایت") . "\n";
                    $message .= "⏳ *اعتبار:* `{$durationHours}` " . $this->escape("ساعت") . "\n\n";
                    $message .= "🔗 *لینک اشتراک شما:*\n";
                    $message .= "`{$configLink}`\n\n";
                    $message .= "👆🏻 " . $this->escape("برای کپی کردن لینک بالا کافیست روی آن بزنید!") . "\n\n";
                    $message .= $this->escape("⚠️ از نرم‌افزارهای v2rayNG (اندروید) یا V2Box (آیفون) استفاده کنید.");

                    // کیبورد با دکمه کپی و QR
                    $keyboard = Keyboard::make()->inline()
                        ->row([
                            Keyboard::inlineButton(['text' => '📋 لینک کپی سریع', 'callback_data' => "copy_trial_link_{$user->id}"]),
                            Keyboard::inlineButton(['text' => '📱 QR Code مجدد', 'callback_data' => "qr_trial_{$user->id}"])
                        ])
                        ->row([
                            Keyboard::inlineButton(['text' => '🛒 خرید سرویس دائمی', 'callback_data' => '/plans']),
                            Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
                        ]);

                    // تولید QR Code به صورت خودکار
                    $qrParams = ['size' => '400x400', 'data' => $configLink, 'ecc' => 'M', 'margin' => 10, 'format' => 'png'];
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query($qrParams);
                    
                    // ارسال به صورت Photo با کپشن
                    try {
                        Telegram::sendPhoto([
                            'chat_id' => $chatId,
                            'photo' => $qrUrl,
                            'caption' => $message,
                            'parse_mode' => 'MarkdownV2',
                            'reply_markup' => $keyboard
                        ]);
                    } catch (\Exception $qrEx) {
                        // اگر ارسال عکس شکست، حداقل متن را بفرست
                        Telegram::sendMessage([
                            'chat_id' => $chatId,
                            'text' => $message,
                            'parse_mode' => 'MarkdownV2',
                            'reply_markup' => $keyboard
                        ]);
                    }

                    Log::info('Trial account created successfully with QR', ['user_id' => $user->id, 'username' => $uniqueUsername]);
            }
        } catch (\Exception $e) {
            Log::error('Trial Account Creation Failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape('❌ خطا در ساخت اکانت تست. لطفاً بعداً تلاش کنید.'),
                'parse_mode' => 'MarkdownV2'
            ]);
        }
    }
    protected function sendOrEditMessage($chatId, $text, $keyboard, $messageId = null)
    {
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $text, // اصلاح: حذف اسکیپ خودکار برای حفظ فرمت مارک‌داون
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
            if (Str::contains($e->getMessage(), ['message is not modified'])) {
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

    protected function escape(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        // اول بک‌اسلش را اسکیپ می‌کنیم تا تداخل پیش نیاید
        $text = str_replace('\\', '\\\\', $text);
        return str_replace($chars, array_map(fn($char) => '\\' . $char, $chars), $text);
    }

    protected function escapeCode(string $text): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\\`'], $text);
    }

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

    protected function sendOrEditMainMenu($chatId, $text, $messageId = null)
    {
        $this->sendOrEditMessage($chatId, $text, $this->getMainMenuKeyboard(), $messageId);
    }

    protected function getReplyMainMenu(): Keyboard
    {
        try {
            $webAppUrl = route('webapp.index');
            $webAppUrl = trim($webAppUrl);

            if (!str_starts_with($webAppUrl, 'https://')) {
                Log::warning('WebApp URL is not HTTPS, skipping button', ['url' => $webAppUrl]);
                $webAppUrl = null;
            }
        } catch (\Exception $e) {
            Log::warning('Route webapp.index not found', ['error' => $e->getMessage()]);
            $webAppUrl = null;
        }

        $keyboard = [
            ['🛒 خرید سرویس', '🛠 سرویس‌های من'],
            ['💰 کیف پول', '📜 تاریخچه تراکنش‌ها'],
            ['💬 پشتیبانی', '🎁 دعوت از دوستان'],
            ['📚 راهنمای اتصال', '🧪 اکانت تست'],
        ];

        if ($webAppUrl) {
            array_unshift($keyboard, [
                ['text' => '📱 مدیریت حساب (Mini App)', 'web_app' => ['url' => $webAppUrl]]
            ]);
        }

        return Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }
}
