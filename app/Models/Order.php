<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
class Order extends Model
{

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'expires_at',
        'payment_method', 'card_payment_receipt', 'nowpayments_payment_id',
        'config_details',
        'amount',
        'source',
        'panel_username',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function store(Plan $plan)
    {

        return view('payment.choose', ['plan' => $plan]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            Log::info('Order is being created', [
                'panel_username' => $order->panel_username,
                'user_id' => $order->user_id
            ]);
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
