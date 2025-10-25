<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'amount',
        'type',
        'status',
        'description',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * ثابت‌ها برای انواع تراکنش‌ها
     * برای جلوگیری از خطای تایپی و خوانایی بیشتر کد
     */
    public const TYPE_DEPOSIT   = 'deposit';
    public const TYPE_PURCHASE  = 'purchase';
    public const TYPE_REFUND    = 'refund';
    public const TYPE_WITHDRAWAL= 'withdrawal';

    /**
     * ثابت‌ها برای وضعیت‌های تراکنش
     */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';


    /**
     * کاربر مرتبط با تراکنش
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * سفارش مرتبط با تراکنش (اختیاری)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }


    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
