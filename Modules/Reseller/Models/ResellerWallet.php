<?php

namespace Modules\Reseller\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerWallet extends Model
{
    protected $fillable = [
        'reseller_id',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ResellerTransaction::class, 'wallet_id');
    }
}
