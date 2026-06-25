<?php

namespace App\Filament\Resources\UserConnects\Pages;

use App\Filament\Resources\UserConnects\UserConnectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserConnect extends EditRecord
{
    protected static string $resource = UserConnectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
