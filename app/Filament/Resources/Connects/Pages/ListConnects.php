<?php

namespace App\Filament\Resources\Connects\Pages;

use App\Filament\Resources\Connects\ConnectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnects extends ListRecords
{
    protected static string $resource = ConnectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
