<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'test_account_enabled',
        'test_account_volume_gb',
        'test_account_days',
        'test_account_max_per_user',
    ];

    protected $casts = [
        'test_account_enabled' => 'boolean',
        'test_account_volume_gb' => 'integer',
        'test_account_days' => 'integer',
        'test_account_max_per_user' => 'integer',
    ];



    public function inbounds()
    {
        return $this->hasMany(\App\Models\Inbound::class);
    }

    public function getTestAccountEnabledAttribute($value)
    {
        return (bool) $value;
    }
}
