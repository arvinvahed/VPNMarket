<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Models\Inbound;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\MarzbanService;
use App\Services\XUIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

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

        Auth::user()->notifications()->create([
            'type' => 'new_order_created',
            'title' => 'سفارش جدید شما ثبت شد!',
            'message' => "سفارش #{$order->id} برای پلن {$plan->name} با موفقیت ثبت شد و در انتظار پرداخت است.",
            'link' => route('order.show', $order->id),
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

        Auth::user()->notifications()->create([
            'type' => 'wallet_charge_pending',
            'title' => 'درخواست شارژ کیف پول ثبت شد!',
            'message' => "سفارش شارژ کیف پول به مبلغ " . number_format($request->amount) . " تومان در انتظار پرداخت شماست.",
            'link' => route('order.show', $order->id),
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

        Auth::user()->notifications()->create([
            'type' => 'renewal_order_created',
            'title' => 'درخواست تمدید سرویس ثبت شد!',
            'message' => "سفارش تمدید سرویس {$order->plan->name} با موفقیت ثبت شد و در انتظار پرداخت است.",
            'link' => route('order.show', $newOrder->id),
        ]);

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

        Auth::user()->notifications()->create([
            'type' => 'card_receipt_submitted',
            'title' => 'رسید پرداخت شما ارسال شد!',
            'message' => "رسید پرداخت سفارش #{$order->id} با موفقیت دریافت شد و در انتظار تایید مدیر است.",
            'link' => route('order.show', $order->id),
        ]);
        return redirect()->route('dashboard')->with('status', 'رسید شما با موفقیت ارسال شد. پس از تایید توسط مدیر، سرویس شما فعال خواهد شد.');
    }

    /**
     * Process instant payment from the user's wallet balance.
     */
    public function processWalletPayment(Order $order)
    {
        if (auth()->id() !== $order->user_id) { abort(403); }
        if (!$order->plan) { return redirect()->back()->with('error', 'این عملیات برای شارژ کیف پول مجاز نیست.'); }

        $user = auth()->user();
        $plan = $order->plan;
        $price = $plan->price;

        if ($user->balance < $price) {
            return redirect()->back()->with('error', 'موجودی کیف پول شما برای انجام این عملیات کافی نیست.');
        }

        try {
            DB::transaction(function () use ($order, $user, $plan, $price) {
                $user->decrement('balance', $price);

                $user->notifications()->create([
                    'type' => 'wallet_deducted',
                    'title' => 'کسر از کیف پول شما',
                    'message' => "مبلغ " . number_format($price) . " تومان برای سفارش #{$order->id} از کیف پول شما کسر شد.",
                    'link' => route('dashboard', ['tab' => 'order_history']),
                ]);

                $settings = Setting::all()->pluck('value', 'key');
                $success = false;
                $finalConfig = '';
                $panelType = $settings->get('panel_type');
                $isRenewal = (bool)$order->renews_order_id;

                $uniqueUsername = "user-{$user->id}-order-" . ($isRenewal ? $order->renews_order_id : $order->id);
                $newExpiresAt = $isRenewal
                    ? (new \DateTime(Order::find($order->renews_order_id)->expires_at))->modify("+{$plan->duration_days} days")
                    : now()->addDays($plan->duration_days);

                if ($panelType === 'marzban') {
                    $marzbanService = new MarzbanService($settings->get('marzban_host'), $settings->get('marzban_sudo_username'), $settings->get('marzban_sudo_password'), $settings->get('marzban_node_hostname'));
                    $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => $plan->volume_gb * 1073741824];

                    $response = $isRenewal
                        ? $marzbanService->updateUser($uniqueUsername, $userData)
                        : $marzbanService->createUser(array_merge($userData, ['username' => $uniqueUsername]));

                    if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                        $finalConfig = $marzbanService->generateSubscriptionLink($response);
                        $success = true;
                    }
                } elseif ($panelType === 'xui') {
                    if ($isRenewal) {
                        throw new \Exception('تمدید خودکار برای پنل سنایی هنوز پیاده‌سازی نشده است.');
                    }
                    $xuiService = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));
                    $inbound = Inbound::find($settings->get('xui_default_inbound_id'));
                    if (!$inbound || !$inbound->inbound_data) {
                        throw new \Exception('اطلاعات اینباند پیش‌فرض برای X-UI یافت نشد.');
                    }
                    if (!$xuiService->login()) {
                        throw new \Exception('خطا در لاگین به پنل X-UI.');
                    }

                    $inboundData = json_decode($inbound->inbound_data, true);
                    $clientData = ['email' => $uniqueUsername, 'total' => $plan->volume_gb * 1073741824, 'expiryTime' => $newExpiresAt->timestamp * 1000];
                    $response = $xuiService->addClient($inboundData['id'], $clientData);

                    if ($response && isset($response['success']) && $response['success']) {
                        $linkType = $settings->get('xui_link_type', 'single');
                        if ($linkType === 'subscription') {
                            $subId = $response['generated_subId'];
                            $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                            if ($subBaseUrl) {
                                $finalConfig = $subBaseUrl . '/sub/' . $subId;
                                $success = true;
                            }
                        } else {
                            $uuid = $response['generated_uuid'];
                            $streamSettings = json_decode($inboundData['streamSettings'], true);
                            $parsedUrl = parse_url($settings->get('xui_host'));
                            $serverIpOrDomain = !empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                            $port = $inboundData['port'];
                            $remark = $inboundData['remark'];
                            $paramsArray = ['type' => $streamSettings['network'] ?? null, 'security' => $streamSettings['security'] ?? null, 'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null), 'sni' => $streamSettings['tlsSettings']['serverName'] ?? null, 'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null];
                            $params = http_build_query(array_filter($paramsArray));
                            $fullRemark = $uniqueUsername . '|' . $remark;
                            $finalConfig = "vless://{$uuid}@{$serverIpOrDomain}:{$port}?{$params}#" . urlencode($fullRemark);
                            $success = true;
                        }
                    } else {
                        throw new \Exception('خطا در ساخت کاربر در پنل سنایی: ' . ($response['msg'] ?? 'پاسخ نامعتبر'));
                    }
                }

                if (!$success) { throw new \Exception('خطا در ارتباط با سرور برای فعال‌سازی سرویس.'); }

                // آپدیت سفارش اصلی یا سفارش جدید
                if($isRenewal) {
                    $originalOrder = Order::find($order->renews_order_id);
                    $originalOrder->update(['config_details' => $finalConfig, 'expires_at' => $newExpiresAt->format('Y-m-d H:i:s')]);
                    $user->update(['show_renewal_notification' => true]);
                    $user->notifications()->create([
                        'type' => 'service_renewed',
                        'title' => 'سرویس شما تمدید شد!',
                        'message' => "سرویس {$originalOrder->plan->name} با موفقیت تمدید شد. لطفاً لینک اشتراک خود را به‌روزرسانی کنید.",
                        'link' => route('dashboard', ['tab' => 'my_services']),
                    ]);

                } else {

                    $order->update(['config_details' => $finalConfig, 'expires_at' => $newExpiresAt]);
                    $user->notifications()->create([
                        'type' => 'service_purchased',
                        'title' => 'سرویس شما فعال شد!',
                        'message' => "سرویس {$plan->name} با موفقیت خریداری و فعال شد.",
                        'link' => route('dashboard', ['tab' => 'my_services']),
                    ]);
                }

                $order->update(['status' => 'paid', 'payment_method' => 'wallet']);
                Transaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'amount' => $price, 'type' => 'purchase', 'status' => 'completed', 'description' => ($isRenewal ? "تمدید سرویس" : "خرید سرویس") . " {$plan->name} از کیف پول"]);

                $user->notifications()->create([
                    'type' => 'wallet_charged_successful',
                    'title' => 'کیف پول شما با موفقیت شارژ شد!',
                    'message' => "مبلغ " . number_format($order->amount) . " تومان به موجودی کیف پول شما اضافه شد.",
                    'link' => route('dashboard', ['tab' => 'order_history']),
                ]);
                OrderPaid::dispatch($order);
            });
        } catch (\Exception $e) {
            Log::error('Wallet Payment Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            Auth::user()->notifications()->create([
                'type' => 'payment_failed',
                'title' => 'خطا در پرداخت با کیف پول!',
                'message' => "پرداخت سفارش شما با خطا مواجه شد: " . $e->getMessage(),
                'link' => route('dashboard', ['tab' => 'order_history']),
            ]);

            return redirect()->route('dashboard')->with('error', 'پرداخت با خطا مواجه شد: ' . $e->getMessage());
        }
        return redirect()->route('dashboard')->with('status', 'سرویس شما با موفقیت فعال شد.');
    }

    public function processCryptoPayment(Order $order)
    {
        $order->update(['payment_method' => 'crypto']);

        Auth::user()->notifications()->create([
            'type' => 'crypto_payment_info',
            'title' => 'پرداخت با ارز دیجیتال',
            'message' => "اطلاعات پرداخت با ارز دیجیتال برای سفارش #{$order->id} ثبت شد. لطفاً به زودی اقدام به پرداخت کنید.",
            'link' => route('order.show', $order->id),
        ]);

        return redirect()->back()->with('status', '💡 پرداخت با ارز دیجیتال به زودی فعال می‌شود. لطفاً از روش کارت به کارت استفاده کنید.');
    }
}

