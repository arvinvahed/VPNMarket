<?php

namespace Modules\Reseller\Filament\Resources\ResellerResource\Pages;

use Modules\Reseller\Filament\Resources\ResellerResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Reseller\Models\ResellerWallet;

class CreateReseller extends CreateRecord
{
    protected static string $resource = ResellerResource::class;

    protected function afterCreate(): void
    {
        // Create wallet for new reseller
        ResellerWallet::create([
            'reseller_id' => $this->record->id,
            'balance' => 0,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
