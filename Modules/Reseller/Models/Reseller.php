<?php

namespace Modules\Reseller\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'telegram_username',
        'phone',
        'description',
        'max_accounts',
    ];

    protected $casts = [
        'max_accounts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ResellerPlan::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(ResellerWallet::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ResellerAccount::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ResellerLog::class);
    }
}
