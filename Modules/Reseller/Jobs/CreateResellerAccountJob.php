<?php

namespace Modules\Reseller\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Reseller\Models\ResellerAccount;
use Modules\Reseller\Services\Vpn\VpnServiceFactory;
use Modules\Reseller\Services\ResellerAccountService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateResellerAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected $account;

    /**
     * Create a new job instance.
     */
    public function __construct(ResellerAccount $account)
    {
        $this->account = $account;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh model state
        $this->account->refresh();

        if ($this->account->status === 'active') {
            return;
        }
        
        // If it was somehow marked as failed but we are retrying (manual retry), we proceed.
        
        $server = $this->account->server;
        $product = $this->account->product;

        try {
            $vpnService = VpnServiceFactory::create($server);
            
            Log::info("Job Attempt {$this->attempts()}: Creating VPN account for {$this->account->username}");

            $result = $vpnService->createAccount(
                $server,
                $product,
                $this->account->username,
                $this->account->uuid
            );

            if ($result['success']) {
                $data = $result['data'];
                
                $this->account->update([
                    'status' => 'active',
                    'uuid' => $data['uuid'] ?? $this->account->uuid, // Use returned UUID or keep existing if any
                    'subscription_url' => $data['subscription_url'] ?? null,
                    'config_link' => $data['config_link'] ?? null,
                    'server_response' => $data['raw'] ?? [],
                    'expired_at' => now()->addDays($product->period_days),
                ]);
                
                Log::info("VPN account created successfully: {$this->account->username}");
            } else {
                // Throw exception to trigger retry
                throw new \Exception($result['error'] ?? 'Unknown error from VPN service');
            }

        } catch (\Exception $e) {
            Log::error("VPN account creation exception: " . $e->getMessage());
            throw $e; // Rethrow to let queue worker handle retry/failure
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("VPN account creation permanently failed for {$this->account->username}: " . $exception->getMessage());

        $this->account->refresh();
        
        // Mark as failed and save error
        $this->account->update([
            'status' => 'failed',
            'server_response' => ['error' => $exception->getMessage()],
        ]);

        // Process refund
        try {
            $service = app(ResellerAccountService::class);
            $service->refund($this->account);
            Log::info("Refunded failed account: {$this->account->username}");
        } catch (\Exception $e) {
            Log::error("Refund failed for account {$this->account->username}: " . $e->getMessage());
        }
    }
}
