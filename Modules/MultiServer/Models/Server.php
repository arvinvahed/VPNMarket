<?php

namespace Modules\MultiServer\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $table = 'ms_servers';

    protected $fillable = [
        'location_id',
        'type', // xui, marzban
        'name',
        'ip_address',
        'port',
        'username',
        'password',
        'marzban_node_hostname',
        'is_https',
        'path',
        'inbound_id',
        'capacity',
        'current_users',
        'is_active',
        'link_type',
        'subscription_domain',
        'subscription_path',
        'subscription_port',
        'tunnel_address',
        'tunnel_port',
        'tunnel_is_https',
    ];

    protected $casts = [
        'is_https' => 'boolean',
        'is_active' => 'boolean',
        'tunnel_is_https' => 'boolean',
        'port' => 'integer',
        'subscription_port' => 'integer',
        'tunnel_port' => 'integer',
        'capacity' => 'integer',
        'current_users' => 'integer',
        'inbound_id' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * پاکسازی خودکار آدرس IP (حذف http/https و پورت و مسیرهای اضافی مثل /dashboard)
     */
    public function setIpAddressAttribute($value)
    {
        $clean = preg_replace('#^https?://#i', '', $value); // حذف http/https
        $clean = preg_replace('#/.*$#', '', $clean);       // حذف هر چیزی بعد از اولین اسلش (مثل /dashboard)
        $clean = preg_replace('#:\d+$#', '', $clean);       // حذف پورت از انتهای آدرس
        $this->attributes['ip_address'] = $clean;
    }

    /**
     * ساخت آدرس کامل پنل برای اتصال API
     */
    public function getFullHostAttribute(): string
    {
        $protocol = $this->is_https ? 'https' : 'http';
        
        // اگر پورت ست نشده باشد، از پیش‌فرض پروتکل استفاده کن
        if (empty($this->port)) {
            $port = $this->is_https ? 443 : 80;
        } else {
            $port = $this->port;
        }

        $path = $this->path ?? '/';
        
        // Marzban might not use path in the same way, but usually it's root
        if ($this->type === 'marzban') {
             // Marzban usually doesn't have path prefix like X-UI might
             $path = '/';
        }

        return "{$protocol}://{$this->ip_address}:{$port}{$path}";
    }
    
    public function isMarzban(): bool
    {
        return $this->type === 'marzban';
    }

    public function isXui(): bool
    {
        return $this->type === 'xui';
    }
}
