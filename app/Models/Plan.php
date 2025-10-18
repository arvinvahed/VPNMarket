<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'currency',
        'features',
        'is_popular',
        'is_active',
        'volume_gb',
        'duration_days',
        'panel_id',
        'marzneshin_service_ids',
    ];

    protected $casts = [
        'marzneshin_service_ids' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function panel()
    {
        return $this->belongsTo(Panel::class);
    }
}
