<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    use HasFactory;


    protected $fillable = ['title', 'inbound_data'];

    protected $casts = [
        'inbound_data' => 'array',
    ];


    public function getPanelIdAttribute(): ?int
    {

        return $this->inbound_data['id'] ?? null;
    }
}


