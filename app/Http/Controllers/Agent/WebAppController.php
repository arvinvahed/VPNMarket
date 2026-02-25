<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\TelegramBotSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Modules\Reseller\Models\ResellerPlan;
use Modules\MultiServer\Models\Location;
use Modules\Reseller\Models\ResellerRequest;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Services\Vpn\VpnServiceFactory;
use Modules\Reseller\Models\Reseller;
use Modules\Reseller\Models\ResellerAccount;

class WebAppController extends Controller
{
    public function registerForm()
    {
        $user = Auth::user();
        $agent = $user?->agent;

        Log::info('WebApp registerForm accessed', [
            'user_id' => $user?->id,
            'telegram_chat_id' => $user?->telegram_chat_id
        ]);

        if ($agent && $agent->status === 'approved') {
            return redirect()->route('webapp.agent.dashboard');
        }

        $plans = ResellerPlan::where('is_active', true)->get();
        $registrationFee = $plans->min('price') ?? 30000;

        return view('agent.register', compact('user', 'registrationFee', 'agent', 'plans'));
    }


    /**
     * ثبت درخواست نمایندگی
     */
    public function submitRegistration(Request $request)
    {
        // ✅ لاگ درخواست
        Log::info('submitRegistration called', [
            'all_data' => $request->all(),
            'has_file' => $request->hasFile('payment_receipt'),
            'user_id' => Auth::id()
        ]);

        try {
        $request->validate([
            'phone' => 'required|string|min:10',
            'telegram_id' => 'nullable|string',
            'address' => 'nullable|string',
            'plan_id' => 'required|exists:reseller_plans,id',
            'payment_receipt' => 'required|image|max:5120',
            'payment_amount' => 'required|numeric|min:30000',
        ]);

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'کاربر یافت نشد. لطفاً دوباره وارد شوید.'
                ], 401);
            }

            $existingAgent = $user->agent;
            if ($existingAgent && in_array($existingAgent->status, ['pending', 'approved'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'شما قبلاً درخواست نمایندگی داده‌اید و در حال حاضر در انتظار بررسی یا تایید شده‌اید.'
                ], 400);
            }

            $receiptPath = $request->file('payment_receipt')->store('agent_receipts', 'public');

            $agentPlan = ResellerPlan::where('id', $request->plan_id)
                ->where('is_active', true)
                ->first();

            if (!$agentPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'پلن انتخابی معتبر نیست یا غیرفعال شده است.'
                ], 422);
            }

            // ثبت درخواست نمایندگی در جدول reseller_requests برای بخش «درخواست‌های نمایندگی»
            $resellerRequest = ResellerRequest::create([
                'user_id' => $user->id,
                'plan_id' => $agentPlan->id,
                'name' => $user->name ?? 'بدون نام',
                'phone' => $request->phone,
                'telegram_username' => $request->telegram_id,
                'description' => $request->address,
                'payment_amount' => $request->payment_amount,
                'payment_receipt_path' => $receiptPath,
                'status' => 'pending',
            ]);

            if ($existingAgent && $existingAgent->status === 'rejected') {
                $existingAgent->update([
                    'status' => 'pending',
                    'phone' => $request->phone,
                    'telegram_id' => $request->telegram_id,
                    'address' => $request->address,
                    'payment_receipt_path' => $receiptPath,
                    'payment_amount' => $request->payment_amount,
                ]);
                $agent = $existingAgent;
            } else {
                $agent = Agent::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'phone' => $request->phone,
                    'telegram_id' => $request->telegram_id,
                    'address' => $request->address,
                    'payment_receipt_path' => $receiptPath,
                    'payment_amount' => $request->payment_amount,
                    'max_accounts' => 16,
                    'server_cost_per_account' => 30000,
                ]);
            }

            Log::info('Agent registration successful', [
                'agent_id' => $agent->id,
                'reseller_request_id' => $resellerRequest->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'درخواست شما با موفقیت ثبت شد. پس از بررسی با شما تماس گرفته خواهد شد.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', ['errors' => $e->errors()]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی: ' . collect($e->errors())->flatten()->first()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Agent registration failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطای سرور: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * داشبورد نماینده
     */
    public function dashboard()
    {
        $user = Auth::user();
        $agent = $user->agent;

        if (!$agent || $agent->status !== 'approved') {

            return redirect()->route('webapp.agent.register', ['user_id' => $user->telegram_chat_id]);
        }


        $servers = $agent->servers()
            ->withCount('orders')
            ->orderBy('is_active', 'desc')
            ->latest()
            ->get();

        $recentTransactions = $agent->transactions()->latest()->take(5)->get();

        $vpnServers = VpnServer::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        return view('agent.dashboard', compact('agent', 'servers', 'recentTransactions', 'vpnServers', 'user'));
    }

    public function accounts(Request $request)
    {
        $user = Auth::user();
        $agent = $user->agent;

        if (!$agent || $agent->status !== 'approved') {
            return redirect()->route('webapp.agent.register');
        }

        $reseller = Reseller::where('user_id', $user->id)->first();

        if (!$reseller) {
            $accounts = collect();
            $stats = [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'soon' => 0
            ];
        } else {
            $query = ResellerAccount::with(['server', 'product'])
                ->where('reseller_id', $reseller->id);

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                      ->orWhere('uuid', 'like', "%{$search}%");
                });
            }

            // Filter by status/category
            if ($request->filled('status')) {
                switch ($request->status) {
                    case 'active':
                        $query->where('status', 'active')->where('expired_at', '>', now());
                        break;
                    case 'expired':
                        $query->where(function($q) {
                            $q->where('status', 'expired')
                              ->orWhere('expired_at', '<=', now());
                        });
                        break;
                    case 'soon':
                        $query->where('status', 'active')
                              ->where('expired_at', '>', now())
                              ->where('expired_at', '<=', now()->addDays(3));
                        break;
                    case 'new': // Created in last 24h
                        $query->where('created_at', '>=', now()->subDay());
                        break;
                }
            }

            // Stats
            $baseQuery = ResellerAccount::where('reseller_id', $reseller->id);
            $stats = [
                'total' => $baseQuery->count(),
                'active' => $baseQuery->clone()->where('status', 'active')->where('expired_at', '>', now())->count(),
                'expired' => $baseQuery->clone()->where(function($q) {
                     $q->where('status', 'expired')->orWhere('expired_at', '<=', now());
                })->count(),
                'soon' => $baseQuery->clone()->where('status', 'active')
                    ->where('expired_at', '>', now())
                    ->where('expired_at', '<=', now()->addDays(3))->count(),
            ];

            $accounts = $query->latest()->paginate(20)->withQueryString();
        }

        return view('agent.accounts', compact('agent', 'accounts', 'user', 'stats'));
    }

    public function deleteAccount(Request $request, $id)
    {
        $user = Auth::user();
        $agent = $user->agent;
        if (!$agent || $agent->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $reseller = Reseller::where('user_id', $user->id)->first();
        $account = ResellerAccount::where('reseller_id', $reseller->id)->findOrFail($id);

        $server = $account->server;
        $product = $account->product;

        if ($server && $product) {
            try {
                $service = VpnServiceFactory::create($server);
                // Assume UUID for Sanaei/X-UI, Username for Marzban if needed
                $identifier = $account->uuid;
                if (str_contains(strtolower($server->panel_type ?? ''), 'marzban')) {
                     $identifier = $account->username;
                }

                $deleted = $service->deleteAccount($server, $identifier, $product);
                
                if (!$deleted) {
                    return response()->json(['success' => false, 'message' => 'Failed to delete from server.'], 500);
                }

            } catch (\Exception $e) {
                 Log::error("Delete Account Error: " . $e->getMessage());
                 return response()->json(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
            }
        }

        $account->delete();
        return response()->json(['success' => true, 'message' => 'Account deleted successfully.']);
    }

    public function renewAccount(Request $request, $id)
    {
        $request->validate([
            'days' => 'required|integer|min:1',
            'traffic' => 'nullable|integer|min:0', // GB
        ]);

        $user = Auth::user();
        $agent = $user->agent;
        if (!$agent || $agent->status !== 'approved') {
             return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $reseller = Reseller::where('user_id', $user->id)->first();
        $account = ResellerAccount::where('reseller_id', $reseller->id)->findOrFail($id);

        $product = $account->product;
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found. Cannot renew.'], 404);
        }

        // Calculate cost: (Base Price / Original Days) * New Days
        // If Original Days is 0 or null (unlimited?), assume base price for 30 days default?
        // Let's safe guard.
        $days = (int) $request->days;
        $periodDays = max(1, $product->period_days ?: 30);
        $pricePerDay = $product->base_price / $periodDays;
        $cost = ceil($pricePerDay * $days);
        
        if ($agent->agent_balance < $cost) {
             return response()->json(['success' => false, 'message' => 'موجودی کیف پول کافی نیست. هزینه تمدید: ' . number_format($cost) . ' تومان'], 400);
        }

        $server = $account->server;
        if ($server && $product) {
            try {
                $service = VpnServiceFactory::create($server);
                $identifier = $account->uuid;
                 if (str_contains(strtolower($server->panel_type ?? ''), 'marzban')) {
                     $identifier = $account->username;
                }

                $renewed = $service->renewAccount($server, $identifier, $product, $days, $request->traffic);
                
                if (!$renewed) {
                    return response()->json(['success' => false, 'message' => 'Failed to renew on server.'], 500);
                }
            } catch (\Exception $e) {
                Log::error("Renew Account Error: " . $e->getMessage());
                return response()->json(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
            }
        }

        // Update DB
        if ($account->expired_at < now()) {
            $account->expired_at = now()->addDays($days);
        } else {
            $account->expired_at = $account->expired_at->addDays($days);
        }
        $account->status = 'active';
        $account->save();

        // Deduct balance
        $agent->decrement('agent_balance', $cost);
        AgentTransaction::create([
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'amount' => -$cost,
            'type' => 'renewal',
            'status' => 'completed',
            'description' => "تمدید اکانت {$account->username} به مدت {$request->days} روز",
        ]);

        return response()->json(['success' => true, 'message' => 'Account renewed successfully.']);
    }

    public function buyVpnProduct(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|integer|exists:vpn_products,id',
                'username' => 'nullable|string|min:3|max:50',
            ]);

            $user = Auth::user();
            $agent = $user->agent;

            if (!$agent || $agent->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'شما نماینده تایید شده نیستید.'
                ], 403);
            }

            $product = VpnProduct::with('server')->findOrFail($request->product_id);

            if (!$product->is_active || !$product->server || !$product->server->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'این محصول یا سرور آن در دسترس نیست.'
                ], 422);
            }

            $price = (int) $product->base_price;

            if ($agent->agent_balance < $price) {
                return response()->json([
                    'success' => false,
                    'message' => 'موجودی کیف پول نمایندگی کافی نیست.'
                ], 400);
            }

            $reseller = Reseller::where('user_id', $user->id)->first();

            $customUsername = $request->input('username');
            $username = $customUsername && trim($customUsername) !== ''
                ? trim($customUsername)
                : 'agent-' . $user->id . '-' . time();

            if ($reseller) {
                $duplicate = ResellerAccount::where('reseller_id', $reseller->id)
                    ->where('server_id', $product->server_id)
                    ->where('username', $username)
                    ->first();

                if ($duplicate) {
                    return response()->json([
                        'success' => false,
                        'message' => 'این نام کاربری قبلاً روی این سرور ثبت شده است. لطفاً نام دیگری انتخاب کنید.'
                    ], 422);
                }
            }

            $server = $product->server;
            $service = VpnServiceFactory::create($server);

            $result = $service->createAccount($server, $product, $username);

            if (!($result['success'] ?? false)) {
                Log::error('Agent VPN account creation failed', [
                    'agent_id' => $agent->id,
                    'product_id' => $product->id,
                    'server_id' => $server->id,
                    'error' => $result['error'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'خطا در ساخت اکانت VPN: ' . ($result['error'] ?? 'نامشخص'),
                ], 500);
            }

            $data = $result['data'] ?? [];
            $subscriptionUrl = $data['subscription_url'] ?? null;
            $configLink = $data['config_link'] ?? null;
            $finalUsername = $data['username'] ?? $username;
            $uuid = $data['uuid'] ?? null;

            $agent->decrement('agent_balance', $price);

            AgentTransaction::create([
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'amount' => -$price,
                'type' => 'account_sale',
                'status' => 'completed',
                'description' => "خرید اکانت {$product->name} روی سرور {$server->name}",
            ]);

            if ($reseller) {
                ResellerAccount::create([
                    'reseller_id' => $reseller->id,
                    'server_id' => $server->id,
                    'product_id' => $product->id,
                    'username' => $finalUsername,
                    'uuid' => $uuid,
                    'subscription_url' => $subscriptionUrl,
                    'config_link' => $configLink,
                    'status' => 'active',
                    'expired_at' => $product->period_days > 0 ? now()->addDays($product->period_days) : null,
                    'price_deducted' => $price,
                    'server_response' => $data['raw'] ?? null,
                ]);
            }

            $agent->refresh();

            return response()->json([
                'success' => true,
                'message' => 'اکانت VPN با موفقیت ساخته شد.',
                'data' => [
                    'username' => $finalUsername,
                    'subscription_url' => $subscriptionUrl,
                    'config_link' => $configLink,
                    'server_name' => $server->name,
                    'product_name' => $product->name,
                    'price' => $price,
                    'balance' => $agent->agent_balance,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی: ' . collect($e->errors())->flatten()->first()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Agent buyVpnProduct failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطای سرور: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * فرم شارژ کیف پول
     */
    public function depositForm()
    {
        $user = Auth::user();
        $agent = $user->agent;

        if (!$agent || $agent->status !== 'approved') {
            return redirect()->route('webapp.agent.register');
        }

        $settings = TelegramBotSetting::all()->pluck('value', 'key');
        $cardNumber = $settings->get('agent_deposit_card_number', '6037 9975 **** ****');
        $cardName = $settings->get('agent_deposit_card_name', 'به نام مدیریت پنل');

        return view('agent.deposit', compact('agent', 'user', 'cardNumber', 'cardName'));
    }

    /**
     * ثبت درخواست شارژ
     */
    public function submitDeposit(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:50000',
                'receipt' => 'required|image|max:5120',
            ]);

            $user = Auth::user();
            $agent = $user->agent;

            if (!$agent || $agent->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'شما نماینده تایید شده نیستید.'
                ], 403);
            }

            $receiptPath = $request->file('receipt')->store('agent_deposits', 'public');

            AgentTransaction::create([
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'amount' => $request->amount,
                'type' => 'deposit',
                'status' => 'pending',
                'description' => 'درخواست شارژ کیف پول نماینده',
                'receipt_path' => $receiptPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'رسید شما ثبت شد. پس از تایید، موجودی شما شارژ خواهد شد.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی: ' . collect($e->errors())->flatten()->first()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Agent deposit failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطای سرور: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * فرم خرید سرور
     */
    public function buyServerForm()
    {
        $user = Auth::user();
        $agent = $user->agent;

        if (!$agent || $agent->status !== 'approved') {
            return redirect()->route('webapp.agent.register');
        }

        $vpnServers = VpnServer::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        return view('agent.buy-server', compact('agent', 'vpnServers', 'user'));
    }

    /**
     * خرید سرور
     */
    public function submitBuyServer(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|integer',
            'server_name' => 'required|string|max:50',
            'location_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $agent = $user->agent;

        if (!$agent || $agent->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'شما نماینده تایید شده نیستید.'
            ], 403);
        }

        $planPrices = [1 => 500000, 2 => 900000, 3 => 2000000];
        $planCapacities = [1 => 100, 2 => 200, 3 => 500];

        $price = $planPrices[$request->plan_id] ?? 500000;
        $capacity = $planCapacities[$request->plan_id] ?? 100;

        $locationName = null;
        if ($request->location_id && class_exists(Location::class)) {
            $location = Location::find($request->location_id);
            $locationName = $location?->name;
        }

        if ($agent->agent_balance < $price) {
            return response()->json([
                'success' => false,
                'message' => 'موجودی کافی نیست. لطفاً ابتدا کیف پول خود را شارژ کنید.'
            ], 400);
        }

        $agent->decrement('agent_balance', $price);

        AgentTransaction::create([
            'agent_id' => $agent->id,
            'user_id' => $user->id,
            'amount' => -$price,
            'type' => 'server_purchase',
            'status' => 'completed',
            'description' => $locationName
                ? "خرید سرور: {$request->server_name} ({$locationName})"
                : "خرید سرور: {$request->server_name}",
        ]);

        $server = $agent->servers()->create([
            'user_id' => $user->id,
            'name' => $request->server_name,
            'panel_type' => 'xui',
            'host' => 'pending',
            'username' => 'pending',
            'password' => 'pending',
            'capacity' => $capacity,
            'current_users' => 0,
            'is_active' => false,
            'expires_at' => now()->addDays(30),
            'monthly_cost' => $price,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'سرور با موفقیت خریداری شد. ادمین اطلاعات اتصال را برای شما تنظیم خواهد کرد.'
        ]);
    }
}
