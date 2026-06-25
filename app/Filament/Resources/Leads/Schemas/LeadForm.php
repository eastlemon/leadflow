<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('inn')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('first_name'),
                TextInput::make('last_name'),
                TextInput::make('middle_name'),
                TextInput::make('company_name'),
                TextInput::make('city'),
                TextInput::make('region'),
                TextInput::make('okved'),
                TextInput::make('extra'),
                TextInput::make('source')
                    ->required()
                    ->default('skorozvon'),
            ]);
    }
}
