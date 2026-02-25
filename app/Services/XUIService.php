<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XUIService
{
    protected string $baseUrl;
    protected string $basePath;
    protected string $username;
    protected string $password;
    protected CookieJar $cookieJar;
    protected bool $isLoggedIn = false;

    public function __construct(string $host, string $username, string $password)
    {
        $parsedUrl = parse_url(rtrim($host, '/'));

        if ($parsedUrl === false) {
            // Fallback for simple cases or throw exception
            // If parse_url fails, it's likely a serious URL format issue (e.g. bad port)
            throw new \InvalidArgumentException("Invalid URL format: $host");
        }

        $this->baseUrl = ($parsedUrl['scheme'] ?? 'http') . '://' . ($parsedUrl['host'] ?? 'localhost') . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '');
        $this->basePath = $parsedUrl['path'] ?? '';

        if (!empty($this->basePath) && !str_starts_with($this->basePath, '/')) {
            $this->basePath = '/' . $this->basePath;
        }

        $this->username = $username;
        $this->password = $password;
        $this->cookieJar = new CookieJar();
    }

//    private function getClient(): PendingRequest
//    {
//        return Http::withOptions([
//            'cookies' => $this->cookieJar,
//            'verify' => false,
//            'timeout' => 30,
//        ]);
//    }

    private function getClient(): PendingRequest
    {
        $options = [
            'cookies' => $this->cookieJar,
            'verify' => false,
            'timeout' => 120,
            'connect_timeout' => 60,
        ];

        // افزودن پشتیبانی از پروکسی اگر در .env تعریف شده باشد
        if (env('HTTP_PROXY')) {
            $options['proxy'] = env('HTTP_PROXY');
        }

        return Http::withOptions($options)->withoutVerifying();
    }

    public function getClients(int $inboundId): array
    {
        if (!$this->login()) {
            Log::error('Cannot get clients: Login failed');
            return [];
        }

        try {
            $url = $this->baseUrl . $this->basePath . "/panel/api/inbounds/get/{$inboundId}";
            $response = $this->getClient()->get($url);

            if (!$response->successful()) {
                Log::error('Failed to fetch inbound details', [
                    'inbound_id' => $inboundId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            Log::debug('X-UI raw response for getClients', [
                'inbound_id' => $inboundId,
                'full_response' => $data
            ]);

            // 🔥 اصلاح اساسی: decode کردن رشته JSON settings
            $settings = json_decode($data['obj']['settings'] ?? '{}', true);
            $clients = $settings['clients'] ?? [];

            Log::info('Successfully fetched clients', [
                'inbound_id' => $inboundId,
                'count' => count($clients),
                'clients_list' => array_map(function($c) {
                    return ['id' => $c['id'] ?? null, 'email' => $c['email'] ?? null, 'subId' => $c['subId'] ?? null];
                }, $clients)
            ]);

            return $clients;

        } catch (\Exception $e) {
            Log::error('Exception while fetching clients', [
                'message' => $e->getMessage(),
                'inbound_id' => $inboundId,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function checkClientExists(int $inboundId, string $email): bool
    {
        $clients = $this->getClients($inboundId);
        foreach ($clients as $client) {
            if (isset($client['email']) && $client['email'] === $email) {
                return true;
            }
        }
        return false;
    }

    public function login(): bool
    {
        if ($this->isLoggedIn) {
            return true;
        }

        try {
            $loginApiUrl = $this->baseUrl . $this->basePath . '/login';

            $response = $this->getClient()->asForm()->post($loginApiUrl, [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            $responseBody = $response->body();
            $isSuccess = $response->successful() && (
                    $response->json('success') === true ||
                    Str::contains($responseBody, 'Login successful') ||
                    Str::contains($responseBody, 'success') ||
                    $response->redirect()
                );

            if ($isSuccess) {
                Log::info('XUI Login successful', ['url' => $loginApiUrl]);
                $this->isLoggedIn = true;
                return true;
            } else {
                Log::error('XUI Login Failed', [
                    'url' => $loginApiUrl,
                    'status' => $response->status(),
                    'body' => $responseBody,
                    'json' => $response->json()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('XUI Connection Exception:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function getInbounds(): array
    {
        if (!$this->login()) {
            Log::error('Cannot get inbounds: Login failed');
            return [];
        }

        try {
            $url = $this->baseUrl . $this->basePath . '/panel/api/inbounds/list';
            $response = $this->getClient()->get($url);

            if (!$response->successful()) {
                Log::error('Failed to fetch inbounds', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();
            $inbounds = $data['obj'] ?? [];
            Log::info('Successfully fetched inbounds', ['count' => count($inbounds)]);
            return $inbounds;

        } catch (\Exception $e) {
            Log::error('Exception while fetching inbounds', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function addClient(int $inboundId, array $clientData): ?array
    {
        if (!$this->login()) {
            return ['success' => false, 'msg' => 'Authentication to X-UI panel failed.'];
        }

        try {
            $uuid = Str::uuid()->toString();
            $subId = Str::random(16);

            Log::info('Creating XUI client', [
                'inbound_id' => $inboundId,
                'email' => $clientData['email'] ?? 'N/A',
                'generated_uuid' => $uuid,
                'generated_subId' => $subId
            ]);

            $clientSettings = [
                'id' => $uuid,
                'email' => $clientData['email'],
                'totalGB' => $clientData['total'] ?? 0,
                'expiryTime' => $clientData['expiryTime'] ?? 0,
                'enable' => true,
                'tgId' => '',
                'subId' => $subId,
                'limitIp' => 0,
                'flow' => '',
            ];

            $settings = json_encode(['clients' => [$clientSettings]]);
            $endpointsToTry = [
                $this->basePath . "/panel/api/inbounds/addClient",
                $this->basePath . "/panel/inbound/addClient",
                $this->basePath . "/xui/inbound/addClient"
            ];

            $response = null;
            $lastResponse = null;
            $lastError = null;

            foreach ($endpointsToTry as $endpoint) {
                $addClientUrl = $this->baseUrl . $endpoint;

                Log::info('Trying XUI addClient endpoint', [
                    'url' => $addClientUrl,
                    'inbound_id' => $inboundId
                ]);

                $currentResponse = $this->getClient()->asForm()->post($addClientUrl, [
                    'id' => $inboundId,
                    'settings' => $settings,
                ]);

                $lastResponse = $currentResponse;
                $status = $currentResponse->status();
                $responseData = $currentResponse->json();

                Log::info('XUI addClient response', [
                    'endpoint' => $endpoint,
                    'status' => $status,
                    'success' => $responseData['success'] ?? false,
                    'msg' => $responseData['msg'] ?? 'N/A'
                ]);

                if ($status === 200 && isset($responseData['success']) && $responseData['success'] === true) {
                    $response = $currentResponse;
                    Log::info('XUI addClient successful', ['endpoint' => $endpoint]);
                    break;
                } else {
                    $lastError = $responseData['msg'] ?? $currentResponse->body();
                }
            }

            if (!$response) {
                $errorMsg = "All endpoints failed. Last error: " . ($lastError ?: 'Unknown error');
                Log::error('XUI addClient failed completely', [
                    'inbound_id' => $inboundId,
                    'last_error' => $lastError,
                    'last_response_body' => $lastResponse?->body()
                ]);
                return ['success' => false, 'msg' => $errorMsg];
            }

            $responseData = $response->json();
            return array_merge($responseData, [
                'generated_uuid' => $uuid,
                'generated_subId' => $subId,
                'inbound_id' => $inboundId
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in XUI addClient', [
                'message' => $e->getMessage(),
                'inbound_id' => $inboundId,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'msg' => 'Error creating client: ' . $e->getMessage()];
        }
    }

    public function resetClientTraffic(int $inboundId, string $email): bool
    {
        if (!$this->login()) {
            Log::error('Cannot reset traffic: Login failed');
            return false;
        }

        try {
            // ✅ FIX: ساختار URL طبق داکیومنت رسمی 3x-ui
            // POST /panel/api/inbounds/{inboundId}/resetClientTraffic/{email}
            $url = $this->baseUrl . $this->basePath . "/panel/api/inbounds/{$inboundId}/resetClientTraffic/" . rawurlencode($email);

            Log::info('Resetting XUI client traffic', [
                'url' => $url,
                'inbound_id' => $inboundId,
                'email' => $email
            ]);

            $response = $this->getClient()->post($url);

            if ($response->successful() && $response->json('success')) {
                Log::info('✅ Client traffic reset successfully', ['email' => $email]);
                return true;
            } else {
                Log::error('❌ Failed to reset client traffic', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'inbound_id' => $inboundId,
                    'email' => $email
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Exception in resetClientTraffic', [
                'message' => $e->getMessage(),
                'inbound_id' => $inboundId,
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    public function updateClient(int $inboundId, string $clientId, array $clientData): ?array
    {
        if (!$this->login()) {
            return ['success' => false, 'msg' => 'Authentication failed.'];
        }

        try {
            $subId = $clientData['subId'] ?? Str::random(16);

            $clientSettings = [
                'id' => $clientId,
                'email' => $clientData['email'],
                'totalGB' => $clientData['total'] ?? 0,
                'expiryTime' => $clientData['expiryTime'] ?? 0,
                'enable' => true,
                'tgId' => '',
                'subId' => $subId,
                'limitIp' => 0,
                'flow' => '',
            ];

            $settings = json_encode(['clients' => [$clientSettings]]);

            $updateClientUrl = $this->baseUrl . $this->basePath . "/panel/api/inbounds/updateClient/{$clientId}";

            Log::info('Updating XUI client', [
                'url' => $updateClientUrl,
                'inbound_id' => $inboundId,
                'client_id' => $clientId
            ]);

            $response = $this->getClient()->asForm()->post($updateClientUrl, [
                'id' => $inboundId,
                'settings' => $settings,
            ]);

            $responseData = $response->json();

            Log::info('XUI updateClient response', [
                'status' => $response->status(),
                'success' => $responseData['success'] ?? false,
                'msg' => $responseData['msg'] ?? 'N/A'
            ]);

            return $responseData;

        } catch (\Exception $e) {
            Log::error('Exception in XUI updateClient', [
                'message' => $e->getMessage(),
                'inbound_id' => $inboundId,
                'client_id' => $clientId,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'msg' => 'Error updating client: ' . $e->getMessage()];
        }
    }
}
