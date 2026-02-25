<?php

namespace Modules\Reseller\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnProduct extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'remote_id',
        'protocol',
        'traffic_limit',
        'period_days',
        'base_price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'traffic_limit' => 'integer',
        'period_days' => 'integer',
        'base_price' => 'decimal:2',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(VpnServer::class, 'server_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ResellerAccount::class, 'product_id');
    }
}
