<?php

namespace App\Filament\Resources\LeadJobs\Pages;

use App\Filament\Resources\LeadJobs\LeadJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeadJobs extends ListRecords
{
    protected static string $resource = LeadJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
