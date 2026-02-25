<?php

namespace Modules\Reseller\Services;

use Modules\Reseller\Models\Reseller;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Models\ResellerAccount;
use Modules\Reseller\Jobs\CreateResellerAccountJob;
use Illuminate\Support\Facades\DB;
use Modules\Reseller\Models\ResellerTransaction;

class ResellerAccountService
{
    /**
     * Create a new account request.
     * 
     * @param Reseller $reseller
     * @param VpnProduct $product
     * @param string $username
     * @return ResellerAccount
     * @throws \Exception
     */
    public function createAccountRequest(Reseller $reseller, VpnProduct $product, string $username): ResellerAccount
    {
        if ($reseller->status !== 'active') {
            throw new \Exception('Reseller account is not active.');
        }

        if (!$product->is_active) {
            throw new \Exception('Product is not active.');
        }

        if (!$product->server->is_active) {
            throw new \Exception('Server is not active.');
        }
        
        // Check if username already exists on this server?
        // Ideally check globally or per server.
        // For now, let's assume username uniqueness is handled by DB constraint or caller.
        // ResellerAccount table should have unique(server_id, username) index? Or just username?
        // Let's rely on catch block for duplicate entry.

        return DB::transaction(function () use ($reseller, $product, $username) {
            $plan = $reseller->plan;
            $price = $product->base_price;
            
            // Apply discount
            if ($plan->discount_percent > 0) {
                $price = $price * (1 - ($plan->discount_percent / 100));
            }

            if ($plan->type === 'quota') {
                // Check quota
                $currentCount = $reseller->accounts()->where('status', 'active')->count(); // Or all non-deleted?
                // Quota usually means total active accounts allowed.
                if ($reseller->max_accounts > 0 && $currentCount >= $reseller->max_accounts) {
                    throw new \Exception('Account limit reached for this plan.');
                }
                // Price is 0 for quota based? Or they paid for the plan?
                // User said: "Two plans: quota based / pay as you go".
                // If quota based, maybe per-account creation is free (covered by plan subscription)?
                // Let's assume price is 0 for quota users for now, OR they have balance too?
                // Usually quota users pay monthly fee for N accounts.
                $price = 0; 
            } else {
                // Pay as you go
                // Check wallet balance
                if ($reseller->wallet->balance < $price) {
                    throw new \Exception('Insufficient wallet balance.');
                }
                
                // Deduct balance
                $reseller->wallet->decrement('balance', $price);
                
                // Log transaction
                ResellerTransaction::create([
                    'reseller_id' => $reseller->id,
                    'amount' => -$price,
                    'type' => 'purchase',
                    'description' => "Purchase account $username ({$product->name})",
                ]);
            }

            // Create Pending Account
            $account = ResellerAccount::create([
                'reseller_id' => $reseller->id,
                'server_id' => $product->server_id,
                'product_id' => $product->id,
                'username' => $username,
                'status' => 'pending',
                'price_deducted' => $price,
            ]);

            // Dispatch Job
            CreateResellerAccountJob::dispatch($account);

            return $account;
        });
    }

    /**
     * Refund a failed account creation.
     */
    public function refund(ResellerAccount $account): void
    {
        if ($account->status !== 'failed') {
            return;
        }

        if ($account->price_deducted > 0) {
            DB::transaction(function () use ($account) {
                $reseller = $account->reseller;
                $reseller->wallet->increment('balance', $account->price_deducted);
                
                ResellerTransaction::create([
                    'reseller_id' => $reseller->id,
                    'amount' => $account->price_deducted,
                    'type' => 'refund',
                    'description' => "Refund failed account {$account->username}",
                ]);
                
                $account->update(['price_deducted' => 0]); // Mark as refunded
            });
        }
    }
}
