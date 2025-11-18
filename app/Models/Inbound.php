<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{

    protected $fillable = ['inbound_data'];
    protected $casts = [
        'inbound_data' => 'array',
    ];

    // اگر می‌خوای این attributeها همیشه در آرایه/JSON باشن:
    protected $appends = ['panel_id', 'is_active', 'remark', 'dropdown_label'];

    public function getPanelIdAttribute(): ?int
    {
        return $this->inbound_data['id'] ?? null;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->inbound_data['enable'] ?? false;
    }

    public function getRemarkAttribute(): ?string
    {
        return $this->inbound_data['remark'] ?? null;
    }

    public function getDropdownLabelAttribute(): string
    {
        $panelId = $this->panel_id ?? 'N/A';
        $remark = $this->remark ?? 'بدون عنوان';
        $protocol = $this->inbound_data['protocol'] ?? 'unknown';
        $port = $this->inbound_data['port'] ?? '-';

        return "{$remark} (ID: {$panelId}) - {$protocol}:{$port}";
    }
}
