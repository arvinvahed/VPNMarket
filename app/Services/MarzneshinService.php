<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarzneshinService
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected string $nodeHostname;

    protected ?string $accessToken = null;

    public function __construct(string $baseUrl, string $username, string $password, string $nodeHostname)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->nodeHostname = $nodeHostname;
    }

    public function login(): bool
    {
        try {
            $response = Http::asForm()->post($this->baseUrl.'/api/admins/token', [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful() && isset($response->json()['access_token'])) {
                $this->accessToken = $response->json()['access_token'];

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Marzneshin Login Exception:', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function createUser(array $userData): ?array
    {
        if (! $this->accessToken) {
            if (! $this->login()) {
                return ['detail' => 'Authentication failed'];
            }
        }

        try {
            // Map our application data to Marzneshin API format
            $apiData = [
                'username' => $userData['username'],
                'expire_strategy' => 'fixed_date',
                'expire_date' => $this->convertTimestampToIso8601($userData['expire']),
                'data_limit' => $userData['data_limit'],
                'data_limit_reset_strategy' => 'no_reset',
                'service_ids' => $userData['service_ids'] ?? [],
            ];

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->baseUrl.'/api/users', $apiData);

            Log::info('Marzneshin Create User Response:', $response->json() ?? ['raw' => $response->body()]);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Marzneshin Create User Exception:', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Convert Unix timestamp to ISO 8601 datetime format
     *
     * @param  int  $timestamp  Unix timestamp
     * @return string ISO 8601 formatted datetime
     */
    private function convertTimestampToIso8601(int $timestamp): string
    {
        return date('c', $timestamp);
    }

    public function updateUser(string $username, array $userData): ?array
    {
        if (! $this->accessToken) {
            if (! $this->login()) {
                return null;
            }
        }

        try {
            $apiData = [
                'expire_strategy' => 'fixed_date',
                'expire_date' => $this->convertTimestampToIso8601($userData['expire']),
                'data_limit' => $userData['data_limit'],
            ];

            if (isset($userData['service_ids'])) {
                $apiData['service_ids'] = $userData['service_ids'];
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->put($this->baseUrl."/api/users/{$username}", $apiData);

            Log::info('Marzneshin Update User Response:', $response->json() ?? ['raw' => $response->body()]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Marzneshin Update User Exception:', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function generateSubscriptionLink(array $userApiResponse): string
    {
        $subscriptionUrl = $userApiResponse['subscription_url'];

        // If the subscription URL is already absolute, return as is
        if (preg_match('#^https?://#i', $subscriptionUrl)) {
            return "لینک سابسکریپشن شما (در تمام برنامه‌ها import کنید):\n".$subscriptionUrl;
        }

        // Ensure exactly one slash between hostname and path
        $link = rtrim($this->nodeHostname, '/').'/'.ltrim($subscriptionUrl, '/');

        return "لینک سابسکریپشن شما (در تمام برنامه‌ها import کنید):\n".$link;
    }

    public function listServices(): array
    {
        if (! $this->accessToken) {
            if (! $this->login()) {
                return [];
            }
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(10)
                ->connectTimeout(5)
                ->get($this->baseUrl.'/api/services');

            if ($response->successful()) {
                $data = $response->json();
                $services = [];

                // Handle paginated response - items could be directly in response or in 'items' key
                $items = $data['items'] ?? $data;

                if (is_array($items)) {
                    foreach ($items as $service) {
                        if (isset($service['id']) && isset($service['name'])) {
                            $services[] = [
                                'id' => $service['id'],
                                'name' => $service['name'],
                            ];
                        }
                    }
                }

                return $services;
            }

            Log::error('Marzneshin List Services failed:', ['status' => $response->status(), 'body' => $response->body()]);

            return [];
        } catch (\Exception $e) {
            Log::error('Marzneshin List Services Exception:', ['message' => $e->getMessage()]);

            return [];
        }
    }

    public function enableUser(string $username): ?array
    {
        if (! $this->accessToken) {
            if (! $this->login()) {
                return null;
            }
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->baseUrl."/api/users/{$username}/enable");

            Log::info('Marzneshin Enable User Response:', $response->json() ?? ['raw' => $response->body()]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Marzneshin Enable User Exception:', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function disableUser(string $username): ?array
    {
        if (! $this->accessToken) {
            if (! $this->login()) {
                return null;
            }
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->baseUrl."/api/users/{$username}/disable");

            Log::info('Marzneshin Disable User Response:', $response->json() ?? ['raw' => $response->body()]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Marzneshin Disable User Exception:', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function resetUser(string $username): ?array
    {
        if (! $this->accessToken) {
            if (! $this->login()) {
                return null;
            }
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($this->baseUrl."/api/users/{$username}/reset");

            Log::info('Marzneshin Reset User Response:', $response->json() ?? ['raw' => $response->body()]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Marzneshin Reset User Exception:', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
