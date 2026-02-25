<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'phone',
        'telegram_id',
        'address',
        'rejection_reason',
        'max_accounts',
        'created_accounts_count',
        'server_cost_per_account',
        'agent_balance',
        'payment_receipt_path',
        'payment_amount',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // رابطه با کاربر
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // سرورهای نماینده
    public function servers(): HasMany
    {
        return $this->hasMany(AgentServer::class);
    }

    // تراکنش‌های نماینده
    public function transactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class);
    }
    public function getAccountCostAttribute()
    {
        // استفاده از مدل جدید ResellerPlan برای سازگاری با سیستم جدید
        $plan = \Modules\Reseller\Models\ResellerPlan::where('type', 'pay_as_you_go')
            ->where('is_active', true)
            ->first();
        return $plan ? $plan->price_per_account : 30000;
    }

    // سفارشات نماینده
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
