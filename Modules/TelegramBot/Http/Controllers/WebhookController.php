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
use Modules\Reseller\Models\Reseller;
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

    public function sendResellerRequestApprovedMessage(User $user): bool
    {
        $chatId = (string) $user->telegram_chat_id;
        if (!$chatId) {
            return false;
        }

        try {
            if ($this->settings->isEmpty()) {
                $this->settings = Setting::all()->pluck('value', 'key');
            }
            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                Log::error('Cannot send approval message: bot token is not set.');
                return false;
            }
            Telegram::setAccessToken($botToken);

            $text = "🎉 *درخواست نمایندگی شما تایید شد*\n\n";
            $text .= "اکنون حساب نمایندگی شما فعال شده و می‌توانید از طریق پنل نمایندگی، سرور و اکانت برای مشتریان خود بسازید.\n\n";
            $text .= "برای ورود به پنل نمایندگی، روی دکمه زیر بزنید:";

            $webAppUrl = route('webapp.agent.dashboard', ['user_id' => $chatId]);
            if (str_starts_with($webAppUrl, 'http://')) {
                $webAppUrl = str_replace('http://', 'https://', $webAppUrl);
            }

            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton([
                        'text' => '🚀 ورود به پنل نمایندگی (Mini App)',
                        'web_app' => ['url' => $webAppUrl],
                    ]),
                ])
                ->row([
                    Keyboard::inlineButton([
                        'text' => '🏠 بازگشت به منوی اصلی',
                        'callback_data' => '/start',
                    ]),
                ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape($text),
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $keyboard,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send reseller request approval Telegram message: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
            ]);
            return false;
        }
    }

    public function sendResellerRequestRejectedMessage(User $user, ?string $reason = null): bool
    {
        $chatId = (string) $user->telegram_chat_id;
        if (!$chatId) {
            return false;
        }

        try {
            if ($this->settings->isEmpty()) {
                $this->settings = Setting::all()->pluck('value', 'key');
            }
            $botToken = $this->settings->get('telegram_bot_token');
            if (!$botToken) {
                Log::error('Cannot send rejection message: bot token is not set.');
                return false;
            }
            Telegram::setAccessToken($botToken);

            $reasonText = $reason ?: 'مشخص نشده';

            $text = "❌ *درخواست نمایندگی شما تایید نشد*\n\n";
            $text .= "دلیل: {$reasonText}\n\n";
            $text .= "اگر مایل باشید می‌توانید برای توضیحات بیشتر یک تیکت پشتیبانی ثبت کنید.";

            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton([
                    'text' => '📝 ایجاد تیکت پشتیبانی',
                    'callback_data' => '/support_menu',
                ])])
                ->row([Keyboard::inlineButton([
                    'text' => '⬅️ بازگشت به منوی اصلی',
                    'callback_data' => '/start',
                ])]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $this->escape($text),
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => $keyboard,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send reseller request rejection Telegram message: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
            ]);
            return false;
        }
    }

    public function handle(Request $request)
    {
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




    protected function sendSiteCredentials(User $user, ?int $messageId = null)
    {
        $chatId = $user->telegram_chat_id;
        $username = $user->email; // Use email as username for now
        
        $loginUrl = $this->settings->get('site_login_url');
        if (empty($loginUrl)) {
            $loginUrl = route('login');
        }

        $message = "🔐 *اطلاعات ورود به پنل کاربری*\n\n";
        $message .= "👤 *نام کاربری:* `{$username}`\n";
        $message .= "🔑 *کلمه عبور:* (مخفی)\n\n";
        $message .= "🌐 *آدرس ورود:* \n" . $this->escape($loginUrl) . "\n\n";
        $message .= "⚠️ *نکته:* اگر رمز عبور خود را فراموش کرده‌اید یا اولین بار است که وارد می‌شوید، می‌توانید یک رمز عبور جدید بسازید.";

        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '🔄 ساخت رمز عبور جدید', 'callback_data' => 'generate_new_password']),
            ]);

        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function generateNewPassword(User $user, ?int $messageId = null)
    {
        $newPassword = Str::random(10); // Generate a 10-char random password
        $user->password = Hash::make($newPassword);
        $user->save();

        $chatId = $user->telegram_chat_id;
        $username = $user->email;
        
        $loginUrl = $this->settings->get('site_login_url');
        if (empty($loginUrl)) {
            $loginUrl = route('login');
        }

        $message = "✅ *رمز عبور جدید ساخته شد*\n\n";
        $message .= "👤 *نام کاربری:* `{$username}`\n";
        $message .= "🔑 *کلمه عبور جدید:* `{$newPassword}`\n\n";
        $message .= "🌐 *آدرس ورود:* \n" . $this->escape($loginUrl) . "\n\n";
        $message .= "⚠️ لطفاً این رمز را در جای امنی یادداشت کنید.";

        // We can just show a "Back" button or no button
        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '🗑 حذف پیام (امنیت)', 'callback_data' => '/cancel_action']),
            ]);

        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function handleAgentMenu($user)
    {
        $chatId = $user->telegram_chat_id;

        // چک کردن وضعیت نمایندگی
        $reseller = $user->reseller;
        $resellerRequest = $user->resellerRequest;

        if (!$reseller && !$resellerRequest) {
            // هنوز درخواست نداده - پیشنهاد ثبت نام
            $this->showAgentRegistration($user);
            return;
        }

        // اگر نماینده فعال هست، داشبورد رو نشون بده
        if ($reseller && $reseller->status === 'active') {
            $this->showAgentDashboard($reseller, $user);
            return;
        }

        // اگر درخواست داره، وضعیت درخواست رو بررسی کن
        if ($resellerRequest) {
            switch ($resellerRequest->status) {
            case 'pending':
                $message = "⏳ *درخواست نمایندگی در انتظار بررسی*\n\n";
                $message .= "درخواست شما برای نمایندگی در حال بررسی توسط ادمین است.\n";
                $message .= "لطفاً صبور باشید، پس از تایید به شما اطلاع داده خواهد شد.";

                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '🔄 بررسی مجدد وضعیت', 'callback_data' => 'agent_check_status'])])
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منو', 'callback_data' => '/start'])]);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($message),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
                break;

            case 'rejected':
                $message = "❌ *درخواست نمایندگی رد شد*\n\n";
                $message .= "دلیل: " . ($resellerRequest->rejection_reason ?: 'مشخص نشده') . "\n\n";
                $message .= "می‌توانید دوباره درخواست دهید.";

                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '📝 ثبت درخواست جدید', 'callback_data' => 'agent_register'])])
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/start'])]);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($message),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
                break;

            case 'approved':
                // درخواست تایید شده، اما هنوز نماینده فعال نشده
                $message = "✅ *درخواست نمایندگی شما تایید شده*\n\n";
                $message .= "لطفاً منتظر بمانید تا حساب نمایندگی شما فعال شود.";
                
                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '🔄 بررسی مجدد وضعیت', 'callback_data' => 'agent_check_status'])])
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منو', 'callback_data' => '/start'])]);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($message),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
                break;
        }
        
        // اگر نماینده غیرفعال یا تعلیق شده باشه
        if ($reseller && in_array($reseller->status, ['inactive', 'banned'])) {
            $message = "🚫 *نمایندگی شما غیرفعال شده*\n\n";
            $message .= $reseller->status === 'banned' ? "نمایندگی شما مسدود شده است." : "نمایندگی شما غیرفعال شده است.";
            $message .= "\nلطفاً با پشتیبانی تماس بگیرید.";

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($message),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $this->getReplyMainMenu()
                ]);
        }
        
        // بستن if مربوط به بررسی درخواست
        }
    }

    protected function getSecureWebAppUrl(string $path): string
    {
        // می‌گیره: /agent/register
        // برمی‌گردونه: https://xxx.ngrok-free.app/agent/register

        $url = config('app.url') . $path;

        // اجبار به HTTPS
        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        // حذف اسلش اضافی
        $url = rtrim($url, '/');

        return $url;
    }


    /**
     * 📝 نمایش فرم ثبت نام نمایندگی
     */
    protected function showAgentRegistration($user)
    {
        $chatId = $user->telegram_chat_id;

        // ✅ خواندن قیمت از جدول reseller_plans
        $agentPlan = \Modules\Reseller\Models\ResellerPlan::where('type', 'quota')
            ->where('is_active', true)
            ->first();

        $registrationFee = $agentPlan ? $agentPlan->price : 30000;
        $maxAccounts = $agentPlan ? $agentPlan->account_limit : 16;

        $message = "🏢 *درخواست نمایندگی*\n\n";
        $message .= "با عضویت در سیستم نمایندگی می‌توانید:\n";
        $message .= "✅ تا " . $this->escape($maxAccounts) . " اکانت بسازید و بفروشید\n";
        $message .= "✅ سرور اختصاصی خریداری کنید\n";
        $message .= "✅ از تعرفه ویژه نمایندگان استفاده کنید\n\n";
        $message .= "💰 *هزینه ثبت‌نام: " . $this->escape(number_format($registrationFee)) . " تومان*\n";
        $message .= "📱 برای ثبت درخواست، مینی‌اپ را باز کنید:";

        $webAppUrl = route('webapp.agent.register', ['user_id' => $chatId]);
        $webAppUrl = str_replace('http://', 'https://', $webAppUrl);

        Log::info('Agent WebApp URL generated', [
            'url' => $webAppUrl,
            'registration_fee' => $registrationFee,
            'max_accounts' => $maxAccounts,
        ]);

        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton([
                'text' => '📱 ورود به مینی‌اپ نمایندگی',
                'web_app' => ['url' => $webAppUrl]
            ])])
            ->row([Keyboard::inlineButton([
                'text' => '⬅️ بازگشت',
                'callback_data' => '/start'
            ])]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $this->escape($message),
            'parse_mode' => 'MarkdownV2',
            'reply_markup' => $keyboard
        ]);
    }
    /**
     * 📊 داشبورد نماینده تایید شده
     */
    protected function showAgentDashboard($reseller, $user)
    {
        $chatId = $user->telegram_chat_id;


        $agentAccountPlan = \Modules\Reseller\Models\ResellerPlan::where('type', 'pay_as_you_go')
            ->where('is_active', true)
            ->first();

        $accountPrice = $agentAccountPlan ? $agentAccountPlan->price_per_account : 30000; // fallback

        $balance = number_format($reseller->wallet ? $reseller->wallet->balance : 0);
        $createdCount = $reseller->accounts()->count();
        $maxCount = $reseller->max_accounts;

        $message = "🏢 *پنل مدیریت نمایندگی*\n\n";
        $message .= "👤 نام: {$this->escape($user->name)}\n";
        $message .= "💰 موجودی: *{$balance} تومان*\n";
        $message .= "📊 وضعیت اکانت‌ها: *{$createdCount} / {$maxCount}*\n";
        $message .= "💸 قیمت هر اکانت: *" . number_format($accountPrice) . " تومان*\n\n";
        $message .= "👇 برای مدیریت کامل روی دکمه زیر کلیک کنید:";



        $webAppUrl = route('webapp.agent.dashboard', ['user_id' => $chatId]);

        if (str_starts_with($webAppUrl, 'http://')) {
            $webAppUrl = str_replace('http://', 'https://', $webAppUrl);
        }


        // ✅ ساخت دکمه با قابلیت web_app
        $keyboard = Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton([
                    'text' => '🚀 ورود به پنل نمایندگی (Mini App)',
                    'web_app' => ['url' => $webAppUrl] // این خط باعث باز شدن مینی‌اپ می‌شود
                ])
            ])
            ->row([
                Keyboard::inlineButton(['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => '/start'])
            ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $this->escape($message),
            'parse_mode' => 'MarkdownV2',
            'reply_markup' => $keyboard
        ]);
    }


    protected function handleTextMessage($update)
    {
        $message = $update->getMessage();
        if (!$message) {
            return;
        }
        
        $chat = $message->getChat();
        if (!$chat) {
            return;
        }
        
        $chatId = $chat->getId();
        $text = trim($message->getText() ?? '');
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user && !$this->isUserMemberOfChannel($user)) {
            $this->showChannelRequiredMessage($chatId);
            return;
        }

        if (!$user) {
            $from = $message->getFrom();
            $userFirstName = $from ? $from->getFirstName() ?? 'کاربر' : 'کاربر';
            $password = Str::random(10);
            $user = User::create([
                'name' => $userFirstName,
                'email' => $chatId . '@telegram.user',
                'password' => Hash::make($password),
                'telegram_chat_id' => $chatId,
                'referral_code' => Str::random(8),
            ]);

            if (!$this->isUserMemberOfChannel($user)) {
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

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $welcomeMessage,
                'reply_markup' => $this->getReplyMainMenu()
            ]);
            return;
        }

        if ($user->bot_state) {
            if ($user->bot_state === 'awaiting_deposit_amount') {
                $this->processDepositAmount($user, $text);
            } elseif (Str::startsWith($user->bot_state, 'awaiting_new_ticket_') || Str::startsWith($user->bot_state, 'awaiting_ticket_reply')) {
                $this->processTicketConversation($user, $text, $update);
            } elseif (Str::startsWith($user->bot_state, 'awaiting_discount_code|')) {
                $orderId = (int) Str::after($user->bot_state, 'awaiting_discount_code|');
                $this->processDiscountCode($user, $orderId, $text);
            }
            elseif (Str::startsWith($user->bot_state, 'awaiting_username_for_order|')) {
                $planId = (int) Str::after($user->bot_state, 'awaiting_username_for_order|');
                $this->processUsername($user, $planId, $text);
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
            case '🏢 نمایندگی':
                $this->handleAgentMenu($user);
                break;
            case '🔐 اطلاعات ورود به سایت':
                $this->sendSiteCredentials($user);
                break;


            case '/start':
                $telegramSettings = TelegramBotSetting::pluck('value', 'key');
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

    protected function promptForUsername(User $user, int $planId, ?int $messageId = null, ?int $locationId = null)
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
        $message .= "لطفاً یک نام کاربری انگلیسی برای سرویس خود وارد کنید.\n";
        $message .= "🔹 فقط حروف انگلیسی و اعداد مجاز است (حداقل ۳ حرف).\n";
        $message .= "🔹 مثال: `arvin123` یا `myvpn`";

        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    /**
     * ارسال مجدد لینک اکانت تست (برای کپی آسان)
     */
    protected function handleTrialCopyLink(User $user, ?int $messageId = null)
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
    protected function sendTrialQRCode(User $user, ?int $messageId = null)
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
        if (!$callbackQuery) {
            return;
        }
        
        $message = $callbackQuery->getMessage();
        if (!$message) {
            return;
        }
        
        $chat = $message->getChat();
        if (!$chat) {
            return;
        }
        
        $chatId = $chat->getId();
        $messageId = $message->getMessageId();
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

        if (!$user) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => $this->escape("❌ کاربر یافت نشد. لطفاً با دستور /start ربات را مجدداً راه‌اندازی کنید."), 'parse_mode' => 'MarkdownV2']);
            return;
        }

        if (Str::startsWith($data, 'show_duration_')) {
            $durationDays = (int)Str::after($data, 'show_duration_');
            $this->sendPlansByDuration($chatId, $durationDays, $messageId);
            return;
        }

        if (Str::startsWith($data, 'show_service_')) {
            $orderId = (int) Str::after($data, 'show_service_');
            $this->showServiceDetails($user, $orderId, $messageId);
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
                    $plan = Plan::find($planId);
                    $serverType = $plan ? ($plan->server_type ?? 'all') : 'all';

                    if ($location) {
                        $query = $location->servers()->where('is_active', true);
                        
                        if ($serverType !== 'all') {
                            $query->where('type', $serverType);
                        }

                        $totalCapacity = $query->sum('capacity');
                        $totalUsed = $query->sum('current_users');

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
            $planId = (int) Str::after($data, 'buy_plan_');

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
        elseif (Str::startsWith($data, 'pay_wallet_')) {
            $input = Str::after($data, 'pay_wallet_');
            $this->processWalletPayment($user, $input, $messageId);
        } elseif (Str::startsWith($data, 'pay_card_')) {
            $orderId = (int) Str::after($data, 'pay_card_');
            $this->sendCardPaymentInfo($chatId, $orderId, $messageId);
        }

        elseif (Str::startsWith($data, 'copy_trial_link_')) {
            $userId = (int) Str::after($data, 'copy_trial_link_');
            $this->handleTrialCopyLink($user, $messageId);
        }
        elseif (Str::startsWith($data, 'qr_trial_')) {
            $this->sendTrialQRCode($user, $messageId);
        }

        elseif (Str::startsWith($data, 'enter_discount_')) {
            $orderId = (int) Str::after($data, 'enter_discount_');
            $this->promptForDiscount($user, $orderId, $messageId);
        }
        elseif (Str::startsWith($data, 'copy_link_')) {
            $orderId = (int) Str::after($data, 'copy_link_');
            $this->handleCopyLinkRequest($user, $orderId);
        }

        elseif (Str::startsWith($data, 'remove_discount_')) {
            $orderId = (int) Str::after($data, 'remove_discount_');
            $this->removeDiscount($user, $orderId, $messageId);
        } elseif (Str::startsWith($data, 'qrcode_order_')) {
            $orderId = (int) Str::after($data, 'qrcode_order_');
            $this->sendQRCodeForOrder($user, $orderId);
        } elseif (Str::startsWith($data, 'renew_order_')) {
            $originalOrderId = (int) Str::after($data, 'renew_order_');
            $this->startRenewalPurchaseProcess($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'renew_pay_wallet_')) {
            $originalOrderId = (int) Str::after($data, 'renew_pay_wallet_');
            $this->processRenewalWalletPayment($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'renew_pay_card_')) {
            $originalOrderId = (int) Str::after($data, 'renew_pay_card_');
            $this->handleRenewCardPayment($user, $originalOrderId, $messageId);
        } elseif (Str::startsWith($data, 'deposit_amount_')) {
            $amount = (int) Str::after($data, 'deposit_amount_');
            $this->processDepositAmount($user, $amount, $messageId);
        } elseif ($data === '/deposit_custom') {
            $this->promptForCustomDeposit($user, $messageId);
        } elseif (Str::startsWith($data, 'close_ticket_')) {
            $ticketId = (int) Str::after($data, 'close_ticket_');
            $callbackQueryId = $callbackQuery ? $callbackQuery->getId() : null;
            $this->closeTicket($user, $ticketId, $messageId, $callbackQueryId);
        }

        elseif (Str::startsWith($data, 'agent_')) {
            $this->handleAgentCallbacks($user, $data, $messageId);
        }

        elseif (Str::startsWith($data, 'reply_ticket_')) {
            $ticketId = (int) Str::after($data, 'reply_ticket_');
            $this->promptForTicketReply($user, $ticketId, $messageId);
        } elseif ($data === '/support_new') {
            $this->promptForNewTicket($user, $messageId);
        } elseif ($data === 'generate_new_password') {
            $this->generateNewPassword($user, $messageId);
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

    /**
     * Handlerهای مربوط به نمایندگی
     */
    protected function handleAgentCallbacks($user, $data, $messageId)
    {
        $chatId = $user->telegram_chat_id;
        $reseller = $user->reseller;

        switch ($data) {
            case 'agent_check_status':
                // رفرش وضعیت
                $this->handleAgentMenu($user);
                try {
                    Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
                } catch (\Exception $e) {}
                break;

            case 'agent_register':
                // باز کردن مینی‌اپ ثبت نام
                $this->showAgentRegistration($user);
                break;

            case 'agent_deposit':
                // شارژ کیف پول
                if (!$reseller || $reseller->status !== 'active') {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $this->escape('❌ شما نماینده فعال نیستید.'),
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    return;
                }

                try {
                    $webAppUrl = route('webapp.agent.deposit');
                } catch (\Exception $e) {
                    $webAppUrl = config('app.url') . '/agent/deposit';
                }

                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '💳 ورود به صفحه شارژ', 'web_app' => ['url' => $webAppUrl]])])
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])]);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape('💰 شارژ کیف پول نمایندگی\n\nاز طریق لینک زیر اقدام کنید:'),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
                break;

            case 'agent_buy_server':
                // خرید سرور
                if (!$reseller || $reseller->status !== 'active') {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $this->escape('❌ شما نماینده فعال نیستید.'),
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    return;
                }

                try {
                    $webAppUrl = route('webapp.agent.buy-server');
                } catch (\Exception $e) {
                    $webAppUrl = config('app.url') . '/agent/buy-server';
                }

                $message = "🖥 *خرید سرور اختصاصی*\n\n";
                $message .= "موجودی فعلی: " . number_format($reseller->wallet ? $reseller->wallet->balance : 0) . " تومان\n\n";
                $message .= "پلن‌های موجود:\n";
                $message .= "• سرور ۱۰۰ نفره: ۵۰۰,۰۰۰ تومان\n";
                $message .= "• سرور ۲۰۰ نفره: ۹۰۰,۰۰۰ تومان\n";
                $message .= "• سرور ۵۰۰ نفره: ۲,۰۰۰,۰۰۰ تومان\n\n";
                $message .= "برای خرید وارد لینک زیر شوید:";

                $keyboard = Keyboard::make()->inline()
                    ->row([Keyboard::inlineButton(['text' => '🖥 ورود به صفحه خرید سرور', 'web_app' => ['url' => $webAppUrl]])])
                    ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])]);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $this->escape($message),
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => $keyboard
                ]);
                break;

            case 'agent_create_account':
                // ساخت اکانت جدید (مستقیم یا از طریق مینی‌اپ)
                if (!$reseller || $reseller->status !== 'active') {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $this->escape('❌ شما نماینده فعال نیستید.'),
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    return;
                }

                if ($reseller->accounts()->count() >= $reseller->max_accounts && $reseller->servers()->where('is_active', true)->count() === 0) {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $this->escape('⚠️ ظرفیت اکانت‌های شما تکمیل است!\n\nبرای ساخت اکانت بیشتر، ابتدا سرور خریداری کنید.'),
                        'parse_mode' => 'MarkdownV2',
                        'reply_markup' => Keyboard::make()->inline()
                            ->row([Keyboard::inlineButton(['text' => '🖥 خرید سرور', 'callback_data' => 'agent_buy_server'])])
                            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])])
                    ]);
                    return;
                }

                // اینجا می‌تونی مستقیم فرآیند ساخت اکانت رو شروع کنی
                // یا کاربر رو به مینی‌اپ هدایت کنی
                $this->startAgentAccountCreation($user, $reseller, $messageId);
                break;

            case 'agent_reports':
                // نمایش گزارشات
                $this->showAgentReports($reseller, $chatId, $messageId);
                break;

            case 'agent_back_to_dashboard':
                // بازگشت به داشبورد
                try {
                    Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $messageId]);
                } catch (\Exception $e) {}
                $this->showAgentDashboard($reseller, $user);
                break;

            default:
                if (Str::startsWith($data, 'agent_select_server_')) {
                    $serverId = (int) Str::after($data, 'agent_select_server_');
                    $this->processAgentServerSelection($user, $reseller, $serverId, $messageId);
                }
                break;
        }
    }

    /**
     * شروع فرآیند ساخت اکانت برای نماینده
     */
    protected function startAgentAccountCreation($user, $reseller, $messageId)
    {
        $chatId = $user->telegram_chat_id;

        // گرفتن سرورهای فعال نماینده
        $servers = $reseller->servers()->where('is_active', true)->get();

        if ($servers->isEmpty()) {
            // استفاده از سرورهای اصلی سیستم (با قیمت نمایندگی)
            $this->sendPlans($chatId, $messageId, true); // true = isAgentMode
            return;
        }

        // نمایش لیست سرورهای خود نماینده
        $message = "🖥 *انتخاب سرور*\n\n";
        $message .= "لطفاً سرور مورد نظر برای ساخت اکانت را انتخاب کنید:\n\n";

        $keyboard = Keyboard::make()->inline();

        foreach ($servers as $server) {
            $available = $server->capacity - $server->current_users;
            $status = $available > 0 ? "🟢 {$available} ظرفیت" : "🔴 تکمیل";

            $message .= "• {$server->name}: {$status}\n";

            $keyboard->row([
                Keyboard::inlineButton([
                    'text' => $server->name . ' (' . $status . ')',
                    'callback_data' => 'agent_select_server_' . $server->id
                ])
            ]);
        }

        $message .= "\n💰 هزینه هر اکانت: " . number_format($reseller->plan ? $reseller->plan->price_per_account : 30000) . " تومان";
        $message .= "\n💳 موجودی: " . number_format($reseller->wallet ? $reseller->wallet->balance : 0) . " تومان";

        $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])]);

        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    /**
     * پردازش انتخاب سرور برای نماینده
     */
    protected function processAgentServerSelection(User $user, Reseller $reseller, int $serverId, int $messageId)
    {
        $chatId = $user->telegram_chat_id;
        
        // بررسی اینکه سرور متعلق به نماینده هست یا نه
        $server = $reseller->servers()->find($serverId);
        
        if (!$server) {
            $message = "❌ *سرور نامعتبر*\n\n";
            $message .= "این سرور یا وجود ندارد یا به شما تعلق ندارد.";
            
            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton(['text' => '🔄 تلاش مجدد', 'callback_data' => 'agent_create_account'])])
                ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])]);
            
            $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
            return;
        }
        
        // بررسی ظرفیت سرور
        $currentAccounts = $reseller->accounts()->where('server_id', $serverId)->count();
        $maxCapacity = $server->max_accounts_per_reseller ?? 50;
        
        if ($currentAccounts >= $maxCapacity) {
            $message = "❌ *ظرفیت سرور تکمیل شده*\n\n";
            $message .= "ظرفیت این سرور برای نمایندگان تکمیل شده است.\n";
            $message .= "لطفاً سرور دیگری انتخاب کنید.";
            
            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton(['text' => '🔄 انتخاب سرور دیگر', 'callback_data' => 'agent_create_account'])])
                ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])]);
            
            $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
            return;
        }
        
        // محاسبه هزینه
        $plan = $reseller->plan;
        $costPerAccount = $plan ? $plan->price_per_account : 30000;
        $currentBalance = $reseller->wallet ? $reseller->wallet->balance : 0;
        
        if ($currentBalance < $costPerAccount) {
            $message = "❌ *موجودی کافی نیست*\n\n";
            $message .= "هزینه ساخت اکانت: *" . number_format($costPerAccount) . " تومان*\n";
            $message .= "موجودی فعلی: *" . number_format($currentBalance) . " تومان*\n\n";
            $message .= "لطفاً ابتدا کیف پول خود را شارژ کنید.";
            
            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton(['text' => '💳 شارژ کیف پول', 'callback_data' => 'agent_deposit'])])
                ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => 'agent_back_to_dashboard'])]);
            
            $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
            return;
        }
        
        // نمایش اطلاعات برای تایید نهایی
        $message = "✅ *تایید ساخت اکانت*\n\n";
        $message .= "🖥 سرور: *{$server->name}*\n";
        $message .= "📍 موقعیت: *{$server->location}*\n";
        $message .= "💰 هزینه: *" . number_format($costPerAccount) . " تومان*\n";
        $message .= "💳 موجودی پس از کسر: *" . number_format($currentBalance - $costPerAccount) . " تومان*\n\n";
        $message .= "آیا از ساخت اکانت اطمینان دارید؟";
        
        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton(['text' => '✅ تایید و ساخت', 'callback_data' => 'agent_confirm_create_' . $serverId])])
            ->row([Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => 'agent_back_to_dashboard'])]);
        
        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    /**
     * 📊 نمایش گزارشات نماینده
     */
    protected function showAgentReports(Reseller $reseller, int $chatId, int $messageId)
    {
        $totalAccounts = $reseller->accounts()->count();
        $activeAccounts = $reseller->accounts()->where('status', 'active')->count();
        $expiredAccounts = $reseller->accounts()->where('status', 'expired')->count();
        $totalRevenue = $reseller->transactions()
            ->where('type', 'purchase')
            ->sum('amount');
        $currentBalance = $reseller->wallet ? $reseller->wallet->balance : 0;
        
        $message = "📊 *گزارشات نمایندگی*\n\n";
        $message .= "👤 نماینده: {$reseller->user->name}\n";
        $message .= "📅 تاریخ گزارش: " . now()->format('Y/m/d') . "\n\n";
        $message .= "📈 *آمار کل:*\n";
        $message .= "• کل اکانت‌ها: *{$totalAccounts}*\n";
        $message .= "• اکانت‌های فعال: *{$activeAccounts}*\n";
        $message .= "• اکانت‌های منقضی: *{$expiredAccounts}*\n\n";
        $message .= "💰 *مالی:*\n";
        $message .= "• درآمد کل: *" . number_format($totalRevenue) . " تومان*\n";
        $message .= "• موجودی فعلی: *" . number_format($currentBalance) . " تومان*\n\n";
        $message .= "📊 *عملکرد:*\n";
        
        if ($totalAccounts > 0) {
            $activePercentage = round(($activeAccounts / $totalAccounts) * 100);
            $message .= "• نرخ فعال بودن: *{$activePercentage}%*\n";
        }
        
        $message .= "\n_برای مشاهده جزئیات بیشتر به پنل نمایندگی مراجعه کنید._";

        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton(['text' => '🔄 بروزرسانی گزارش', 'callback_data' => 'agent_reports'])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به داشبورد', 'callback_data' => 'agent_back_to_dashboard'])]);

        $this->sendOrEditMessage($chatId, $message, $keyboard, $messageId);
    }

    protected function promptForLocation($user, $planId, $messageId)
    {
        $plan = Plan::find($planId);
        $serverType = $plan ? ($plan->server_type ?? 'all') : 'all';

        $settings = Setting::all()->pluck('value', 'key');
        $showCapacity = filter_var($settings->get('ms_show_capacity', true), FILTER_VALIDATE_BOOLEAN);
        $hideFull = filter_var($settings->get('ms_hide_full_locations', false), FILTER_VALIDATE_BOOLEAN);

        // ✅ فیلتر کردن سرورها بر اساس نوع پلن
        $locations = \Modules\MultiServer\Models\Location::where('is_active', true)
            ->with(['servers' => function ($query) use ($serverType) {
                $query->where('is_active', true);
                if ($serverType !== 'all') {
                    $query->where('type', $serverType);
                }
            }])
            ->get();

        $keyboard = Keyboard::make()->inline();
        $hasAvailableLocation = false;

        foreach ($locations as $loc) {
            // ✅ استفاده از سرورهای فیلتر شده
            $relevantServers = $loc->servers;

            if ($relevantServers->isEmpty()) {
                continue;
            }

            $totalCapacity = $relevantServers->sum('capacity');
            $totalUsed = $relevantServers->sum('current_users');
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
        if (!$message) {
            return;
        }
        
        $chat = $message->getChat();
        if (!$chat) {
            return;
        }
        
        $chatId = $chat->getId();
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
            $orderId = (int) Str::after($user->bot_state, 'waiting_receipt_');
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

                    $adminChatId = $this->settings->get('telegram_admin_chat_id');
                    if ($adminChatId) {
                        $orderType = $order->renews_order_id ? 'تمدید سرویس' : ($order->plan_id ? 'خرید سرویس' : 'شارژ کیف پول');

                        $adminMessage = "🧾 *رسید جدید برای سفارش \\#{$orderId}*\n\n";
                        $adminMessage .= "*کاربر:* " . $this->escape($user->name) . " \\(ID: `{$user->id}`\\)\n";
                        $adminMessage .= "*مبلغ:* " . $this->escape(number_format($order->amount) . ' تومان') . "\n";
                        $adminMessage .= "*نوع سفارش:* " . $this->escape($orderType) . "\n\n";
                        $adminMessage .= $this->escape("لطفا در پنل مدیریت بررسی و تایید کنید.");

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
                $serverType = $plan->server_type ?? 'all';

                // پیدا کردن خلوت‌ترین سرور فعال با توجه به نوع پلن
                $query = \Modules\MultiServer\Models\Server::where('location_id', $locationId)
                    ->where('is_active', true)
                    ->whereRaw('current_users < capacity');
                
                if ($serverType !== 'all') {
                    $query->where('type', $serverType);
                }

                $bestServer = $query->orderBy('current_users', 'asc')->first();

                if ($bestServer) {
                    $serverId = $bestServer->id;
                } else {
                    // اگر در لوکیشن انتخابی سروری نبود، سعی کن از هر لوکیشنی یک سرور مناسب پیدا کنی
                    $fallbackQuery = \Modules\MultiServer\Models\Server::where('is_active', true)
                        ->whereRaw('current_users < capacity');
                    if ($serverType !== 'all') {
                        $fallbackQuery->where('type', $serverType);
                    }
                    $fallbackServer = $fallbackQuery->orderBy('current_users', 'asc')->first();
                    
                    if ($fallbackServer) {
                        $serverId = $fallbackServer->id;
                    } else {
                        $user->update(['bot_state' => null]);
                        Telegram::sendMessage([
                            'chat_id' => $user->telegram_chat_id,
                            'text' => $this->escape("❌ متأسفانه ظرفیت تمام سرورها تکمیل شده است."),
                            'parse_mode' => 'MarkdownV2'
                        ]);
                        return;
                    }
                }
            } else {
                // اگر لوکیشن انتخاب نشده بود (مثلاً state پاک شده)، سعی کن بهترین سرور را پیدا کنی
                $serverType = $plan->server_type ?? 'all';
                $fallbackQuery = \Modules\MultiServer\Models\Server::where('is_active', true)
                    ->whereRaw('current_users < capacity');
                if ($serverType !== 'all') {
                    $fallbackQuery->where('type', $serverType);
                }
                $fallbackServer = $fallbackQuery->orderBy('current_users', 'asc')->first();

                if ($fallbackServer) {
                    $serverId = $fallbackServer->id;
                } else {
                    $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ لطفاً ابتدا لوکیشن را انتخاب کنید.", $messageId);
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
        if (!$plan) {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ اطلاعات سفارش نامعتبر است.");
            return;
        }
        
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
                    $orderId = (int) Str::after($input, 'order_');
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
            // اطمینان از در دسترس بودن سفارش و پلن
            if (!$order || !$plan) {
                throw new \RuntimeException('Order or plan not available after wallet payment processing.');
            }

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
            }

            // ساخت پیام کامل
            $message = "✅ *خرید موفق!*\n\n";
            $message .= "📦 *پلن:* `{$this->escape($plan->name)}`\n";
            $message .= "🌍 *موقعیت:* {$locationFlag} {$this->escape($locationName)}\n";
            $message .= "🖥 *سرور:* {$this->escape($serverName)}\n";
            $message .= "💾 *حجم:* {$plan->volume_gb} گیگابایت\n";
            $message .= "📅 *مدت:* {$plan->duration_days} روز\n";

            $expiresAt = $order->expires_at;
            $expiresText = $expiresAt ? $expiresAt->format('Y/m/d H:i') : '-';
            $message .= "⏳ *انقضا:* `{$expiresText}`\n";
            $message .= "👤 *یوزرنیم:* `{$order->panel_username}`\n\n";
            $message .= "🔗 *لینک کانفیگ شما:*\n";
            $message .= "{$this->escape($link)}\n\n";
            $message .= "⚠️ روی لینک بالا کلیک کنید تا کپی شود";

            // کیبورد با دکمه کپی لینک
            $keyboard = Keyboard::make()->inline()
                ->row([
                    Keyboard::inlineButton(['text' => '📋 کپی لینک کانفیگ', 'callback_data' => "copy_link_{$order->id}"]),
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
                        // ✅ اصلاح: فیلتر کردن بر اساس نوع سرور پلن
                        $plan = $order->plan;
                        $serverType = $plan ? ($plan->server_type ?? 'all') : 'all';

                        $query = \Modules\MultiServer\Models\Server::where('location_id', $locationId)
                            ->where('is_active', true)
                            ->whereRaw('current_users < capacity');
                        
                        if ($serverType !== 'all') {
                            $query->where('type', $serverType);
                        }

                        $bestServer = $query->orderBy('current_users', 'asc')->first();

                        if ($bestServer) {
                            $order->update(['server_id' => $bestServer->id]);
                            Log::info("Fixed missing server_id for order #{$order->id} with server #{$bestServer->id} ({$bestServer->type})");
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

            $message = "🚀 *انتخاب سرویس VPN*\n\n";
            $message .= "لطفاً مدت‌زمان سرویس مورد نظر را انتخاب کنید:\n\n";
            $message .= "👇 یکی از گزینه‌های زیر را بزنید:";

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

            $this->sendOrEditMessage($chatId, $this->escape("❌ خطایی در بارگذاری پلن‌ها رخ داد."), $keyboard, $messageId);
        }
    }

    protected function generateDurationLabel(int $days): string
    {
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

            $this->sendOrEditMessage($chatId, $this->escape("❌ خطایی در بارگذاری پلن‌ها رخ داد."), $keyboard, $messageId);
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

            // ✅ ارسال عکس با InputFile
            Telegram::sendPhoto([
                'chat_id' => $user->telegram_chat_id,
                'photo' => InputFile::create($tempFile, "qr_code_{$order->id}.png"),
                'caption' => $this->escape("📱 QR Code برای سرویس #{$order->id}\n\n" .
                    "👤 نام کاربری: `{$order->panel_username}`\n" .
                    "🔗 لینک: {$configLink}\n\n" .
                    "⚠️ برای کپی روی لینک بالا کلیک کنید."),
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
                'text' => $this->escape("❌ خطا در تولید QR Code.\n\n🔧 لطفاً از لینک زیر استفاده کنید:\n`{$configLink}`"),
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

    /**
     * ✅ حذف: این متد دقیقاً در انتهای فایل تکراری بود و حذف شده است.
     * نسخه اصلی در انتهای فایل نگه داشته شد.
     */
    /*
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

        // ✅ اصلاح: حذف space های اضافی
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
    */

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
        $expiresAt = $order->expires_at ? Carbon::parse($order->expires_at) : null;
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
                $panelType = $targetServer->type ?? 'xui';
                
                // X-UI credentials
                $xuiHost = $targetServer->full_host;
                $xuiUser = $targetServer->username;
                $xuiPass = $targetServer->password;
                $inboundId = $targetServer->inbound_id;

                // Marzban credentials
                $marzbanHost = $targetServer->full_host;
                $marzbanUser = $targetServer->username;
                $marzbanPass = $targetServer->password;
                // Use node hostname if set, otherwise fallback to panel host
                $marzbanNode = $targetServer->marzban_node_hostname ?? $marzbanHost;

                Log::info("🚀 Provisioning on MultiServer", [
                    'server_name' => $targetServer->name,
                    'server_id' => $targetServer->id,
                    'type' => $panelType,
                    'host' => parse_url($xuiHost, PHP_URL_HOST),
                    'link_type' => $targetServer->link_type ?? 'not set'
                ]);
            }
        }

        try {
            // ==========================================
            // پنل MARZBAN
            // ==========================================
            if ($panelType === 'marzban') {
                if ($isMultiServer) {
                    $marzban = new MarzbanService(
                        $marzbanHost ?? '',
                        $marzbanUser ?? '',
                        $marzbanPass ?? '',
                        $marzbanNode ?? ''
                    );
                } else {
                    $marzban = new MarzbanService(
                        $settings->get('marzban_host') ?? '',
                        $settings->get('marzban_sudo_username') ?? '',
                        $settings->get('marzban_sudo_password') ?? '',
                        $settings->get('marzban_node_hostname') ?? ''
                    );
                }
                
                $response = $marzban->createUser([
                    'username' => $uniqueUsername,
                    'expire' => $expiresAt ? $expiresAt->getTimestamp() : null,
                    'data_limit' => $plan->volume_gb * 1024 * 1024 * 1024,
                ]);

                if (!empty($response['subscription_url'])) {
                    $configData['link'] = $marzban->generateSubscriptionLink($response);
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
                    'expiryTime' => $expiresAt ? $expiresAt->getTimestamp() * 1000 : null,
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

                } else {
                    throw new \Exception($response['msg'] ?? 'Error creating user in X-UI');
                }
            } else {
                throw new \Exception("Panel type not supported");
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
        $this->sendOrEditMessage($user->telegram_chat_id, $this->escape("💳 لطفاً مبلغ دلخواه خود را (به تومان، حداقل ۱۰,۰۰۰) در یک پیام ارسال کنید:"), $keyboard, $messageId);
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
                } else {
                    $errorMsg = $response['msg'] ?? 'Unknown Error';
                    throw new \Exception("❌ خطا در بروزرسانی کلاینت: " . $errorMsg);
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
                $subject = Str::after($state, 'awaiting_new_ticket_message|'); // This one is string, not int
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
                $ticketId = (int) Str::after($state, 'awaiting_ticket_reply|');
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

        $plan = $order->plan;
        if (!$plan) {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ اطلاعات سفارش نامعتبر است.", $messageId);
            return;
        }

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
        $message .= "📦 *پلن:* `{$this->escape($plan->name)}`\n";
        $message .= "🌍 *موقعیت:* {$locationFlag} {$this->escape($locationName)}\n";
        $message .= "🖥 *سرور:* {$this->escape($serverName)}\n";
        $message .= "💾 *حجم:* {$plan->volume_gb} گیگابایت\n";
        $message .= "📅 *مدت:* {$plan->duration_days} روز\n";

        $expiresAt = $order->expires_at ? Carbon::parse($order->expires_at) : null;
        $expiresText = $expiresAt ? $expiresAt->format('Y/m/d H:i') : '-';
        $message .= "⏳ *انقضا:* `{$expiresText}`\n";
        $message .= "👤 *یوزرنیم:* `{$order->panel_username}`\n\n";
        $message .= "🔗 *لینک کانفیگ شما:*\n";
        $message .= "{$this->escape($link)}\n\n";
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
        $message = $update->getMessage();
        if (!$message) {
            return null;
        }
        
        $photos = $message->getPhoto();
        if (!$photos) {
            return null;
        }
        
        $photo = collect($photos)->last();
        if(!$photo) return null;

        $botToken = $this->settings->get('telegram_bot_token');
        try {
            $file = Telegram::getFile(['file_id' => $photo->getFileId()]);
            $filePath = method_exists($file, 'getFilePath') ? $file->getFilePath() : ($file['file_path'] ?? null);
            if(!$filePath) { throw new \Exception('File path not found in Telegram response.'); }

            // ✅ اصلاح: حذف space های اضافی
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

    /**
     * ✅ نسخه اصلی sendReferralMenu (متد تکراری دیگری در انتهای فایل وجود داشت که حذف شد)
     */
    protected function sendReferralMenu($user, $messageId = null)
    {
        try {
            $botInfo = Telegram::getMe();
            $botUsername = $botInfo->getUsername();
        } catch (\Exception $e) {
            $this->sendOrEditMainMenu($user->telegram_chat_id, "❌ خطا در دریافت اطلاعات ربات", $messageId);
            return;
        }

        $referralCode = $user->referral_code ?? Str::random(8);
        if (!$user->referral_code) {
            $user->update(['referral_code' => $referralCode]);
        }

        // ✅ اصلاح: حذف space های اضافی
        $referralLink = "https://t.me/{$botUsername}?start={$referralCode}";
        $referrerReward = number_format((int) $this->settings->get('referral_referrer_reward', 0));
        $referralCount = $user->referrals()->count();

        $message = "🎁 *دعوت از دوستان*\n\n";
        $message .= "با اشتراک‌گذاری لینک زیر، دوستان خود را به ربات دعوت کنید.\n\n";
        $message .= "💸 با هر خرید موفق دوستانتان، *{$referrerReward} تومان* به کیف پول شما اضافه می‌شود.\n\n";
        $message .= "🔗 *لینک دعوت شما:*\n`{$referralLink}`\n\n";
        $message .= "👥 تعداد دعوت‌های موفق شما: *{$referralCount} نفر*";

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت', 'callback_data' => '/start'])]);
        $this->sendOrEditMessage($user->telegram_chat_id, $message, $keyboard, $messageId);
    }

    protected function handleTrialRequest($user)
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

            $uniqueUsername = "trial-{$user->id}-" . ($currentTrials + 1);
            $expiresAt = now()->addHours($durationHours);
            $dataLimitBytes = $volumeMB * 1024 * 1024;

            $configLink = null;
            $panelType = $settings->get('panel_type');

            // --- تنظیمات سرور (Multi-Server Logic) ---
            $isMultiLocationEnabled = filter_var($settings->get('enable_multilocation', false), FILTER_VALIDATE_BOOLEAN);
            $targetServer = null;

            // 1. خواندن آیدی سرور تنظیم شده برای تست (از تنظیمات جدید)
            $forcedServerId = $settings->get('trial_server_id');

            // مقادیر پیش‌فرض
            $xuiHost = $settings->get('xui_host');
            $xuiUser = $settings->get('xui_user');
            $xuiPass = $settings->get('xui_pass');
            $inboundId = (int) $settings->get('xui_default_inbound_id');
            $linkType = $settings->get('xui_link_type', 'single');

            if ($isMultiLocationEnabled && class_exists('Modules\MultiServer\Models\Server')) {

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

                // اعمال تنظیمات سرور انتخاب شده
                if ($targetServer) {
                    $panelType = 'xui';
                    $xuiHost = $targetServer->full_host;
                    $xuiUser = $targetServer->username;
                    $xuiPass = $targetServer->password;
                    $inboundId = $targetServer->inbound_id;
                    $linkType = $targetServer->link_type ?? 'single';
                }
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

                            // 🔥 چک کردن وضعیت TLS از دیتابیس (مثل بخش خرید)
                            $tls = filter_var($targetServer->tunnel_is_https, FILTER_VALIDATE_BOOLEAN);

                            $params = ['type' => $streamSettings['network'] ?? 'tcp'];
                            if ($tls) {
                                $params['security'] = 'tls';
                                $params['sni'] = $tunnelAddress;
                            } else {
                                $params['security'] = 'none';
                                // 🔥 اگر TLS خاموشه، encryption رو هم none کن
                                if($protocol === 'vless') $params['encryption'] = 'none';
                            }

                            if (($params['type'] ?? '') === 'ws') {
                                $params['path'] = $streamSettings['wsSettings']['path'] ?? '/';
                                $params['host'] = $streamSettings['wsSettings']['headers']['Host'] ?? $tunnelAddress;
                            }

                            $flag = $targetServer->location->flag ?? '🏳️';

                            $remarkText = $flag . "-" . $uniqueUsername;




                            $qs = http_build_query($params);
//
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
            } else {
                throw new \Exception('نوع پنل در تنظیمات مشخص نشده است.');
            }

            if ($configLink) {
                if ($configLink) {
                    $user->increment('trial_accounts_taken');

                    // ذخیره لینک توی cache برای ۱۰ دقیقه (برای دکمه کپی)
                    \Illuminate\Support\Facades\Cache::put("trial_link_{$user->id}", $configLink, now()->addMinutes(10));

                    // بارگذاری اطلاعات سرور برای نمایش کشور
                    $locationFlag = '🏳️';
                    $locationName = 'نامشخص';
                    if ($targetServer && $targetServer->location) {
                        $locationFlag = $targetServer->location->flag ?? '🏳️';
                        $locationName = $targetServer->location->name;
                    }

                    // ساخت پیام کامل
                    $message = $this->escape("✅ اکانت تست شما با موفقیت ساخته شد!") . "\n\n";
                    $message .= "🌍 *موقعیت:* {$locationFlag} " . $this->escape($locationName) . "\n";
                    $message .= "📦 *حجم:* `{$volumeMB}` " . $this->escape("مگابایت") . "\n";
                    $message .= "⏳ *اعتبار:* `{$durationHours}` " . $this->escape("ساعت") . "\n\n";
                    $message .= "🔗 *لینک کانفیگ:*\n";
                    $message .= "`{$configLink}`\n\n";
                    $message .= $this->escape("⚠️ روی لینک بالا کلیک کنید یا دکمه زیر را بزنید.");

                    // کیبورد با دکمه کپی و QR
                    $keyboard = Keyboard::make()->inline()
                        ->row([
                            Keyboard::inlineButton(['text' => '📋 کپی لینک', 'callback_data' => "copy_trial_link_{$user->id}"]),
                            Keyboard::inlineButton(['text' => '📱 QR Code', 'callback_data' => "qr_trial_{$user->id}"])
                        ])
                        ->row([
                            Keyboard::inlineButton(['text' => '🛒 خرید سرویس', 'callback_data' => '/plans']),
                            Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
                        ]);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'MarkdownV2',
                        'reply_markup' => $keyboard
                    ]);

                    Log::info('Trial account created successfully', ['user_id' => $user->id, 'username' => $uniqueUsername]);
                    }}
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
    protected function sendOrEditMessage(int $chatId, string $text, $keyboard, ?int $messageId = null)
    {
        $payload = [
            'chat_id'      => $chatId,
            'text'         => $text,
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
        $text = str_replace('\\', '\\\\', $text);
        return str_replace($chars, array_map(fn($char) => '\\' . $char, $chars), $text);
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
        $this->sendOrEditMessage($chatId, $this->escape($text), $this->getMainMenuKeyboard(), $messageId);
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
            ['🏢 نمایندگی', '🔐 اطلاعات ورود به سایت'],
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
