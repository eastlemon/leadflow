<?php

namespace App\Filament\Resources\UserConnects;

use App\Filament\Resources\UserConnects\Pages\CreateUserConnect;
use App\Filament\Resources\UserConnects\Pages\EditUserConnect;
use App\Filament\Resources\UserConnects\Pages\ListUserConnects;
use App\Filament\Resources\UserConnects\Schemas\UserConnectForm;
use App\Filament\Resources\UserConnects\Tables\UserConnectsTable;
use App\Models\UserConnect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserConnectResource extends Resource
{
    protected static ?string $model = UserConnect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return UserConnectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserConnectsTable::configure($table);
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
            'index' => ListUserConnects::route('/'),
            'create' => CreateUserConnect::route('/create'),
            'edit' => EditUserConnect::route('/{record}/edit'),
        ];
    }
}
