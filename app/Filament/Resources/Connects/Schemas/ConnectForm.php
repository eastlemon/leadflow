<?php

namespace App\Filament\Resources\Connects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConnectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('system_name')
                    ->required(),
                TextInput::make('display_name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('tune'),
            ]);
    }
}
