<?php

namespace Modules\Reseller\Services\Vpn;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;
use Modules\Reseller\Models\VpnServer;
use Modules\Reseller\Models\VpnProduct;
use Illuminate\Support\Str;

class SanaeiService implements VpnServiceInterface
{
    protected $jar;

    public function __construct()
    {
        $this->jar = new \GuzzleHttp\Cookie\CookieJar();
    }

    protected function getBaseUrl(VpnServer $server): string
    {
        return rtrim($server->api_url, '/');
    }

    protected function getApiBasePath(VpnServer $server): string
    {
        $apiPath = trim((string) $server->api_path, '/');
        return $apiPath !== '' ? '/' . $apiPath : '/panel/api/inbounds';
    }

    protected function getPanelBasePath(VpnServer $server): string
    {
        $apiPath = trim((string) $server->api_path, '/');
        if ($apiPath === '') {
            return '';
        }

        $segments = explode('/', $apiPath);
        return '/' . $segments[0];
    }

    protected function getSubscriptionBaseUrl(VpnServer $server): string
    {
        return $server->sub_url;
    }

    protected function getClient(VpnServer $server): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $this->jar,
            'verify' => false,
            'timeout' => 15,
        ]);
    }

    protected function login(VpnServer $server): bool
    {
        try {
            $baseUrl = $this->getBaseUrl($server);
            $panelBase = $this->getPanelBasePath($server);
            $loginUrl = rtrim($baseUrl . $panelBase, '/') . '/login';

            $response = $this->getClient($server)->post($loginUrl, [
                'username' => $server->username,
                'password' => $server->password,
            ]);

            if ($response->successful() && $response->json('success')) {
                return true;
            }

            Log::error('Sanaei Login Failed', [
                'url' => $loginUrl,
                'status' => $response->status(),
                'body' => $response->body(),
                'json' => $response->json(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("Sanaei Login Exception: " . $e->getMessage());
            return false;
        }
    }

    public function createAccount(VpnServer $server, VpnProduct $product, string $username, ?string $uuid = null): array
    {
        if (!$this->login($server)) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        $uuid = $uuid ?? Str::uuid()->toString();
        $inboundId = (int) $product->remote_id;
        
        $expiryTime = $product->period_days > 0 ? (time() + ($product->period_days * 86400)) * 1000 : 0;
        
        $subId = Str::random(16); // Generate random subId for subscription

        $clientSettings = [
            'id' => $uuid,
            'email' => $username,
            'limitIp' => 0, // Default unlimited IP
            'totalGB' => (int) $product->traffic_limit * 1024 * 1024 * 1024,
            'expiryTime' => $expiryTime,
            'enable' => true,
            'tgId' => '',
            'subId' => $subId,
        ];
        
        // Add flow for VLESS/Reality if configured
        if ($product->protocol === 'vless' && str_contains(strtolower($product->name), 'reality')) {
             $clientSettings['flow'] = 'xtls-rprx-vision';
        }

        try {
            $baseUrl = $this->getBaseUrl($server);
            $apiBasePath = $this->getApiBasePath($server);
            $addClientUrl = rtrim($baseUrl . $apiBasePath, '/') . '/addClient';

            $response = $this->getClient($server)->post($addClientUrl, [
                'id' => $inboundId,
                'settings' => json_encode(['clients' => [$clientSettings]]) 
            ]);

            if ($response->successful() && $response->json('success')) {
                $subBase = rtrim($this->getSubscriptionBaseUrl($server), '/');
                $subUrl = $subBase . '/sub/' . $subId;

                $config = $server->config ?? [];
                $mode = $server->subscription_mode ?: ($config['subscription_mode'] ?? 'subscribe');
                $configLink = null;

                if ($mode === 'single') {
                    try {
                        $subResponse = $this->getClient($server)->get($subUrl);
                        
                        if ($subResponse->successful()) {
                            $body = trim($subResponse->body());
                            
                            // Try to decode if it looks like base64 (common for subscriptions)
                            // Use non-strict decode to handle padding issues
                            $decoded = base64_decode($body);
                            
                            // If decoding worked and contains protocol scheme, use it
                            if ($decoded && preg_match('/(vless|vmess|trojan|ss):\/\//i', $decoded)) {
                                $body = $decoded;
                            }

                            if (preg_match('/(vless|vmess|trojan|ss):\/\/[^\s"<]+/i', $body, $m)) {
                                $configLink = $this->cleanVlessLink($m[0]);
                            } else {
                                Log::warning("Sanaei Single Config: No config found in response.", [
                                    'url' => $subUrl,
                                    'body_snippet' => substr($body, 0, 100)
                                ]);
                            }
                        } else {
                            Log::error("Sanaei Single Config Fetch Failed: " . $subResponse->status(), [
                                'url' => $subUrl
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error("Sanaei Single Config Fetch Exception: " . $e->getMessage());
                    }
                }

                return [
                    'success' => true,
                    'data' => [
                        'username' => $username,
                        'uuid' => $uuid,
                        'subscription_url' => $subUrl,
                        'config_link' => $configLink,
                        'subId' => $subId,
                        'raw' => $response->json()
                    ]
                ];
            }

            Log::error("Sanaei AddClient Failed: " . $response->body());
            return ['success' => false, 'error' => $response->json('msg') ?? $response->body()];

        } catch (\Exception $e) {
            Log::error("Sanaei Create Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): bool
    {
        if (!$product) {
            Log::error("Sanaei Delete Account requires Product for Inbound ID.");
            return false;
        }

        if (!$this->login($server)) return false;
        
        $inboundId = (int) $product->remote_id;
        
        try {
            $baseUrl = $this->getBaseUrl($server);
            $apiBasePath = $this->getApiBasePath($server);
            $deleteUrl = rtrim($baseUrl . $apiBasePath, '/') . "/$inboundId/delClient/$identifier";

            $response = $this->getClient($server)->post($deleteUrl);
            
            if ($response->successful() && $response->json('success')) {
                return true;
            }
            
            Log::error("Sanaei Delete Failed: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Sanaei Delete Exception: " . $e->getMessage());
            return false;
        }
    }

    public function getAccount(VpnServer $server, string $identifier, ?VpnProduct $product = null): ?array
    {
        if (!$product) return null;
        if (!$this->login($server)) return null;

        $inboundId = (int) $product->remote_id;

        try {
            $baseUrl = $this->getBaseUrl($server);
            $apiBasePath = $this->getApiBasePath($server);
            $getUrl = rtrim($baseUrl . $apiBasePath, '/') . "/get/$inboundId";

            $response = $this->getClient($server)->get($getUrl);
            
            if ($response->successful() && $response->json('success')) {
                $obj = $response->json('obj');
                $settings = json_decode($obj['settings'], true);
                
                if (isset($settings['clients'])) {
                    foreach ($settings['clients'] as $client) {
                        if ($client['id'] === $identifier) {
                            return $client;
                        }
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function renewAccount(VpnServer $server, string $identifier, VpnProduct $product, int $daysToAdd, ?int $trafficLimit = null): bool
    {
        if (!$this->login($server)) return false;

        $client = $this->getAccount($server, $identifier, $product);
        if (!$client) {
            Log::error("Sanaei Renew: Account not found on server.");
            return false;
        }

        // Update Expiry
        $currentExpiry = $client['expiryTime'] ?? 0;
        // If expired or negative, start from now
        if ($currentExpiry < time() * 1000) {
            $currentExpiry = time() * 1000;
        }
        $newExpiry = $currentExpiry + ($daysToAdd * 86400 * 1000);
        $client['expiryTime'] = $newExpiry;

        // Update Traffic Limit
        if ($trafficLimit !== null) {
            // Convert GB to Bytes
            $client['totalGB'] = $trafficLimit * 1024 * 1024 * 1024;
        }

        // Ensure enabled
        $client['enable'] = true;

        $inboundId = (int) $product->remote_id;

        try {
            $baseUrl = $this->getBaseUrl($server);
            $apiBasePath = $this->getApiBasePath($server);
            
            // 1. Update Client Settings
            $updateUrl = rtrim($baseUrl . $apiBasePath, '/') . "/updateClient/$identifier";
            
            // Sanaei/X-UI expects 'settings' to be a JSON string containing the 'clients' array with the single client
            $settingsJson = json_encode(['clients' => [$client]]);

            $response = $this->getClient($server)->post($updateUrl, [
                'id' => $inboundId,
                'settings' => $settingsJson
            ]);

            if (!$response->successful() || !$response->json('success')) {
                Log::error("Sanaei Renew Update Failed: " . $response->body());
                return false;
            }

            // 2. Reset Traffic Usage
            // User requested that renewal should always reset the traffic usage.
            $resetUrl = rtrim($baseUrl . $apiBasePath, '/') . "/$inboundId/resetClientTraffic/" . $client['email'];
            $this->getClient($server)->post($resetUrl);

            return true;

        } catch (\Exception $e) {
            Log::error("Sanaei Renew Exception: " . $e->getMessage());
            return false;
        }
    }

    protected function cleanVlessLink(string $link): string
    {
        // 1. Check if it's a valid link (vless://...)
        if (!preg_match('/^(vless|vmess|trojan|ss):\/\//i', $link)) {
            return $link;
        }

        // 2. Parse URL components
        $parts = parse_url($link);
        if (!$parts || !isset($parts['scheme']) || !isset($parts['host'])) {
            return $link;
        }

        // 3. Clean Query Parameters
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // Define desired order: type, encryption, security
        $ordered = [];
        if (isset($query['type'])) $ordered['type'] = $query['type'];
        if (isset($query['encryption'])) $ordered['encryption'] = $query['encryption'];
        if (isset($query['security'])) $ordered['security'] = $query['security'];
        
        // Add remaining params
        foreach ($query as $k => $v) {
            if (!isset($ordered[$k])) {
                $ordered[$k] = $v;
            }
        }

        // 4. Clean Fragment (Hash)
        $fragment = $parts['fragment'] ?? '';
        // Remove stats suffix pattern: -35.00GB... or -30D...
        // Pattern matches: hyphen followed by number and unit (GB|MB|KB|B|D)
        $fragment = preg_replace('/-[\d\.]+(GB|MB|KB|B|D).*$/ui', '', $fragment);
        // Also remove just emojis if leftover or present alone
        $fragment = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}]/u', '', $fragment);
        
        // Rebuild Link
        $newLink = $parts['scheme'] . '://';
        if (isset($parts['user'])) $newLink .= $parts['user'] . '@';
        $newLink .= $parts['host'];
        if (isset($parts['port'])) $newLink .= ':' . $parts['port'];
        
        // Build query string manually to ensure correct encoding
        if (!empty($ordered)) {
            $newLink .= '?' . http_build_query($ordered);
        }
        
        if (!empty($fragment)) {
            $newLink .= '#' . $fragment;
        }

        return $newLink;
    }
}
