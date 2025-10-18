<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Panel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'panel_type',
        'username',
        'password',
        'api_token',
        'extra',
        'is_active',
    ];

    protected $casts = [
        'extra' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];

    /**
     * Encrypt password before saving
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Encrypt API token before saving
     */
    protected function apiToken(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Crypt::decryptString($value) : null,
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Get panel credentials for API usage
     */
    public function getCredentials(): array
    {
        return [
            'url' => $this->url,
            'username' => $this->username,
            'password' => $this->password,
            'api_token' => $this->api_token,
            'extra' => $this->extra ?? [],
        ];
    }
}
