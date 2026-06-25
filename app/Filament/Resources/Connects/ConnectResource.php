<?php

namespace App\Filament\Resources\Connects;

use App\Filament\Resources\Connects\Pages\CreateConnect;
use App\Filament\Resources\Connects\Pages\EditConnect;
use App\Filament\Resources\Connects\Pages\ListConnects;
use App\Filament\Resources\Connects\Schemas\ConnectForm;
use App\Filament\Resources\Connects\Tables\ConnectsTable;
use App\Models\Connect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectResource extends Resource
{
    protected static ?string $model = Connect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ConnectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectsTable::configure($table);
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
            'index' => ListConnects::route('/'),
            'create' => CreateConnect::route('/create'),
            'edit' => EditConnect::route('/{record}/edit'),
        ];
    }
}
