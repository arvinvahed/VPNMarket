<?php

namespace Modules\Reseller\Filament\Resources\VpnServerResource\Pages;

use Modules\Reseller\Filament\Resources\VpnServerResource;
use Filament\Resources\Pages\EditRecord;

class EditVpnServer extends EditRecord
{
    protected static string $resource = VpnServerResource::class;

    protected function getActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
