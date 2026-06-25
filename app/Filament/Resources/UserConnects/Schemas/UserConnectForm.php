<?php

namespace App\Filament\Resources\UserConnects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserConnectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('system_name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('display_name'),
                TextInput::make('tune'),
            ]);
    }
}
