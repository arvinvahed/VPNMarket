<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Models\Inbound;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\TelegramBotSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MarzbanService;
use App\Services\MarzneshinService;
use App\Services\XUIService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Ticketing\Models\Ticket;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class WebhookController extends Controller
{
    protected $settings;

    protected $botSettings;

    public function handle(Request $request)
    {
        Log::info('Telegram Webhook Received:', $request->all());

        try {
            $this->settings = Setting::all()->pluck('value', 'key');
            $this->botSettings = TelegramBotSetting::all()->pluck('value', 'key');
            $botToken = $this->settings->get('telegram_bot_token');

            if (! $botToken) {
                return 'ok';
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
            Log::error('Telegram Bot Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return 'ok';
    }

    /**
     * پردازش پیام‌های متنی ارسالی به ربات.
     */
    protected function handleTextMessage($update)
    {

        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $text = $message->getText() ?? '';
        $user = User::where('telegram_chat_id', $chatId)->first();
        $userFirstName = $message->getFrom()->getFirstName() ?? 'کاربر';

        if (! $user) {
            $password = Str::random(8);
            $user = User::create([
                'name' => $userFirstName,
                'email' => $chatId.'@telegram.user',
                'password' => Hash::make($password),
                'telegram_chat_id' => $chatId,
            ]);

            $welcomeMessage = "سلام *{$userFirstName}* عزیز به ربات ما خوش آمدید!\n\nیک حساب کاربری به صورت خودکار برای شما ایجاد شد:\n📧 **ایمیل:** `{$user->email}`\n🔑 **رمز عبور:** `{$password}`";

            // بررسی وجود کد معرف در دستور /start
            if (Str::startsWith($text, '/start ')) {
                $referralCode = Str::after($text, '/start ');
                $referrer = User::where('referral_code', $referralCode)->first();

                if ($referrer) {
                    $user->referrer_id = $referrer->id;
                    $user->save();

                    $welcomeGift = (int) $this->settings->get('referral_welcome_gift', 0);
                    if ($welcomeGift > 0) {
                        $user->increment('balance', $welcomeGift);
                        $welcomeMessage .= "\n\n🎁 شما یک هدیه خوش‌آمدگویی به مبلغ *".number_format($welcomeGift).' تومان* دریافت کردید!';
                    }

                    if ($referrer->telegram_chat_id) {
                        try {
                            $referrerNotificationMessage = "👤 *خبر خوب!*\n\n";
                            $referrerNotificationMessage .= "کاربر جدیدی با نام «{$userFirstName}» با لینک دعوت شما به ربات پیوست.\n\n";
                            $referrerNotificationMessage .= '🎁 پاداش شما پس از اولین خرید موفق ایشان به کیف پولتان اضافه خواهد شد. به دعوت کردن ادامه دهید!';

                            Telegram::sendMessage([
                                'chat_id' => $referrer->telegram_chat_id,
                                'text' => $referrerNotificationMessage,
                                'parse_mode' => 'Markdown',
                            ]);
                        } catch (\Exception $e) {
                            Log::error("Failed to send referral notification to referrer {$referrer->id}: ".$e->getMessage());
                        }
                    }
                }
            }
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => $welcomeMessage, 'parse_mode' => 'Markdown', 'reply_markup' => $this->getReplyMainMenu()]);

            return;
        }

        switch ($text) {
            case '🛒 خرید سرویس': $this->sendPlans($chatId);

                return;
            case '🛠 سرویس‌های من': $this->sendMyServices($user);

                return;
            case '💰 کیف پول': $this->sendWalletMenu($user);

                return;
            case '🎁 دعوت از دوستان': $this->sendReferralMenu($user);

                return;
            case '💬 پشتیبانی': $this->showSupportMenu($user);

                return;
            case '📚 راهنمای اتصال': $this->sendTutorialsMenu($chatId);

                return;
        }

        if ($user->bot_state === 'awaiting_deposit_amount') {
            $this->processDepositAmount($user, $text);

            return;
        }

        if ($user->bot_state && (Str::startsWith($user->bot_state, 'awaiting_new_ticket_') || Str::startsWith($user->bot_state, 'awaiting_ticket_reply'))) {
            $this->processTicketConversation($user, $text, $update);

            return;
        }

        if ($text === '/start') {
            $user->update(['bot_state' => null]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "سلام مجدد *{$user->name}*! لطفاً یکی از گزینه‌ها را از منوی پایین انتخاب کنید:",
                'parse_mode' => 'Markdown',
                'reply_markup' => $this->getReplyMainMenu(),
            ]);

            return;
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'دستور شما نامفهوم است. لطفاً از دکمه‌های منوی پایین استفاده کنید.',
            'reply_markup' => $this->getReplyMainMenu(),
        ]);
    }

    /**
     * ایجاد اکانت کاربر در پنل سرویس‌دهنده (Marzban/XUI) و بازگرداندن لینک کانفیگ.
     *
     * @return string|null
     */
    protected function provisionUserAccount(Order $order, Plan $plan)
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $configLink = null;

        // مطمئن می‌شویم expires_at ست شده است.
        if (! $order->expires_at) {
            $order->update(['expires_at' => now()->addDays($plan->duration_days)]);
        }

        $expireTimestamp = $order->expires_at->timestamp;
        $dataLimitBytes = $plan->data_limit_gb * 1073741824;
        $uniqueUsername = "user_{$order->user_id}_order_{$order->id}";

        try {
            if (($settings['panel_type'] ?? 'marzban') === 'marzban') {
                // ----------- اتصال به مرزبان -----------
                $marzban = new MarzbanService(
                    $settings['marzban_host'] ?? '',
                    $settings['marzban_sudo_username'] ?? '',
                    $settings['marzban_sudo_password'] ?? '',
                    $settings['marzban_node_hostname'] ?? null
                );

                $response = $marzban->createUser([
                    'username' => $uniqueUsername,
                    'expire' => $expireTimestamp,
                    'data_limit' => $dataLimitBytes,
                ]);

                if (! empty($response['subscription_url'])) {
                    $configLink = $marzban->generateSubscriptionLink($response);
                } else {
                    Log::error('Marzban user creation failed.', $response);
                }

            } elseif (($settings['panel_type'] ?? 'marzban') === 'marzneshin') {
                // ----------- اتصال به مرزنشین -----------
                $marzneshin = new MarzneshinService(
                    $settings['marzneshin_host'] ?? '',
                    $settings['marzneshin_sudo_username'] ?? '',
                    $settings['marzneshin_sudo_password'] ?? '',
                    $settings['marzneshin_node_hostname'] ?? null
                );

                $userData = [
                    'username' => $uniqueUsername,
                    'expire' => $expireTimestamp,
                    'data_limit' => $dataLimitBytes,
                ];

                // Add plan-specific service_ids if available
                if ($plan->marzneshin_service_ids && is_array($plan->marzneshin_service_ids) && count($plan->marzneshin_service_ids) > 0) {
                    $userData['service_ids'] = $plan->marzneshin_service_ids;
                }

                $response = $marzneshin->createUser($userData);

                if (! empty($response['subscription_url'])) {
                    $configLink = $marzneshin->generateSubscriptionLink($response);
                } else {
                    Log::error('Marzneshin user creation failed.', $response);
                }

            } elseif (($settings['panel_type'] ?? 'marzban') === 'xui') {
                // ----------- اتصال به سنایی/X-UI -----------

                // 1. دریافت Inbound ID از تنظیمات. این همان عددی است که در XUIService استفاده می‌شود.
                $inboundId = $settings['xui_default_inbound_id'] ?? null;
                if (! $inboundId) {
                    Log::error('XUI Inbound ID is not set in settings.');

                    return null;
                }

                $xui = new XuiService(
                    $settings['xui_host'] ?? '',
                    $settings['xui_user'] ?? '',
                    $settings['xui_pass'] ?? ''
                );

                // 2. محاسبات زمان (میلی‌ثانیه) و حجم (بایت)
                // توجه: X-UI نیاز به expiryTime در میلی‌ثانیه دارد.
                $expireTimeMs = $order->expires_at->timestamp * 1000;

                $clientData = [
                    'email' => $uniqueUsername,
                    'expiryTime' => $expireTimeMs,
                    'total' => $dataLimitBytes,
                ];

                // 3. افزودن کلاینت با Inbound ID صحیح
                $response = $xui->addClient($inboundId, $clientData);

                if ($response && isset($response['success']) && $response['success']) {

                    // 4. تولید لینک کانفیگ یا سابسکریپشن (همانند Filament)
                    $inbound = Inbound::find($inboundId);
                    if ($inbound && $inbound->inbound_data) {
                        $inboundData = json_decode($inbound->inbound_data, true);

                        $linkType = $settings['xui_link_type'] ?? 'single';
                        if ($linkType === 'subscription') {
                            $subId = $response['generated_subId'] ?? null;
                            $subBaseUrl = rtrim($settings['xui_subscription_url_base'] ?? '', '/');
                            if ($subId && $subBaseUrl) {
                                $configLink = $subBaseUrl.'/sub/'.$subId; // برخی پنل‌ها از sub/ و برخی از json/ استفاده می‌کنند.
                            }
                        } else {
                            $uuid = $response['generated_uuid'] ?? null;
                            if ($uuid) {
                                // ساخت لینک تکی VLESS (بر اساس منطق موجود در OrderResource)
                                $streamSettings = json_decode($inboundData['streamSettings'] ?? '{}', true);
                                $parsedUrl = parse_url($settings['xui_host'] ?? 'http://example.com');
                                $serverIpOrDomain = ! empty($inboundData['listen']) ? $inboundData['listen'] : ($parsedUrl['host'] ?? 'server_ip');
                                $port = $inboundData['port'] ?? 443;
                                $remark = $inboundData['remark'] ?? 'خدمات_وی_پی_ان';

                                $paramsArray = [
                                    'type' => $streamSettings['network'] ?? null,
                                    'security' => $streamSettings['security'] ?? null,
                                    'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null),
                                    'sni' => $streamSettings['tlsSettings']['serverName'] ?? null,
                                    'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null,
                                ];
                                $params = http_build_query(array_filter($paramsArray));
                                $fullRemark = $uniqueUsername.'|'.$remark;
                                $configLink = "vless://{$uuid}@{$serverIpOrDomain}:{$port}?{$params}#".urlencode($fullRemark);
                            }
                        }
                    }

                    if (! $configLink) {
                        Log::error('XUI config link generation failed.', ['response' => $response, 'inbound' => $inboundId]);
                    }

                } else {
                    Log::error('XUI user creation failed.', $response);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to provision account for Order {$order->id}: ".$e->getMessage());
        }

        return $configLink;
    }

    protected function showSupportMenu($user)
    {
        $user->update(['bot_state' => 'awaiting_new_ticket_subject']);
        $cancelKeyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton(['text' => '❌ انصراف', 'callback_data' => '/cancel_action']),
        ]);
        Telegram::sendMessage([
            'chat_id' => $user->telegram_chat_id,
            'text' => 'لطفاً موضوع تیکت خود را ارسال کنید، یا برای انصراف روی دکمه زیر کلیک کنید:',
            'reply_markup' => $cancelKeyboard,
        ]);
    }

    protected function sendReferralMenu($user)
    {
        $botUsername = Telegram::getMe()->getUsername();
        $referralCode = $user->referral_code;
        $referralLink = "https://t.me/{$botUsername}?start={$referralCode}";

        $referrerReward = number_format((int) $this->settings->get('referral_referrer_reward', 0));
        $referralCount = $user->referrals()->count();

        $message = "🎁 *سیستم دعوت از دوستان*\n\n";
        $message .= "دوستان خود را به ربات ما دعوت کنید و کسب درآمد کنید!\n\n";
        $message .= "با هر خرید موفق توسط کاربری که شما دعوت کرده‌اید، مبلغ *{$referrerReward} تومان* به کیف پول شما اضافه خواهد شد.\n\n";
        $message .= "🔗 *لینک دعوت اختصاصی شما:*\n`{$referralLink}`\n\n";
        $message .= "👥 تعداد دعوت‌های موفق شما تا کنون: *{$referralCount} نفر*";

        $keyboard = Keyboard::make()->inline()->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);
        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $message, 'parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
    }

    /**
     * پردازش پرداخت از طریق کیف پول کاربر.
     *
     * @param  User  $user
     * @param  int  $planId
     */
    protected function processWalletPayment($user, $planId)
    {
        $plan = Plan::find($planId);
        if (! $plan) {
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => '❌ پلن مورد نظر یافت نشد.']);

            return;
        }

        $userBalance = (float) $user->balance;
        $planPrice = (float) $plan->price;

        if ($userBalance < $planPrice) {
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => '❌ موجودی کیف پول شما برای خرید این پلن کافی نیست.']);

            return;
        }

        try {

            $order = DB::transaction(function () use ($user, $plan) {
                $user->decrement('balance', $plan->price);
                $order = $user->orders()->create([
                    'plan_id' => $plan->id, 'status' => 'paid', 'source' => 'telegram',
                    'amount' => $plan->price, 'expires_at' => now()->addDays($plan->duration_days),
                ]);
                Transaction::create([
                    'user_id' => $user->id, 'order_id' => $order->id, 'amount' => $plan->price,
                    'type' => 'purchase', 'status' => 'completed',
                    'description' => "خرید سرویس {$plan->name} (پرداخت از کیف پول)",
                ]);

                return $order;
            });

            // --- مرحله ۲: ساخت خودکار کانفیگ ---
            $settings = Setting::all()->pluck('value', 'key');
            $panelType = $settings->get('panel_type');
            $config = null;
            $uniqueUsername = "user_{$user->id}_order_{$order->id}";

            if ($panelType === 'marzban') {
                $trafficInBytes = $plan->volume_gb * 1073741824;
                $marzbanService = new MarzbanService(
                    $settings->get('marzban_host'),
                    $settings->get('marzban_sudo_username'),
                    $settings->get('marzban_sudo_password'),
                    $settings->get('marzban_node_hostname')
                );
                $expireTimestamp = $order->expires_at->timestamp;
                $userData = ['username' => $uniqueUsername, 'data_limit' => $trafficInBytes, 'expire' => $expireTimestamp];
                $response = $marzbanService->createUser($userData);

                if ($response && isset($response['username'])) {
                    $config = $marzbanService->generateSubscriptionLink($response);
                } else {
                    Log::error('Telegram Wallet Payment - Marzban Error', ['response' => $response]);
                }

            } elseif ($panelType === 'marzneshin') {
                $trafficInBytes = $plan->volume_gb * 1073741824;
                $marzneshinService = new MarzneshinService(
                    $settings->get('marzneshin_host'),
                    $settings->get('marzneshin_sudo_username'),
                    $settings->get('marzneshin_sudo_password'),
                    $settings->get('marzneshin_node_hostname')
                );
                $expireTimestamp = $order->expires_at->timestamp;
                $userData = ['username' => $uniqueUsername, 'data_limit' => $trafficInBytes, 'expire' => $expireTimestamp];

                // Add plan-specific service_ids if available
                if ($plan->marzneshin_service_ids && is_array($plan->marzneshin_service_ids) && count($plan->marzneshin_service_ids) > 0) {
                    $userData['service_ids'] = $plan->marzneshin_service_ids;
                }

                $response = $marzneshinService->createUser($userData);

                if ($response && isset($response['username'])) {
                    $config = $marzneshinService->generateSubscriptionLink($response);
                } else {
                    Log::error('Telegram Wallet Payment - Marzneshin Error', ['response' => $response]);
                }

            } elseif ($panelType === 'xui') {
                $inboundSettingId = $settings->get('xui_default_inbound_id');
                if ($inboundSettingId) {
                    $inbound = Inbound::find($inboundSettingId);
                    if ($inbound && $inbound->inbound_data) {
                        $inboundData = json_decode($inbound->inbound_data, true);
                        $xuiService = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));

                        $expireTime = $order->expires_at->timestamp * 1000;
                        $volumeBytes = $plan->volume_gb * 1073741824;
                        $clientData = ['email' => $uniqueUsername, 'total' => $volumeBytes, 'expiryTime' => $expireTime];

                        $response = $xuiService->addClient($inboundData['id'], $clientData);

                        if ($response && isset($response['success']) && $response['success']) {
                            // منطق ساخت لینک کانفیگ (دقیقاً مشابه OrderResource)
                            $linkType = $settings->get('xui_link_type', 'single');
                            if ($linkType === 'subscription') {
                                $subId = $response['generated_subId'];
                                $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                                if ($subBaseUrl) {
                                    $config = $subBaseUrl.'/json/'.$subId;
                                }
                            } else {
                                $uuid = $response['generated_uuid'];
                                $streamSettings = json_decode($inboundData['streamSettings'], true);
                                $parsedUrl = parse_url($settings->get('xui_host'));
                                $serverIpOrDomain = ! empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                                $port = $inboundData['port'];
                                $remark = $inboundData['remark'];
                                $paramsArray = ['type' => $streamSettings['network'] ?? null, 'security' => $streamSettings['security'] ?? null, 'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null), 'sni' => $streamSettings['tlsSettings']['serverName'] ?? null, 'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null];
                                $params = http_build_query(array_filter($paramsArray));
                                $fullRemark = $uniqueUsername.'|'.$remark;
                                $config = "vless://{$uuid}@{$serverIpOrDomain}:{$port}?{$params}#".urlencode($fullRemark);
                            }
                        } else {
                            Log::error('Telegram Wallet Payment - XUI Error', ['response' => $response]);
                        }
                    }
                }
            }

            // --- مرحله ۳: ذخیره کانفیگ و ارسال پیام به کاربر ---
            if ($config) {
                $order->update(['config_details' => $config]);
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => "✅ خرید شما با موفقیت انجام شد و سرویس *{$plan->name}* فوراً برای شما فعال گردید. می‌توانید از بخش 'سرویس‌های من' کانفیگ خود را دریافت کنید.",
                    'parse_mode' => 'Markdown',
                ]);
            } else {
                // اگر به هر دلیلی ساخت کانفیگ با خطا مواجه شد
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_chat_id,
                    'text' => "⚠️ پرداخت شما موفق بود اما در ساخت خودکار سرویس خطایی رخ داد. لطفاً فوراً به پشتیبانی اطلاع دهید. شماره سفارش شما: #{$order->id}",
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Wallet Payment Failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => '❌ خطایی در هنگام پردازش خرید رخ داد. لطفاً با پشتیبانی تماس بگیرید.']);
        }
    }

    protected function getReplyMainMenu(): Keyboard
    {
        return Keyboard::make([
            'keyboard' => [
                ['🛒 خرید سرویس', '🛠 سرویس‌های من'],
                ['💰 کیف پول', '🎁 دعوت از دوستان'],
                ['💬 پشتیبانی', '📚 راهنمای اتصال'],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);
    }

    /**
     * پردازش Callback Query ها (کلیک روی دکمه‌های Inline).
     */
    protected function handleCallbackQuery($update)
    {
        $callbackQuery = $update->getCallbackQuery();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        $user = User::where('telegram_chat_id', $chatId)->first();

        try {
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
        } catch (\Exception $e) {
            Log::warning('Could not answer callback query: '.$e->getMessage());
        }

        if (! $user) {
            return;
        }
        $user->update(['bot_state' => null]);

        if (Str::startsWith($data, 'buy_plan_')) {
            $planId = Str::after($data, 'buy_plan_');
            $this->startPurchaseProcess($user, $planId);
        } elseif (Str::startsWith($data, 'pay_wallet_')) {
            $planId = Str::after($data, 'pay_wallet_');
            $this->processWalletPayment($user, $planId);
        } elseif (Str::startsWith($data, 'pay_card_')) {
            $orderId = Str::after($data, 'pay_card_');
            $this->sendCardPaymentInfo($chatId, $orderId);
        } elseif (Str::startsWith($data, 'deposit_amount_')) {
            $amount = Str::after($data, 'deposit_amount_');
            $this->processDepositAmount($user, $amount);
        } elseif ($data === 'deposit_custom') {
            $user->update(['bot_state' => 'awaiting_deposit_amount']);
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => 'لطفاً مبلغ دلخواه خود را (به تومان) به صورت عددی وارد کنید:']);
        } elseif (Str::startsWith($data, 'close_ticket_')) {
            $ticketId = Str::after($data, 'close_ticket_');
            $ticket = $user->tickets()->where('id', $ticketId)->first();
            if ($ticket && $ticket->status !== 'closed') {
                $ticket->update(['status' => 'closed']);
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => '✅ تیکت شما بسته شد.']);
            }
        } elseif (Str::startsWith($data, 'reply_ticket_')) {
            $ticketId = Str::after($data, 'reply_ticket_');
            $user->update(['bot_state' => 'awaiting_ticket_reply|'.$ticketId]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '✏️ لطفاً پاسخ خود را ارسال کنید.']);
        } elseif ($data === '/start') {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => "سلام مجدد *{$user->name}*! لطفاً یکی از گزینه‌ها را انتخاب کنید:", 'parse_mode' => 'Markdown', 'reply_markup' => $this->getMainMenuKeyboard()]);
        } elseif ($data === '/plans') {
            $this->sendPlans($chatId);
        } elseif ($data === '/my_services') {
            $this->sendMyServices($user);
        } elseif ($data === '/cancel_action') {
            // وضعیت ربات را پاک کن
            $user->update(['bot_state' => null]);

            // پیامی که دکمه انصراف داشت را ویرایش کن تا دکمه حذف شود
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $callbackQuery->getMessage()->getMessageId(),
                'text' => '✅ عملیات لغو شد.',
                'reply_markup' => null, // حذف کیبورد
            ]);

            // پیام راهنمای جدید با منوی اصلی ارسال کن
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'لطفاً یکی از گزینه‌ها را انتخاب کنید:',
                'reply_markup' => $this->getReplyMainMenu(), // یا هر کیبورد اصلی دیگری که دارید
            ]);
        } elseif ($data === '/wallet') {
            $this->sendWalletMenu($user);

        } elseif ($data === '/referral') {
            $this->sendReferralMenu($user);
        } elseif ($data === '/deposit') {
            $this->showDepositOptions($user);
        } elseif ($data === '/transactions') { // NEW: اضافه شده برای نمایش تاریخچه تراکنش‌ها
            $this->sendTransactions($user);
        } elseif ($data === '/support') {
            $user->update(['bot_state' => 'awaiting_new_ticket_subject']);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'لطفاً موضوع تیکت خود را ارسال کنید:']);
        } elseif ($data === '/tutorials') {
            $this->sendTutorialsMenu($chatId);
        } elseif ($data === '/tutorial_android') {
            $this->sendTutorial('android', $chatId);
        } elseif ($data === '/tutorial_ios') {
            $this->sendTutorial('ios', $chatId);
        } elseif ($data === '/tutorial_windows') {
            $this->sendTutorial('windows', $chatId);
        }
    }

    /**
     * نمایش گزینه‌های شارژ کیف پول.
     *
     * @param  User  $user
     */
    protected function showDepositOptions($user)
    {
        $message = 'لطفاً یکی از مبلغ‌های زیر را برای شارژ انتخاب کنید یا مبلغ دلخواه خود را وارد نمایید:';
        $keyboard = Keyboard::make()->inline();

        $depositAmountsJson = $this->botSettings->get('deposit_amounts');
        if ($depositAmountsJson) {
            $amountsArray = json_decode($depositAmountsJson, true);

            if (is_array($amountsArray) && ! empty($amountsArray)) {
                $amountButtons = [];
                foreach ($amountsArray as $item) {
                    $amount = $item['amount'] ?? null;
                    if (is_numeric($amount)) {
                        $amountButtons[] = Keyboard::inlineButton([
                            'text' => number_format($amount).' تومان',
                            'callback_data' => 'deposit_amount_'.$amount,
                        ]);
                    }
                }

                foreach (array_chunk($amountButtons, 2) as $rowOfButtons) {
                    $keyboard->row($rowOfButtons);
                }

            }
        }

        $keyboard->row([Keyboard::inlineButton(['text' => '✍️ ورود مبلغ دلخواه', 'callback_data' => 'deposit_custom'])]);
        $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به کیف پول', 'callback_data' => '/wallet'])]);

        Telegram::sendMessage([
            'chat_id' => $user->telegram_chat_id,
            'text' => $message,
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * نمایش منوی کیف پول و موجودی کاربر.
     *
     * @param  User  $user
     */
    protected function sendWalletMenu($user)
    {
        $balance = number_format($user->balance ?? 0);
        $message = "💰 موجودی کیف پول شما: *{$balance} تومان*\n\nلطفاً یکی از گزینه‌های زیر را انتخاب کنید:";
        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton(['text' => '💳 شارژ حساب (کارت به کارت)', 'callback_data' => '/deposit'])])
            ->row([Keyboard::inlineButton(['text' => '📜 تاریخچه تراکنش‌ها', 'callback_data' => '/transactions'])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]);
        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $message, 'parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
    }

    /**
     * ثبت سفارش شارژ حساب برای مبلغ وارد شده و هدایت به صفحه پرداخت.
     *
     * @param  User  $user
     * @param  string  $amount
     */
    protected function processDepositAmount($user, $amount)
    {
        // تمیزسازی مبلغ ورودی
        $amount = str_replace(',', '', $amount);
        $amount = (int) $amount;

        if (! is_numeric($amount) || $amount < 1000) {
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => '❌ مبلغ وارد شده نامعتبر است. لطفاً یک عدد (به تومان) و بیشتر از ۱۰۰۰ وارد کنید.']);

            return;
        }

        $order = $user->orders()->create([
            'plan_id' => null,
            'status' => 'pending',
            'source' => 'telegram',
            'amount' => $amount,
        ]);

        $user->update(['bot_state' => null]);

        $this->sendCardPaymentInfo($user->telegram_chat_id, $order->id);
    }

    /**
     * نمایش منوی آموزش‌ها.
     *
     * @param  int  $chatId
     */
    protected function sendTutorialsMenu($chatId)
    {
        $message = 'لطفاً سیستم‌عامل خود را برای دریافت راهنمای اتصال انتخاب کنید:';
        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton(['text' => '📱 اندروید (Android)', 'callback_data' => '/tutorial_android'])])
            ->row([Keyboard::inlineButton(['text' => '🍏 آیفون (iOS)', 'callback_data' => '/tutorial_ios'])])
            ->row([Keyboard::inlineButton(['text' => '💻 ویندوز (Windows)', 'callback_data' => '/tutorial_windows'])])
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start'])]); // بازگشت به منوی اصلی
        // تغییر داده شده از بازگشت به آموزش به منوی اصلی چون سطح اول است.

        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $keyboard]);
    }

    /**
     * پردازش پیام‌های حاوی عکس (رسید یا پیوست تیکت).
     */
    protected function handlePhotoMessage($update)
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (! $user || ! $user->bot_state) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ لطفاً ابتدا یک عملیات مانند ثبت رسید را شروع کنید، سپس عکس را ارسال نمایید.']);

            return;
        }

        // پاسخ تیکت با عکس
        if (Str::startsWith($user->bot_state, 'awaiting_ticket_reply|')) {
            $this->processTicketConversation($user, '📎 فایل ارسال شد', $update);

            return;
        }

        // رسید کارت به کارت
        if (Str::startsWith($user->bot_state, 'waiting_receipt_')) {
            $orderId = Str::after($user->bot_state, 'waiting_receipt_');
            $order = Order::find($orderId);

            if ($order && $order->user_id === $user->id && $order->status === 'pending') {
                try {
                    $photo = collect($message->getPhoto())->last(); // بالاترین رزولوشن
                    $botToken = $this->settings->get('telegram_bot_token');
                    $file = Telegram::getFile(['file_id' => $photo->getFileId()]);
                    $fileContents = file_get_contents("https://api.telegram.org/file/bot{$botToken}/{$file->getFilePath()}");

                    if ($fileContents === false) {
                        throw new \Exception('Failed to download file from Telegram.');
                    }

                    $fileName = 'receipts/'.Str::random(40).'.jpg';
                    Storage::disk('public')->put($fileName, $fileContents);

                    $order->update(['card_payment_receipt' => $fileName]);
                    $user->update(['bot_state' => null]);

                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => '✅ رسید با موفقیت ثبت شد. پس از بررسی توسط تیم پشتیبانی، حساب شما شارژ/سرویس شما فعال خواهد شد.']);

                    $adminChatId = $this->settings->get('telegram_admin_chat_id');
                    if ($adminChatId) {
                        Telegram::sendMessage([
                            'chat_id' => $adminChatId,
                            'text' => "رسید جدید برای سفارش #{$order->id} ثبت شد. مبلغ: ".number_format($order->amount)." تومان. (کاربر: {$user->name} - #{$user->id})\nلینک رسید: ".Storage::disk('public')->url($fileName),
                            'parse_mode' => 'Markdown',
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error("Telegram receipt processing failed for order {$orderId}: ".$e->getMessage());
                    Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ خطا در پردازش رسید شما رخ داد. لطفاً مطمئن شوید که عکس را به درستی ارسال کرده‌اید و دوباره تلاش کنید.']);
                }
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ رسید برای سفارش معتبری نیست یا وضعیت آن در انتظار پرداخت نیست.']);
            }
        }
    }

    /**
     * مدیریت مکالمات تیکت (ارسال تیکت جدید یا پاسخ به تیکت موجود).
     *
     * @param  User  $user
     * @param  string  $text
     */
    protected function processTicketConversation($user, $text, $update = null)
    {
        $state = $user->bot_state;
        $chatId = $user->telegram_chat_id;

        // وضعیت پاسخ به تیکت موجود
        if (Str::startsWith($state, 'awaiting_ticket_reply|')) {
            $ticketId = Str::after($state, 'awaiting_ticket_reply|');
            $ticket = Ticket::find($ticketId);
            if ($ticket) {
                $replyData = ['user_id' => $user->id, 'message' => $text];

                // اگر عکس ارسال شده باشد
                if ($update && $update->getMessage()->has('photo')) {
                    try {
                        $photo = collect($update->getMessage()->getPhoto())->last();
                        $botToken = $this->settings->get('telegram_bot_token');
                        $file = Telegram::getFile(['file_id' => $photo->getFileId()]);
                        $fileContents = file_get_contents("https://api.telegram.org/file/bot{$botToken}/{$file->getFilePath()}");
                        $fileName = 'ticket_attachments/'.Str::random(40).'.jpg';
                        Storage::disk('public')->put($fileName, $fileContents);
                        $replyData['attachment_path'] = $fileName;
                        $replyData['message'] = $replyData['message']."\n[📎 پیوست تصویر]";
                    } catch (\Exception $e) {
                        Log::error('Ticket attachment upload failed: '.$e->getMessage());
                    }
                }

                $ticket->replies()->create($replyData);
                $ticket->update(['status' => 'open']);
                $user->update(['bot_state' => null]);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '✅ پاسخ شما با موفقیت ثبت شد.',
                ]);
            }
        }

        // وضعیت گرفتن موضوع تیکت جدید
        elseif ($state === 'awaiting_new_ticket_subject') {
            $user->update(['bot_state' => 'awaiting_new_ticket_message|'.$text]);
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '✅ موضوع دریافت شد. حالا لطفاً متن کامل پیام خود را ارسال کنید:']);
        }
        // وضعیت گرفتن متن پیام تیکت جدید
        elseif (Str::startsWith($state, 'awaiting_new_ticket_message|')) {
            $subject = Str::after($state, 'awaiting_new_ticket_message|');
            $ticket = $user->tickets()->create(['subject' => $subject, 'priority' => 'medium', 'status' => 'open', 'source' => 'telegram']);
            $ticket->replies()->create(['user_id' => $user->id, 'message' => $text]);

            $user->update(['bot_state' => null]);

            $closeKeyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => '❌ بستن تیکت', 'callback_data' => 'close_ticket_'.$ticket->id]),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '✅ تیکت شما با موفقیت ثبت شد و به زودی بررسی خواهد شد.',
                'reply_markup' => $closeKeyboard,
            ]);

            $adminChatId = $this->settings->get('telegram_admin_chat_id');
            if ($adminChatId) {
                Telegram::sendMessage(['chat_id' => $adminChatId, 'text' => "تیکت جدید با موضوع '{$subject}' توسط کاربر {$user->name} از تلگرام ثبت شد."]);
            }
        }
    }

    /**
     * شروع فرآیند خرید (انتخاب روش پرداخت).
     *
     * @param  User  $user
     * @param  int  $planId
     */
    protected function startPurchaseProcess($user, $planId)
    {
        $plan = Plan::find($planId);
        if (! $user || ! $plan) {
            return;
        }

        $balance = $user->balance ?? 0;

        $message = "شما در حال خرید پلن *{$plan->name}* به قیمت *".number_format($plan->price)." تومان* هستید.\n";
        $message .= 'موجودی کیف پول شما: *'.number_format($balance)." تومان*\n\n";
        $message .= 'لطفاً روش پرداخت خود را انتخاب کنید:';

        $keyboard = Keyboard::make()->inline();

        if ($balance >= $plan->price) {
            $keyboard->row([Keyboard::inlineButton(['text' => '✅ پرداخت با کیف پول (فعالسازی آنی)', 'callback_data' => "pay_wallet_{$plan->id}"])]);
        }

        $order = $user->orders()->create([
            'plan_id' => $plan->id,
            'status' => 'pending',
            'source' => 'telegram',
            'amount' => $plan->price,
        ]);

        $keyboard->row([Keyboard::inlineButton(['text' => '💳 کارت به کارت (نیاز به تایید)', 'callback_data' => "pay_card_{$order->id}"])]);
        $keyboard->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به پلن‌ها', 'callback_data' => '/plans'])]);

        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $message, 'parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
    }

    /**
     * ارسال اطلاعات کارت به کارت و درخواست رسید.
     *
     * @param  int  $chatId
     * @param  int  $orderId
     */
    protected function sendCardPaymentInfo($chatId, $orderId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        $order = Order::find($orderId);

        if (! $user || ! $order || $order->user_id !== $user->id) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ خطای سیستم: سفارش یافت نشد.']);

            return;
        }

        // تنظیم وضعیت کاربر برای انتظار دریافت عکس رسید
        $user->update(['bot_state' => 'waiting_receipt_'.$orderId]);

        $cardNumber = $this->settings->get('payment_card_number', 'شماره کارتی یافت نشد');
        $cardHolder = $this->settings->get('payment_card_holder_name', 'صاحب حسابی یافت نشد');
        $amountToPay = number_format($order->amount);

        $message = 'لطفاً مبلغ *'.$amountToPay." تومان* را به کارت زیر واریز نمایید:\n\n";
        $message .= '💳 شماره کارت: `'.$cardNumber."`\n";
        $message .= '👤 نام صاحب حساب: *'.$cardHolder."*\n";
        $message .= '🔔 توجه: فقط مبلغ *'.$amountToPay." تومان* را واریز کنید.\n\n";
        $message .= '🔴 **سپس فقط عکس رسید واریزی (عکس از صفحه اپلیکیشن بانکی یا عابربانک) را در همین چت ارسال کنید.**';
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown']);
    }

    /**
     * ارسال لیست پلن‌های فعال.
     *
     * @param  int  $chatId
     */
    protected function sendPlans($chatId)
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        if ($plans->isEmpty()) {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'متاسفانه در حال حاضر هیچ پلن فعالی وجود ندارد.']);

            return;
        }

        $message = "لیست پلن‌های موجود:\n\n";
        $keyboard = Keyboard::make()->inline();
        foreach ($plans as $plan) {
            $message .= "--------------------------------------\n";
            $message .= "💎 *{$plan->name}*\n";
            $message .= "📊 حجم: *{$plan->volume_gb} گیگ*\n";
            $message .= "🗓️ مدت: *{$plan->duration_days} روز*\n";
            $message .= '💰 قیمت: *'.number_format($plan->price)." تومان*\n";
            $message .= "--------------------------------------\n";

            $keyboard->row([Keyboard::inlineButton(['text' => "🛒 خرید پلن {$plan->name}", 'callback_data' => "buy_plan_{$plan->id}"])]);
        }

        $keyboard->row([
            \Telegram\Bot\Keyboard\Keyboard::inlineButton([
                'text' => '⬅️ بازگشت به منوی اصلی',
                'callback_data' => '/start',
            ]),
        ]);

        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
    }

    /**
     * نمایش سرویس‌های فعال کاربر.
     *
     * @param  User  $user
     */
    protected function sendMyServices($user)
    {
        $activeOrders = $user->orders()
            ->with('plan')
            ->where('status', 'paid')
            ->whereNotNull('plan_id')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        if ($activeOrders->isEmpty()) {
            Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => 'شما در حال حاضر هیچ سرویس فعالی ندارید.']);

            return;
        }

        $message = "لیست سرویس‌های فعال شما:\n\n";

        foreach ($activeOrders as $order) {
            if (! $order->plan) {
                continue;
            }

            $expiresAt = Carbon::parse($order->expires_at);

            // --- شروع تغییر کلیدی ---
            // محاسبه روزهای باقی‌مانده و گرد کردن آن به پایین
            $daysRemaining = floor(now()->diffInDays($expiresAt, false));
            // --- پایان تغییر کلیدی ---

            $remainingText = '';
            if ($daysRemaining > 0) {
                $remainingText = "(*{$daysRemaining} روز باقی‌مانده*)";
            } elseif ($daysRemaining == 0) {
                $remainingText = '(*کمتر از یک روز باقی‌مانده*)'; // متن را برای حالت صفر بهبود می‌دهیم
            } else {
                // این حالت با کوئری where('expires_at', '>', now()) رخ نمی‌دهد، اما برای اطمینان باقی می‌ماند
                $remainingText = '(*منقضی شده*)';
            }

            $message .= "--------------------------------------\n";
            $message .= "💎 *{$order->plan->name}*\n";
            $message .= '🗓️ تاریخ انقضا: *'.$expiresAt->format('Y/m/d').'* '.$remainingText."\n";
            $message .= "📦 حجم کل: *{$order->plan->volume_gb} گیگابایت*\n";

            if (! empty($order->config_details)) {
                $message .= "🔗 *لینک اتصال:*\n`{$order->config_details}`\n";
            } else {
                $message .= "⚠️ کانفیگ در حال آماده‌سازی است. لطفاً کمی صبر کنید یا با پشتیبانی تماس بگیرید.\n";
            }
        }

        $message .= "\nبرای مدیریت سرویس‌ها و مشاهده میزان مصرف، از پنل کاربری خود نیز می‌توانید استفاده کنید.";

        $keyboard = Keyboard::make()->inline()->row([
            Keyboard::inlineButton(['text' => '⬅️ بازگشت به منوی اصلی', 'callback_data' => '/start']),
        ]);

        Telegram::sendMessage([
            'chat_id' => $user->telegram_chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    /**
     * تولید کیبورد اصلی ربات (منوی اصلی).
     *
     * @return Keyboard
     */
    protected function getMainMenuKeyboard()
    {
        return Keyboard::make()->inline()
            ->row([
                Keyboard::inlineButton(['text' => '🛒 خرید سرویس جدید', 'callback_data' => '/plans']),
                Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '💰 کیف پول و شارژ حساب', 'callback_data' => '/wallet']),
                Keyboard::inlineButton(['text' => '📚 آموزش اتصال', 'callback_data' => '/tutorials']),
            ])
            ->row([
                Keyboard::inlineButton(['text' => '💬 پشتیبانی (تیکت)', 'callback_data' => '/support']),
            ]);
    }

    /**
     * ارسال محتوای آموزشی بر اساس پلتفرم.
     *
     * @param  string  $platform
     * @param  int  $chatId
     */
    protected function sendTutorial($platform, $chatId)
    {
        $tutorials = [
            'android' => [
                'title' => 'راهنمای اتصال اندروید 📱',
                'text' => 'برای اتصال در اندروید، اپلیکیشن *V2RayNG* را نصب کنید. لینک کانفیگ خود را کپی کرده و در این برنامه وارد نمایید. (آموزش کامل به زودی در وب‌سایت قرار می‌گیرد.)',
                'app_link' => 'https://play.google.com/store/apps/details?id=com.v2ray.android',
            ],
            'ios' => [
                'title' => 'راهنمای اتصال آیفون/آیپد 🍏',
                'text' => 'برای اتصال در iOS، اپلیکیشن *Fair VPN* یا *Streisand* را نصب کنید. سپس لینک سابسکریپشن خود را در آن وارد نمایید.',
                'app_link' => 'https://apps.apple.com/us/app/fair-vpn/id6446860086',
            ],
            'windows' => [
                'title' => 'راهنمای اتصال ویندوز 💻',
                'text' => 'برای اتصال در ویندوز، نرم‌افزار *V2RayN* را دانلود و نصب کنید. لینک کانفیگ را کپی کرده و در برنامه اضافه کنید.',
                'app_link' => 'لینک دانلود V2RayN از گیت‌هاب',
            ],
        ];

        $info = $tutorials[$platform] ?? null;

        if ($info) {
            $message = "*{$info['title']}*\n\n{$info['text']}\n\n[لینک دانلود برنامه]({$info['app_link']})";

            $keyboard = Keyboard::make()->inline()
                ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به آموزش‌ها', 'callback_data' => '/tutorials'])]);

            Telegram::sendMessage(['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
        } else {
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '❌ آموزش درخواستی یافت نشد.']);
        }
    }

    /**
     * نمایش 5 تراکنش آخر کاربر.
     *
     * @param  User  $user
     */
    protected function sendTransactions($user)
    {
        // محدود کردن به 5 تراکنش اخیر
        $transactions = $user->transactions()->orderBy('created_at', 'desc')->take(5)->get();

        if ($transactions->isEmpty()) {
            $message = '📜 تاریخچه تراکنش‌های شما خالی است.';
        } else {
            $message = "📜 *۵ تراکنش اخیر شما:*\n\n";
            foreach ($transactions as $transaction) {
                $status = $transaction->status === 'completed' ? '✅ موفق' : '⚠️ در انتظار';
                $type = $transaction->type === 'deposit' ? '💰 شارژ' : '🛒 خرید';
                $message .= "--------------------------------------\n";
                $message .= '💸 مبلغ: *'.number_format($transaction->amount)." تومان*\n";
                $message .= "🏷 نوع: *{$type}*\n";
                $message .= "وضعیت: *{$status}*\n";
                $message .= "توضیحات: {$transaction->description}\n";
                $message .= 'تاریخ: '.Carbon::parse($transaction->created_at)->format('Y/m/d H:i');
            }
        }

        $keyboard = Keyboard::make()->inline()
            ->row([Keyboard::inlineButton(['text' => '⬅️ بازگشت به کیف پول', 'callback_data' => '/wallet'])]);

        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $message, 'parse_mode' => 'Markdown', 'reply_markup' => $keyboard]);
    }
}
