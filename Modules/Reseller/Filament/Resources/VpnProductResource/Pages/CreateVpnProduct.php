<?php

namespace Modules\Reseller\Filament\Resources\VpnProductResource\Pages;

use Modules\Reseller\Filament\Resources\VpnProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVpnProduct extends CreateRecord
{
    protected static string $resource = VpnProductResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
