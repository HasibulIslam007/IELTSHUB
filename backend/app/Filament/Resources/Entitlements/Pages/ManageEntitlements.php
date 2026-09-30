<?php

namespace App\Filament\Resources\Entitlements\Pages;

use App\Filament\Resources\Entitlements\EntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEntitlements extends ManageRecords
{
    protected static string $resource = EntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
