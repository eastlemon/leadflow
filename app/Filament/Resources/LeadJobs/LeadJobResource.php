<?php

namespace App\Filament\Resources\LeadJobs;

use App\Filament\Resources\LeadJobs\Pages\CreateLeadJob;
use App\Filament\Resources\LeadJobs\Pages\EditLeadJob;
use App\Filament\Resources\LeadJobs\Pages\ListLeadJobs;
use App\Filament\Resources\LeadJobs\Schemas\LeadJobForm;
use App\Filament\Resources\LeadJobs\Tables\LeadJobsTable;
use App\Models\LeadJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LeadJobResource extends Resource
{
    protected static ?string $model = LeadJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LeadJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadJobsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadJobs::route('/'),
            'create' => CreateLeadJob::route('/create'),
            'edit' => EditLeadJob::route('/{record}/edit'),
        ];
    }
}
