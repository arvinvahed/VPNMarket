<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $plan_id
 * @property int|null $server_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $payment_method
 * @property string|null $card_payment_receipt
 * @property string|null $nowpayments_payment_id
 * @property string|null $config_details
 * @property int $amount
 * @property string|null $source
 * @property string|null $panel_username
 * @property bool $reserved_slot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * 
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Plan|null $plan
 * @property-read \Modules\MultiServer\Models\Server|null $server
 */
class Order extends Model
{
    protected $casts = [
        'expires_at' => 'datetime',
        'amount' => 'integer',
        'reserved_slot' => 'boolean',
    ];

    protected $fillable = [
        'user_id',
        'plan_id',
        'server_id',
        'status',
        'expires_at',
        'payment_method',
        'card_payment_receipt',
        'nowpayments_payment_id',
        'config_details',
        'amount',
        'source',
        'panel_username',
        'reserved_slot',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }


    public function server()
    {

        if (class_exists('Modules\MultiServer\Models\Server')) {
            return $this->belongsTo(\Modules\MultiServer\Models\Server::class, 'server_id');
        }

        return $this->belongsTo(Plan::class, 'plan_id')->whereNull('id');
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
