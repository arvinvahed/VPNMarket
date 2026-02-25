<?php

namespace Modules\Reseller\Services\Vpn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;

class MarzbanService implements VpnServiceInterface
{
    protected ?string $token = null;

    protected function login(VpnServer $server): bool
    {
        try {
            // Check if we have a valid cached token? 
            // For now, just login every time or rely on instance cache
            if ($this->token) {
                return true;
            }

            $response = Http::asForm()->post($server->api_url . '/api/admin/token', [
                'username' => $server->username,
                'password' => $server->password,
            ]);

            if ($response->successful() && isset($response->json()['access_token'])) {
                $this->token = $response->json()['access_token'];
                return true;
            }
            
            Log::error("Marzban Login Failed: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Marzban Login Error: " . $e->getMessage());
        }
        return false;
    }

    public function createAccount(VpnServer $server, VpnProduct $product, string $username, ?string $uuid = null): array
    {
        if (!$this->login($server)) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            // Marzban uses data_limit in bytes.
            // product->traffic_limit is bytes.
            // product->period_days -> expire (timestamp)
            
            $expire = $product->period_days > 0 ? time() + ($product->period_days * 86400) : 0;
            
            // Marzban needs proxies/inbounds config.
            // We use product->remote_id as the Inbound Tag(s).
            // If multiple tags, separate by comma.
            $inboundTags = array_map('trim', explode(',', $product->remote_id));
            
            // Map protocol to inbound tags
            // Marzban expects "proxies" and "inbounds".
            // For v0.2+:
            // "proxies": { "vless": {}, "vmess": {}, ... } -> enable protocols for user
            // "inbounds": { "vless": ["tag1"], ... } -> map protocols to specific inbounds
            
            $protocol = strtolower($product->protocol);
            $proxies = [];
            $inbounds = [];
            
            // Enable the protocol
            $proxies[$protocol] = new \stdClass(); // Empty object
            
            // Assign tags to the protocol
            if (!empty($inboundTags)) {
                $inbounds[$protocol] = $inboundTags;
            }
            
            // If the user wants other protocols enabled by default, they should be in server config?
            // For now, we only enable the product's protocol.

            $payload = [
                'username' => $username,
                'proxies' => $proxies,
                'inbounds' => $inbounds,
                'expire' => $expire,
                'data_limit' => (int) $product->traffic_limit,
                'data_limit_reset_strategy' => 'no_reset',
                'status' => 'active',
                'note' => 'Reseller: ' . ($product->name ?? 'Unknown'),
            ];

            // Add config from server if exists (e.g. global overrides)
            if (!empty($server->config['marzban_overrides'])) {
                 $payload = array_merge($payload, $server->config['marzban_overrides']);
            }

            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post($server->api_url . '/api/user', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Get subscription URL
                $subPath = $data['subscription_url'] ?? ''; 
                
                // Construct full sub link
                // Marzban usually returns relative path starting with /
                $subLink = $server->sub_url . $subPath;

                return [
                    'success' => true,
                    'data' => [
                        'username' => $data['username'],
                        'uuid' => null, // Marzban users don't have a single UUID, they have per-protocol settings.
                        'subscription_url' => $subLink,
                        'raw' => $data
                    ]
                ];
            }

            Log::error("Marzban Create Failed: " . $response->body());
            return ['success' => false, 'error' => $response->body()];

        } catch (\Exception $e) {
            Log::error("Marzban Create Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): bool
    {
        if (!$this->login($server)) return false;

        try {
            $response = Http::withToken($this->token)->delete($server->api_url . "/api/user/{$identifier}");
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Marzban Delete Exception: " . $e->getMessage());
            return false;
        }
    }

    public function getAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): ?array
    {
        if (!$this->login($server)) return null;

        try {
            $response = Http::withToken($this->token)->get($server->api_url . "/api/user/{$identifier}");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function renewAccount(VpnServer $server, string $identifier, VpnProduct $product, int $daysToAdd, ?int $trafficLimit = null): bool
    {
        if (!$this->login($server)) return false;

        $user = $this->getAccount($server, $identifier, $product);
        if (!$user) {
            Log::error("Marzban Renew: Account not found on server.");
            return false;
        }

        // Calculate new expiry
        $currentExpiry = $user['expire'] ?? 0;
        
        // If expired or no expiry, start from now
        if ($currentExpiry < time()) {
            $currentExpiry = time();
        }
        
        $newExpiry = $currentExpiry + ($daysToAdd * 86400);

        $payload = [
            'expire' => $newExpiry,
            'status' => 'active', // Ensure it's active
        ];

        // Update Traffic Limit if provided
        if ($trafficLimit !== null) {
            // Marzban uses Bytes
            $payload['data_limit'] = $trafficLimit * 1024 * 1024 * 1024;
            // Usually when renewing with new traffic, we might want to reset usage or strategy
            // But here we just set the new limit.
            // If we want to reset usage: $payload['data_limit_reset_strategy'] = ...
        }

        try {
            $response = Http::withToken($this->token)
                ->put($server->api_url . "/api/user/{$identifier}", $payload);

            if ($response->successful()) {
                // Reset Traffic Usage as requested
                Http::withToken($this->token)->post($server->api_url . "/api/user/{$identifier}/reset");
                
                return true;
            }

            Log::error("Marzban Renew Failed: " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("Marzban Renew Exception: " . $e->getMessage());
            return false;
        }
    }
}
