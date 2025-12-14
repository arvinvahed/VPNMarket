<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'type', 'value', 'max_discount_amount',
        'usage_limit', 'usage_limit_per_user', 'used_count', 'min_order_amount',
        'applies_to_wallet', 'applies_to_renewal', 'plan_ids', 'starts_at', 'expires_at', 'is_active'
    ];



    protected $casts = [
        'plan_ids' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'applies_to_wallet' => 'boolean',
        'applies_to_renewal' => 'boolean',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function isValidForOrder(float $amount, ?int $planId, bool $isWallet, bool $isRenewal): bool
    {
        if ($this->min_order_amount && $amount < $this->min_order_amount) return false;
        if ($isWallet && !$this->applies_to_wallet) return false;
        if ($isRenewal && !$this->applies_to_renewal) return false;
        if (!empty($this->plan_ids) && $planId && !in_array($planId, $this->plan_ids)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;

        $userUsage = DiscountCodeUsage::where('discount_code_id', $this->id)
            ->where('user_id', auth()->id())->count();

        return $userUsage < $this->usage_limit_per_user;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percent') {
            $discount = ($amount * $this->value) / 100;
            return $this->max_discount_amount ? min($discount, $this->max_discount_amount) : $discount;
        }
        return min($this->value, $amount);
    }

    public function apply(Order $order)
    {
        $amount = $order->plan_id ? $order->plan->price : $order->amount;
        $discount = $this->calculateDiscount($amount);

        DiscountCodeUsage::create([
            'discount_code_id' => $this->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => $discount,
            'original_amount' => $amount,
        ]);

        $this->increment('used_count');
        return $discount;
    }
}
