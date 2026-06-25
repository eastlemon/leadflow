<?php

namespace App\Filament\Resources\Connects\Pages;

use App\Filament\Resources\Connects\ConnectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConnect extends EditRecord
{
    protected static string $resource = ConnectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
