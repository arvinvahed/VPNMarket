<?php

namespace Modules\Reseller\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Models\ResellerAccount;
use Modules\Reseller\Services\ResellerAccountService;
use Modules\Reseller\Services\QrCodeService;
use Illuminate\Support\Str;

class ResellerApiController extends Controller
{
    protected $accountService;
    protected $qrCodeService;

    public function __construct(ResellerAccountService $accountService, QrCodeService $qrCodeService)
    {
        $this->accountService = $accountService;
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Get reseller profile and wallet balance.
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isReseller()) {
            return response()->json(['message' => 'User is not a reseller'], 403);
        }

        $reseller = $user->reseller;
        $reseller->load('plan', 'wallet');

        return response()->json([
            'reseller' => [
                'id' => $reseller->id,
                'status' => $reseller->status,
                'plan' => $reseller->plan->name,
                'plan_type' => $reseller->plan->type,
                'balance' => $reseller->wallet ? (float) $reseller->wallet->balance : 0,
                'currency' => 'TOMAN', // Or dynamic
                'max_accounts' => $reseller->max_accounts,
                'active_accounts' => $reseller->accounts()->where('status', 'active')->count(),
            ]
        ]);
    }

    /**
     * Get available servers and products.
     */
    public function servers(Request $request)
    {
        $servers = VpnServer::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get()
            ->map(function ($server) {
                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'location' => $server->location,
                    'products' => $server->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'price' => (float) $product->price_reseller,
                            'period_days' => $product->period_days,
                            'traffic_limit' => $product->traffic_limit, // bytes
                            'protocol' => $product->protocol,
                        ];
                    })
                ];
            });

        return response()->json(['servers' => $servers]);
    }

    /**
     * List reseller accounts.
     */
    public function accounts(Request $request)
    {
        $user = $request->user();
        $reseller = $user->reseller;

        $accounts = $reseller->accounts()
            ->with('product', 'server')
            ->latest()
            ->paginate(20);

        return response()->json($accounts);
    }

    /**
     * Create a new VPN account.
     */
    public function createAccount(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:vpn_products,id',
            'username' => 'nullable|string|min:4|max:50|regex:/^[a-zA-Z0-9_-]+$/',
        ]);

        $user = $request->user();
        if (!$user->isReseller()) {
            return response()->json(['message' => 'User is not a reseller'], 403);
        }
        
        $reseller = $user->reseller;
        $product = VpnProduct::findOrFail($request->product_id);
        
        // Generate username if not provided
        $username = $request->username ?? Str::random(8);
        
        // Ensure username is unique for this reseller/server?
        // Let service handle it or check here.
        // For simplicity, we rely on service/db constraints.
        
        try {
            $account = $this->accountService->createAccountRequest($reseller, $product, $username);
            
            return response()->json([
                'message' => 'Account creation request submitted successfully.',
                'account' => $account,
                'status' => 'pending'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get account details.
     */
    public function getAccount(Request $request, $id)
    {
        $user = $request->user();
        $reseller = $user->reseller;
        
        $account = $reseller->accounts()->with('product', 'server')->find($id);
        
        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        // Generate QR code if subscription URL exists
        $qrCode = null;
        if ($account->subscription_url) {
            $qrCode = $this->qrCodeService->generateVpnQrCodeBase64(
                $account->subscription_url,
                $account->username
            );
        }

        return response()->json([
            'account' => $account,
            'qr_code' => $qrCode,
        ]);
    }
}
