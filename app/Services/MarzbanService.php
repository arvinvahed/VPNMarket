<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarzbanService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $nodeHostname;
    protected ?string $accessToken = null;

    public function __construct(string $baseUrl, string $username, string $password, string $nodeHostname)
    {
        // Remove /dashboard if present
        $baseUrl = str_ireplace('/dashboard', '', $baseUrl);
        $this->baseUrl = rtrim($baseUrl, '/');
        
        $this->username = $username;
        $this->password = $password;

        // Clean node hostname
        $nodeHostname = trim($nodeHostname);
        
        // Remove any leading slashes (e.g. if user entered /https://...)
        $nodeHostname = ltrim($nodeHostname, '/');
        
        // Remove trailing slashes
        $nodeHostname = rtrim($nodeHostname, '/');
        
        // Ensure scheme exists
        if (!preg_match("~^(?:f|ht)tps?://~i", $nodeHostname)) {
            $nodeHostname = "https://" . $nodeHostname;
        }

        // Double check for duplicate scheme (e.g. https:///https://)
        while (str_starts_with($nodeHostname, 'https:///')) {
             $nodeHostname = str_replace('https:///', 'https://', $nodeHostname);
        }

        $this->nodeHostname = $nodeHostname;
    }



    public function login(): bool
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/api/admin/token', [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful() && isset($response->json()['access_token'])) {
                $this->accessToken = $response->json()['access_token'];
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Marzban Login Exception:', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function createUser(array $userData): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) {
                return ['detail' => 'Authentication failed'];
            }
        }

        try {
            // Fix: Marzban expects a dictionary (JSON Object) for inbounds, not an array.
            // If empty, it should be {}, not [].
            $inbounds = $userData['inbounds'] ?? [];
            
            // If it's an array and empty, convert to stdClass to ensure {} in JSON
            if (is_array($inbounds) && empty($inbounds)) {
                $inbounds = new \stdClass();
            }
            // If it's an array and not empty, it's already fine as a dictionary if keys are strings (associative array)
            // But if it's a sequential array (list), it might be wrong if Marzban expects key-value pairs.
            // Assuming Marzban inbounds structure is like {"tag": ["protocol"]} or similar dictionary structure.
            
            $payload = [
                'username' => $userData['username'],
                'inbounds' => $inbounds,
                'expire' => $userData['expire'],
                'data_limit' => (int)$userData['data_limit'],
                'data_limit_reset_strategy' => 'no_reset',
            ];

            if (isset($userData['proxies']) && count((array)$userData['proxies']) > 0) {
                $payload['proxies'] = $userData['proxies'];
            }

            // Manually encode to JSON to ensure correct types (especially inbounds as {})
            $jsonPayload = json_encode($payload);
            
            // Debug logging
            Log::info('Marzban Create User Payload (Raw JSON):', [
                'url' => $this->baseUrl . '/api/user',
                'json_payload' => $jsonPayload,
                'inbounds_type' => gettype($inbounds),
                'inbounds_is_object' => is_object($inbounds),
                'inbounds_is_array' => is_array($inbounds)
            ]);

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post($this->baseUrl . '/api/user');

            Log::info('Marzban Create User Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();

        } catch (\Exception $e) {
            Log::error('Marzban Create User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function updateUser(string $username, array $userData): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->put($this->baseUrl . "/api/user/{$username}", [
                    'expire' => $userData['expire'],
                    'data_limit' => $userData['data_limit'],
                ]);

            Log::info('Marzban Update User Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Marzban Update User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function getUser(string $username): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->baseUrl . "/api/user/{$username}");

            if ($response->successful()) {
                return $response->json();
            }
            return null;
        } catch (\Exception $e) {
            Log::error('Marzban Get User Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function resetUserTraffic(string $username): ?array
    {
        if (!$this->accessToken) {
            if (!$this->login()) return null;
        }

        try {
            // Reset user traffic by setting used_traffic to 0
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->baseUrl . "/api/user/{$username}/reset");

            Log::info('Marzban Reset User Traffic Response:', $response->json() ?? ['raw' => $response->body()]);
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Marzban Reset User Traffic Exception:', ['message' => $e->getMessage()]);
            return null;
        }
    }


    public function generateSubscriptionLink(array $userApiResponse): string
    {
        $subscriptionUrl = trim($userApiResponse['subscription_url']);
        
        // If Marzban returns a full URL (check for http/https at start)
        if (preg_match("~^(?:f|ht)tps?://~i", $subscriptionUrl)) {
            return $subscriptionUrl;
        }
        
        // If it starts with /http... (weird case where Marzban returns relative path starting with http?)
        // Or if there is a double slash issue
        if (preg_match("~^/(?:f|ht)tps?://~i", $subscriptionUrl)) {
             return ltrim($subscriptionUrl, '/');
        }
        
        // Ensure one slash between host and path
        if (!str_starts_with($subscriptionUrl, '/')) {
            $subscriptionUrl = '/' . $subscriptionUrl;
        }

        // Ensure base doesn't have trailing slash (handled in constructor but safety first)
        $base = rtrim($this->nodeHostname, '/');

        return $base . $subscriptionUrl;
    }
}
