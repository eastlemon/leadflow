<?php

namespace App\Filament\Resources\LeadJobs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class LeadJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('lead_id')
                    ->relationship('lead', 'id')
                    ->required(),
                TextInput::make('system_name')
                    ->required(),
                TextInput::make('stage')
                    ->required(),
                TextInput::make('status')
                    ->required(),
                TextInput::make('external_id'),
                Textarea::make('error')
                    ->columnSpanFull(),
                DateTimePicker::make('finished_at'),
            ]);
    }
}
