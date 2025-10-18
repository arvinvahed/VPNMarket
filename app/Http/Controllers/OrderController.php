<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Models\Inbound;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\CouponService;
use App\Services\MarzbanService;
use App\Services\MarzneshinService;
use App\Services\XUIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Create a new pending order for a specific plan.
     */
    public function store(Plan $plan)
    {
        $order = Auth::user()->orders()->create([
            'plan_id' => $plan->id,
            'status' => 'pending',
            'source' => 'web',
        ]);

        return redirect()->route('order.show', $order->id);
    }

    /**
     * Show the payment method selection page for an order.
     */
    public function show(Order $order)
    {
        if (Auth::id() !== $order->user_id) {
            abort(403, 'شما به این صفحه دسترسی ندارید.');
        }

        if ($order->status === 'paid') {
            return redirect()->route('dashboard')->with('status', 'این سفارش قبلاً پرداخت شده است.');
        }

        return view('payment.show', ['order' => $order]);
    }

    /**
     * Show the bank card details and receipt upload form.
     */
    public function processCardPayment(Order $order)
    {
        $order->update(['payment_method' => 'card']);
        $settings = Setting::all()->pluck('value', 'key');

        return view('payment.card-receipt', [
            'order' => $order,
            'settings' => $settings,
        ]);
    }

    /**
     * Show the form to enter the wallet charge amount.
     */
    public function showChargeForm()
    {
        return view('wallet.charge');
    }

    /**
     * Create a new pending order for charging the wallet.
     */
    public function createChargeOrder(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:10000']);
        $order = Auth::user()->orders()->create([
            'plan_id' => null,
            'amount' => $request->amount,
            'status' => 'pending',
            'source' => 'web',
        ]);

        return redirect()->route('order.show', $order->id);
    }

    /**
     * Create a new pending order to renew an existing service.
     */
    public function renew(Order $order)
    {
        if (Auth::id() !== $order->user_id || $order->status !== 'paid') {
            abort(403);
        }

        $newOrder = $order->replicate();
        $newOrder->created_at = now();
        $newOrder->status = 'pending';
        $newOrder->source = 'web';
        $newOrder->config_details = null;
        $newOrder->expires_at = null;
        $newOrder->renews_order_id = $order->id;
        $newOrder->save();

        return redirect()->route('order.show', $newOrder->id)->with('status', 'سفارش تمدید شما ایجاد شد. لطفاً هزینه را پرداخت کنید.');
    }

    /**
     * Handle the submission of the payment receipt file.
     */
    public function submitCardReceipt(Request $request, Order $order)
    {
        $request->validate(['receipt' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048']);
        $path = $request->file('receipt')->store('receipts', 'public');
        $order->update(['card_payment_receipt' => $path]);

        return redirect()->route('dashboard')->with('status', 'رسید شما با موفقیت ارسال شد. پس از تایید توسط مدیر، سرویس شما فعال خواهد شد.');
    }

    /**
     * Process instant payment from the user's wallet balance.
     */
    public function processWalletPayment(Order $order)
    {
        if (auth()->id() !== $order->user_id) {
            abort(403);
        }
        if (! $order->plan) {
            return redirect()->back()->with('error', 'این عملیات برای شارژ کیف پول مجاز نیست.');
        }

        $user = auth()->user();
        $plan = $order->plan;
        // Use the order's amount if a coupon was applied, otherwise use the plan's price
        $price = $order->amount ?? $plan->price;

        if ($user->balance < $price) {
            return redirect()->back()->with('error', 'موجودی کیف پول شما برای انجام این عملیات کافی نیست.');
        }

        try {
            DB::transaction(function () use ($order, $user, $plan, $price) {
                $user->decrement('balance', $price);

                $success = false;
                $finalConfig = '';
                $isRenewal = (bool) $order->renews_order_id;

                // Get panel from plan
                $panel = $plan->panel;
                if (!$panel) {
                    throw new \Exception('هیچ پنلی به این پلن مرتبط نیست. لطفاً از طریق پنل ادمین یک پنل را به این پلن اختصاص دهید.');
                }

                $panelType = $panel->panel_type;
                $credentials = $panel->getCredentials();

                $uniqueUsername = "user_{$user->id}_order_".($isRenewal ? $order->renews_order_id : $order->id);
                $newExpiresAt = $isRenewal
                    ? (new \DateTime(Order::find($order->renews_order_id)->expires_at))->modify("+{$plan->duration_days} days")
                    : now()->addDays($plan->duration_days);

                if ($panelType === 'marzban') {
                    $nodeHostname = $credentials['extra']['node_hostname'] ?? '';
                    $marzbanService = new MarzbanService(
                        $credentials['url'],
                        $credentials['username'],
                        $credentials['password'],
                        $nodeHostname
                    );
                    $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => $plan->volume_gb * 1073741824];

                    $response = $isRenewal
                        ? $marzbanService->updateUser($uniqueUsername, $userData)
                        : $marzbanService->createUser(array_merge($userData, ['username' => $uniqueUsername]));

                    if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                        $finalConfig = $marzbanService->generateSubscriptionLink($response);
                        $success = true;
                    }
                } elseif ($panelType === 'marzneshin') {
                    $nodeHostname = $credentials['extra']['node_hostname'] ?? '';
                    $marzneshinService = new MarzneshinService(
                        $credentials['url'],
                        $credentials['username'],
                        $credentials['password'],
                        $nodeHostname
                    );
                    $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => $plan->volume_gb * 1073741824];

                    // Add plan-specific service_ids if available
                    if ($plan->marzneshin_service_ids && is_array($plan->marzneshin_service_ids) && count($plan->marzneshin_service_ids) > 0) {
                        $userData['service_ids'] = $plan->marzneshin_service_ids;
                    }

                    $response = $isRenewal
                        ? $marzneshinService->updateUser($uniqueUsername, $userData)
                        : $marzneshinService->createUser(array_merge($userData, ['username' => $uniqueUsername]));

                    if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                        $finalConfig = $marzneshinService->generateSubscriptionLink($response);
                        $success = true;
                    }
                } elseif ($panelType === 'xui') {
                    if ($isRenewal) {
                        throw new \Exception('تمدید خودکار برای پنل سنایی هنوز پیاده‌سازی نشده است.');
                    }
                    $xuiService = new XUIService(
                        $credentials['url'],
                        $credentials['username'],
                        $credentials['password']
                    );
                    
                    $defaultInboundId = $credentials['extra']['default_inbound_id'] ?? null;
                    $inbound = $defaultInboundId ? Inbound::find($defaultInboundId) : null;
                    
                    if (! $inbound || ! $inbound->inbound_data) {
                        throw new \Exception('اطلاعات اینباند پیش‌فرض برای X-UI یافت نشد.');
                    }
                    if (! $xuiService->login()) {
                        throw new \Exception('خطا در لاگین به پنل X-UI.');
                    }

                    $inboundData = json_decode($inbound->inbound_data, true);
                    $clientData = ['email' => $uniqueUsername, 'total' => $plan->volume_gb * 1073741824, 'expiryTime' => $newExpiresAt->timestamp * 1000];
                    $response = $xuiService->addClient($inboundData['id'], $clientData);

                    if ($response && isset($response['success']) && $response['success']) {
                        $linkType = $credentials['extra']['link_type'] ?? 'single';
                        if ($linkType === 'subscription') {
                            $subId = $response['generated_subId'];
                            $subBaseUrl = rtrim($credentials['extra']['subscription_url_base'] ?? '', '/');
                            if ($subBaseUrl) {
                                $finalConfig = $subBaseUrl.'/sub/'.$subId;
                                $success = true;
                            }
                        } else {
                            $uuid = $response['generated_uuid'];
                            $streamSettings = json_decode($inboundData['streamSettings'], true);
                            $parsedUrl = parse_url($credentials['url']);
                            $serverIpOrDomain = ! empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                            $port = $inboundData['port'];
                            $remark = $inboundData['remark'];
                            $paramsArray = ['type' => $streamSettings['network'] ?? null, 'security' => $streamSettings['security'] ?? null, 'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null), 'sni' => $streamSettings['tlsSettings']['serverName'] ?? null, 'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null];
                            $params = http_build_query(array_filter($paramsArray));
                            $fullRemark = $uniqueUsername.'|'.$remark;
                            $finalConfig = "vless://{$uuid}@{$serverIpOrDomain}:{$port}?{$params}#".urlencode($fullRemark);
                            $success = true;
                        }
                    } else {
                        throw new \Exception('خطا در ساخت کاربر در پنل سنایی: '.($response['msg'] ?? 'پاسخ نامعتبر'));
                    }
                }

                if (! $success) {
                    throw new \Exception('خطا در ارتباط با سرور برای فعال‌سازی سرویس.');
                }

                // آپدیت سفارش اصلی یا سفارش جدید
                if ($isRenewal) {
                    $originalOrder = Order::find($order->renews_order_id);
                    $originalOrder->update(['config_details' => $finalConfig, 'expires_at' => $newExpiresAt->format('Y-m-d H:i:s')]);
                    $user->update(['show_renewal_notification' => true]);
                } else {
                    $order->update(['config_details' => $finalConfig, 'expires_at' => $newExpiresAt]);
                }

                $order->update(['status' => 'paid', 'payment_method' => 'wallet']);
                Transaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'amount' => $price, 'type' => 'purchase', 'status' => 'completed', 'description' => ($isRenewal ? 'تمدید سرویس' : 'خرید سرویس')." {$plan->name} از کیف پول"]);

                // Increment promo code usage if applied
                if ($order->promo_code_id) {
                    $couponService = new CouponService;
                    $couponService->incrementUsage($order->promoCode);
                }

                OrderPaid::dispatch($order);
            });
        } catch (\Exception $e) {
            Log::error('Wallet Payment Failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('dashboard')->with('error', 'پرداخت با خطا مواجه شد: '.$e->getMessage());
        }

        return redirect()->route('dashboard')->with('status', 'سرویس شما با موفقیت فعال شد.');
    }

    public function processCryptoPayment(Order $order)
    {
        $order->update(['payment_method' => 'crypto']);

        return redirect()->back()->with('status', '💡 پرداخت با ارز دیجیتال به زودی فعال می‌شود. لطفاً از روش کارت به کارت استفاده کنید.');
    }

    /**
     * Apply a coupon code to an order.
     */
    public function applyCoupon(Request $request, Order $order)
    {
        if (Auth::id() !== $order->user_id) {
            abort(403);
        }

        $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $couponService = new CouponService;
        $result = $couponService->applyToOrder($order, $request->coupon_code);

        if (! $result['valid']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Remove a coupon code from an order.
     */
    public function removeCoupon(Order $order)
    {
        if (Auth::id() !== $order->user_id) {
            abort(403);
        }

        $couponService = new CouponService;
        $couponService->removeFromOrder($order);

        return redirect()->back()->with('status', 'کد تخفیف حذف شد.');
    }
}
