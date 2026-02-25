<?php

namespace Modules\Reseller\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reseller_id',
        'server_id',
        'product_id',
        'username',
        'uuid',
        'subscription_url',
        'config_link',
        'status',
        'expired_at',
        'price_deducted',
        'server_response',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'price_deducted' => 'decimal:2',
        'server_response' => 'array',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(VpnServer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(VpnProduct::class);
    }
}
