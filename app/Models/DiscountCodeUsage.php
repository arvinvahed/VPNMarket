<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCodeUsage extends Model
{
    protected $fillable = [
        'discount_code_id', 'user_id', 'order_id', 'discount_amount', 'original_amount'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
    ];
}
