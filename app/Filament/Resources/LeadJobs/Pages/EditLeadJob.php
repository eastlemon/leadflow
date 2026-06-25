<?php

namespace App\Filament\Resources\LeadJobs\Pages;

use App\Filament\Resources\LeadJobs\LeadJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLeadJob extends EditRecord
{
    protected static string $resource = LeadJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
