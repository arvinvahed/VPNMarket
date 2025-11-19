<?php

namespace App\Filament\Resources\InboundResource\Pages;

use App\Filament\Resources\InboundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInbounds extends ListRecords
{
    protected static string $resource = InboundResource::class;

    protected function getHeaderActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}
