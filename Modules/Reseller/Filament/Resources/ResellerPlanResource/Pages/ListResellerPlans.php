<?php

namespace Modules\Reseller\Filament\Resources\ResellerPlanResource\Pages;

use Modules\Reseller\Filament\Resources\ResellerPlanResource;
use Filament\Resources\Pages\ListRecords;

class ListResellerPlans extends ListRecords
{
    protected static string $resource = ResellerPlanResource::class;

    protected function getActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
