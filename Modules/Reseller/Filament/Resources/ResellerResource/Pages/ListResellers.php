<?php

namespace Modules\Reseller\Filament\Resources\ResellerResource\Pages;

use Modules\Reseller\Filament\Resources\ResellerResource;
use Filament\Resources\Pages\ListRecords;

class ListResellers extends ListRecords
{
    protected static string $resource = ResellerResource::class;

    protected function getActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
