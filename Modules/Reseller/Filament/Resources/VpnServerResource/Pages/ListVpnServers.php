<?php

namespace Modules\Reseller\Filament\Resources\VpnServerResource\Pages;

use Modules\Reseller\Filament\Resources\VpnServerResource;
use Filament\Resources\Pages\ListRecords;

class ListVpnServers extends ListRecords
{
    protected static string $resource = VpnServerResource::class;

    protected function getActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
