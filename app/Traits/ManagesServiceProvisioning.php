<?php

namespace App\Traits;

use App\Models\Order;
use App\Models\Inbound;
use App\Models\Plan;
use App\Services\MarzbanService;
use App\Services\XUIService;
use App\Services\PasargadService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

trait ManagesServiceProvisioning
{
    /**
     * سرویس کاربر را در پنل مربوطه (Marzban/XUI) ایجاد یا تمدید می‌کند.
     *
     * @param string $panelType نوع پنل (marzban یا xui)
     * @param \Illuminate\Support\Collection $settings تنظیمات برنامه
     * @param Order $order سفارش
     * @param bool $isTelegramContext آیا از تلگرام فراخوانی شده؟ (برای مدیریت خطا)
     * @return array|false آرایه‌ای شامل ['config' => $config, 'expires_at' => $expires_at] در صورت موفقیت، یا false در صورت شکست
     */
    public function provisionService(string $panelType, $settings, Order $order, bool $isTelegramContext = false)
    {
        $user = $order->user;
        $plan = $order->plan;
        if (!$plan) {
            $this->handleProvisioningError("سفارش {$order->id} فاقد پلن است.", $isTelegramContext);
            return false;
        }

        $isRenewal = (bool)$order->renews_order_id;
        $originalOrder = null;

        if ($isRenewal) {
            $originalOrder = Order::find($order->renews_order_id);
            if (!$originalOrder) {
                $this->handleProvisioningError('سفارش اصلی جهت تمدید یافت نشد.', $isTelegramContext);
                return false;
            }
        }

        // نام کاربری بر اساس سفارش اصلی (در صورت تمدید) یا سفارش فعلی (در صورت خرید جدید)
        $uniqueUsername = "user-{$user->id}-order-" . ($isRenewal ? $originalOrder->id : $order->id);

        // محاسبه تاریخ انقضای جدید
        $baseDate = now();
        if ($isRenewal) {
            $baseDate = (new \DateTime($originalOrder->expires_at));
            // اگر سرویس منقضی شده، تمدید از امروز حساب شود
            if ($baseDate < now()) {
                $baseDate = now();
            }
        }

        // $newExpiresAt به یک آبجکت DateTime تبدیل می‌شود
        $newExpiresAt = $baseDate->modify("+{$plan->duration_days} days");

        $finalConfig = null;
        $success = false;

        try {
            if ($panelType === 'marzban') {
                $marzbanService = new MarzbanService($settings->get('marzban_host'), $settings->get('marzban_sudo_username'), $settings->get('marzban_sudo_password'), $settings->get('marzban_node_hostname'));

                // مطمئن شوید مدل Plan ستون data_limit_gb را دارد (در کد شما volume_gb بود، من به data_limit_gb تغییر دادم)
                $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => $plan->data_limit_gb * 1024 * 1024 * 1024];

                $response = $isRenewal
                    ? $marzbanService->updateUser($uniqueUsername, $userData)
                    : $marzbanService->createUser(array_merge($userData, ['username' => $uniqueUsername]));

                if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                    $finalConfig = $marzbanService->generateSubscriptionLink($response);
                    $success = true;
                } else {
                    $error = $response['detail'] ?? 'پاسخ نامعتبر از مرزبان.';
                    $this->handleProvisioningError($error, $isTelegramContext, ['response' => $response]);
                    return false;
                }

            } elseif ($panelType === 'xui') {
                $inboundId = $settings->get('xui_default_inbound_id');
                if (!$inboundId) {
                    $this->handleProvisioningError('اینباند XUI در تنظیمات ست نشده.', $isTelegramContext); return false;
                }
                $xuiService = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));
                if (!$xuiService->login()) {
                    $this->handleProvisioningError('خطا در لاگین به پنل X-UI.', $isTelegramContext); return false;
                }
                $inbound = Inbound::find($inboundId);
                if (!$inbound || !$inbound->inbound_data) {
                    $this->handleProvisioningError('اطلاعات اینباند پیش‌فرض X-UI یافت نشد.', $isTelegramContext); return false;
                }

                $inboundData = json_decode($inbound->inbound_data, true);
                // مطمئن شوید مدل Plan ستون data_limit_gb را دارد (در کد شما volume_gb بود، من به data_limit_gb تغییر دادم)
                $clientData = ['email' => $uniqueUsername, 'total' => $plan->data_limit_gb * 1024 * 1024 * 1024, 'expiryTime' => $newExpiresAt->getTimestamp() * 1000];

                if ($isRenewal) {
                    //TODO: منطق تمدید کاربر در XUI (یافتن کاربر و آپدیت)
                    $this->handleProvisioningError('تمدید خودکار برای پنل XUI هنوز پیاده‌سازی نشده است.', $isTelegramContext);
                    return false;
                }

                $response = $xuiService->addClient($inboundData['id'], $clientData);

                if ($response && isset($response['success']) && $response['success']) {
                    $linkType = $settings->get('xui_link_type', 'single');
                    if ($linkType === 'subscription') {
                        $subId = $response['generated_subId'] ?? null;
                        $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                        if ($subBaseUrl && $subId) {
                            $finalConfig = $subBaseUrl . '/sub/' . $subId;
                            $success = true;
                        } else {
                            $this->handleProvisioningError('آدرس پایه اشتراک XUI یا ID اشتراک ست نشده.', $isTelegramContext); return false;
                        }
                    } else { // single link
                        $uuid = $response['generated_uuid'] ?? null;
                        if (!$uuid) { $this->handleProvisioningError('UUID از پنل XUI دریافت نشد.', $isTelegramContext); return false; }

                        $streamSettings = json_decode($inboundData['streamSettings'], true);
                        $parsedUrl = parse_url($settings->get('xui_host'));
                        $serverAddress = !empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                        $port = $inboundData['port'];
                        $remark = $inboundData['remark'];
                        $paramsArray = [
                            'type' => $streamSettings['network'] ?? null,
                            'security' => $streamSettings['security'] ?? null,
                            'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null),
                            'sni' => $streamSettings['tlsSettings']['serverName'] ?? null,
                            'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null
                        ];
                        $params = http_build_query(array_filter($paramsArray));
                        $fullRemark = $uniqueUsername . '|' . $remark;
                        $finalConfig = "vless://{$uuid}@{$serverAddress}:{$port}?{$params}#" . urlencode($fullRemark);
                        $success = true;
                    }
                } else {
                    $this->handleProvisioningError($response['msg'] ?? 'پاسخ نامعتبر از XUI', $isTelegramContext, ['response' => $response]);
                    return false;
                }
            }
            // --- PASARGAD ---
            elseif ($panelType === 'pasargad') {
                $pasargadService = new PasargadService(
                    $settings->get('pasargad_host'),
                    $settings->get('pasargad_sudo_username'),
                    $settings->get('pasargad_sudo_password'),
                    $settings->get('pasargad_node_hostname')
                );

                $userData = [
                    'expire' => $newExpiresAt->getTimestamp(),
                    'data_limit' => $plan->data_limit_gb * 1024 * 1024 * 1024
                ];

                $response = $isRenewal
                    ? $pasargadService->updateUser($uniqueUsername, $userData)
                    : $pasargadService->createUser(array_merge($userData, [
                        'username' => $uniqueUsername,
                        'group_ids' => [(int)($plan->pasargad_group_id ?? $settings->get('pasargad_paid_group_id') ?? 1)],
                    ]));

                if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                    $finalConfig = $response['subscription_url'] ?? $pasargadService->generateSubscriptionLink($uniqueUsername);
                    $success = true;
                    
                    // ریست ترافیک در صورت تمدید
                    if ($isRenewal) {
                        $pasargadService->resetUserTraffic($uniqueUsername);
                    }
                } else {
                    $error = $response['detail'] ?? 'پاسخ نامعتبر از پاسارگاد.';
                    $this->handleProvisioningError($error, $isTelegramContext, ['response' => $response]);
                    return false;
                }
            }

            if ($success) {
                return ['config' => $finalConfig, 'expires_at' => $newExpiresAt];
            } else {
                $this->handleProvisioningError('موفقیت‌آمیز نبود (Success=false) اما خطایی رخ نداد.', $isTelegramContext);
                return false;
            }

        } catch (\Exception $e) {
            $this->handleProvisioningError("خطای سیستمی: " . $e->getMessage(), $isTelegramContext, ['trace' => $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * مدیریت خطاها در Trait
     */
    protected function handleProvisioningError(string $message, bool $isTelegram, array $context = [])
    {
        Log::error($message, $context);
        if (!$isTelegram) {
            // اگر در فیلامنت هستیم، نوتیفیکیشن نشان بده
            Notification::make()->title('خطا در ساخت سرویس')->body($message)->danger()->send();
        }
        // اگر در تلگرام باشیم، فقط لاگ می‌اندازد و false برمی‌گرداند تا در try/catch مدیریت شود
    }
}
