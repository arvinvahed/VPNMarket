<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'currency',
        'max_uses',
        'max_uses_per_user',
        'uses_count',
        'start_at',
        'expires_at',
        'active',
        'applies_to',
        'plan_id',
        'provider_id',
        'created_by_admin_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'active' => 'boolean',
        'start_at' => 'datetime',
        'expires_at' => 'datetime',
        'uses_count' => 'integer',
        'max_uses' => 'integer',
        'max_uses_per_user' => 'integer',
    ];

    /**
     * Get the plan that this promo code applies to.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the admin who created this promo code.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    /**
     * Get the orders that used this promo code.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Automatically uppercase the code when setting.
     */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtoupper($value);
    }

    /**
     * Check if the promo code is currently valid.
     */
    public function isValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->start_at && now()->isBefore($this->start_at)) {
            return false;
        }

        if ($this->expires_at && now()->isAfter($this->expires_at)) {
            return false;
        }

        if ($this->max_uses && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Check if the promo code can be used by a specific user.
     */
    public function canBeUsedByUser(?int $userId): bool
    {
        if (!$userId || !$this->max_uses_per_user) {
            return true;
        }

        $userUsageCount = Order::where('user_id', $userId)
            ->where('promo_code_id', $this->id)
            ->where('status', 'paid')
            ->count();

        return $userUsageCount < $this->max_uses_per_user;
    }

    /**
     * Check if the promo code applies to a specific plan.
     */
    public function appliesToPlan(?int $planId): bool
    {
        if ($this->applies_to === 'all') {
            return true;
        }

        if ($this->applies_to === 'plan' && $this->plan_id === $planId) {
            return true;
        }

        return false;
    }

    /**
     * Calculate the discount amount for a given price.
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->discount_type === 'percent') {
            return round($price * ($this->discount_value / 100), 0);
        }

        // For fixed discount
        return min($this->discount_value, $price);
    }
}
