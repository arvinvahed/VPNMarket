<?php

namespace Modules\Reseller\Filament\Resources\VpnProductResource\Pages;

use Modules\Reseller\Filament\Resources\VpnProductResource;
use Filament\Resources\Pages\EditRecord;

class EditVpnProduct extends EditRecord
{
    protected static string $resource = VpnProductResource::class;

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
