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

        'features',
        'is_popular',
        'is_active',
        'volume_gb',
        'duration_days',
        'pasargad_group_id'
    ];

    protected $casts = [
        'features' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }


    public function getDurationLabelAttribute()
    {
        $days = $this->duration_days;

        return match (true) {
            $days == 30  => '۱ ماهه',
            $days == 60  => '۲ ماهه',
            $days == 90  => '۳ ماهه',
            $days == 180 => '۶ ماهه',
            $days == 365 => '۱ ساله',
            $days == 730 => '۲ ساله',
            default      => "$days روزه",
        };
    }

    // --- دسته‌بندی: ماهانه / سه‌ماهه / سالانه ---
    public function getDurationGroupAttribute()
    {
        return match (true) {
            $this->duration_days <= 90   => 'ماهانه',
            $this->duration_days <= 365  => 'سه‌ماهه تا سالانه',
            $this->duration_days > 365   => 'سالانه+',
            default                      => 'سایر',
        };
    }


    public function getMonthlyPriceAttribute()
    {
        if ($this->duration_days == 0) return $this->price;
        return round($this->price / ($this->duration_days / 30), 0);
    }

}
