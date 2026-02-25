<?php

namespace Modules\Reseller\Filament\Resources\ResellerPlanResource\Pages;

use Modules\Reseller\Filament\Resources\ResellerPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateResellerPlan extends CreateRecord
{
    protected static string $resource = ResellerPlanResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
