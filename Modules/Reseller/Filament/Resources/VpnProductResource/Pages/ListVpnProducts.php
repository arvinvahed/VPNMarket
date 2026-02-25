<?php

namespace Modules\Reseller\Filament\Resources\VpnProductResource\Pages;

use Modules\Reseller\Filament\Resources\VpnProductResource;
use Filament\Resources\Pages\ListRecords;

class ListVpnProducts extends ListRecords
{
    protected static string $resource = VpnProductResource::class;

    protected function getActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
