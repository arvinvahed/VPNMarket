<?php

namespace Modules\Reseller\Filament\Resources\VpnServerResource\Pages;

use Modules\Reseller\Filament\Resources\VpnServerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVpnServer extends CreateRecord
{
    protected static string $resource = VpnServerResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
