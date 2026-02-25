<?php

namespace Modules\Reseller\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnServer extends Model
{
    protected $fillable = [
        'name',
        'type',
        'ip_address',
        'port',
        'subscription_port',
        'is_https',
        'username',
        'password',
        'api_path',
        'is_active',
        'capacity',
        'config',
        'subscription_mode',
        'sub_url_template',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_https' => 'boolean',
        'port' => 'integer',
        'subscription_port' => 'integer',
        'capacity' => 'integer',
        'config' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(VpnProduct::class, 'server_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ResellerAccount::class, 'server_id');
    }

    /**
     * Get the base URL for API requests.
     */
    public function getApiUrlAttribute(): string
    {
        $protocol = $this->is_https ? 'https' : 'http';
        $port = $this->port;
        // If port is standard, we might omit it, but usually safer to include unless 80/443
        
        $host = $this->ip_address;
        
        // Remove trailing slash from ip_address if exists (though unlikely if validated)
        $host = rtrim($host, '/');
        
        // Ensure api_path starts with /
        $path = '/' . ltrim($this->api_path, '/');
        
        // If api_path includes 'http', assume it's a full URL override? No, assume it's a path.
        
        // Special handling if ip_address already contains http
        if (str_starts_with($host, 'http')) {
             return rtrim($host, '/'); // Assume full URL provided in ip_address field erroneously or intentionally
        }

        return "{$protocol}://{$host}:{$port}";
    }

    /**
     * Get the base URL for subscriptions.
     */
    public function getSubUrlAttribute(): string
    {
        $protocol = $this->is_https ? 'https' : 'http';
        $host = rtrim($this->ip_address, '/');

        if (str_starts_with($host, 'http')) {
            return rtrim($host, '/');
        }

        if (!empty($this->sub_url_template)) {
            return rtrim($this->sub_url_template, '/');
        }

        $port = $this->subscription_port ?: $this->port;

        if ($port) {
            return "{$protocol}://{$host}:{$port}";
        }

        if (!empty($this->sub_url_template)) {
            return rtrim($this->sub_url_template, '/');
        }

        // Fallback to API base URL (often same domain/IP)
        return $this->api_url;
    }
}
