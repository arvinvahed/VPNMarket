<?php

namespace Modules\Reseller\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Services\Vpn\VpnServiceFactory;
use Modules\Reseller\Models\ResellerAccount;
use Illuminate\Support\Facades\Log;

class TestVpnController extends Controller
{
    /**
     * Test VPN account creation with MarzbanService.
     */
    public function testMarzbanAccount(Request $request)
    {
        $request->validate([
            'server_id' => 'required|exists:vpn_servers,id',
            'product_id' => 'required|exists:vpn_products,id',
            'username' => 'nullable|string|min:4|max:50',
        ]);

        $server = VpnServer::findOrFail($request->server_id);
        $product = VpnProduct::findOrFail($request->product_id);
        $username = $request->username ?? 'test_user_' . time();

        try {
            $vpnService = VpnServiceFactory::create($server);
            
            Log::info("Testing Marzban account creation", [
                'server' => $server->name,
                'product' => $product->name,
                'username' => $username,
                'inbound_tags' => $product->remote_id,
            ]);

            $result = $vpnService->createAccount($server, $product, $username);

            if ($result['success']) {
                // Create a test account record for tracking
                $account = ResellerAccount::create([
                    'reseller_id' => 1, // Test reseller ID
                    'server_id' => $server->id,
                    'product_id' => $product->id,
                    'username' => $username,
                    'uuid' => $result['data']['uuid'] ?? null,
                    'subscription_url' => $result['data']['subscription_url'] ?? null,
                    'status' => 'active',
                    'server_response' => $result['data']['raw'] ?? [],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'VPN account created successfully',
                    'account' => [
                        'id' => $account->id,
                        'username' => $username,
                        'subscription_url' => $result['data']['subscription_url'] ?? null,
                        'server_data' => $result['data']['raw'] ?? null,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error("Test VPN account creation failed", [
                'error' => $e->getMessage(),
                'server' => $server->name,
                'product' => $product->name,
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test VPN account deletion.
     */
    public function testDeleteAccount(Request $request, $id)
    {
        $account = ResellerAccount::findOrFail($id);
        $server = $account->server;
        $product = $account->product;

        try {
            $vpnService = VpnServiceFactory::create($server);
            
            $result = $vpnService->deleteAccount($server, $account->username, $product);

            if ($result) {
                $account->update(['status' => 'deleted']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'VPN account deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete account'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}