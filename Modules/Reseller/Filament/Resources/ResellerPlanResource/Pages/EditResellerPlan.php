<?php

namespace Modules\Reseller\Filament\Resources\ResellerPlanResource\Pages;

use Modules\Reseller\Filament\Resources\ResellerPlanResource;
use Filament\Resources\Pages\EditRecord;

class EditResellerPlan extends EditRecord
{
    protected static string $resource = ResellerPlanResource::class;

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
