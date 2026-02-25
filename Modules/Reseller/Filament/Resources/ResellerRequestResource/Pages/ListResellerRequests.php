<?php

namespace Modules\Reseller\Filament\Resources\ResellerRequestResource\Pages;

use Modules\Reseller\Filament\Resources\ResellerRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListResellerRequests extends ListRecords
{
    protected static string $resource = ResellerRequestResource::class;

    protected function getActions(): array
    {
        return [
            // No Create Action for Admin usually, but maybe for manual entry?
            // Let's omit it for now as per requirement (Bot request).
        ];
    }
}
