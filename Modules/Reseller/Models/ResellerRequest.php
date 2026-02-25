<?php

namespace Modules\Reseller\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerRequest extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'name',
        'phone',
        'telegram_username',
        'description',
        'payment_amount',
        'payment_receipt_path',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ResellerPlan::class, 'plan_id');
    }
}
