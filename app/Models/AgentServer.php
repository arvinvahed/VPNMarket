<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentServer extends Model
{
    protected $fillable = [
        'agent_id', 'user_id', 'name', 'panel_type', 'host', 'username', 'password',
        'inbound_id', 'capacity', 'current_users', 'is_active',
        'expires_at', 'monthly_cost', 'billing_cycle',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'agent_server_id');
    }

    // ✅ متدهای کمکی جدید برای تشخیص نوع پنل

    public function isMarzban(): bool
    {
        return strtolower($this->panel_type) === 'marzban';
    }

    public function isXui(): bool
    {
        // شامل xui، sanaei، alireza و ...
        return in_array(strtolower($this->panel_type), ['xui', 'sanaei', 'alireza', 'hexos']);
    }

    // ✅ ساخت آدرس کامل (اضافه کردن https اگر نباشد)
    public function getFullUrlAttribute(): string
    {
        if ($this->host === 'pending') return '';

        $url = $this->host;
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }
        return rtrim($url, '/');
    }

    // ✅ محاسبه درصد مصرف
    public function getUsagePercentAttribute(): int
    {
        if ($this->capacity <= 0) return 0;

        $count = $this->current_users;
        return min(100, round(($count / $this->capacity) * 100));
    }
}
