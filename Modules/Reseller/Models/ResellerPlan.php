<?php

namespace Modules\Reseller\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'account_limit',
        'price',
        'price_per_account',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'account_limit' => 'integer',
        'price' => 'decimal:2',
        'price_per_account' => 'decimal:2',
    ];

    public function resellers(): HasMany
    {
        return $this->hasMany(Reseller::class, 'plan_id');
    }
}
