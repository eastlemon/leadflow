<?php

namespace App\Filament\Resources\UserConnects\Pages;

use App\Filament\Resources\UserConnects\UserConnectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserConnects extends ListRecords
{
    protected static string $resource = UserConnectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
