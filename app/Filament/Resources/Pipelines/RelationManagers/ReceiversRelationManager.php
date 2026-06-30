<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pipelines\RelationManagers;

use App\Adapters\AdapterRegistry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ReceiversRelationManager extends RelationManager
{
    protected static string $relationship = 'receivers';

    protected static ?string $title = 'Ресиверы';

    protected static ?string $modelLabel = 'Ресивер';

    protected static ?string $pluralModelLabel = 'Ресиверы';

    public function form(Schema $schema): Schema
    {
        $bankOptions = collect(app(AdapterRegistry::class)->available())
            ->mapWithKeys(fn (string $class, string $name) => [
                $name => app($class)::displayName() ?? $name,
            ])
            ->all();

        return $schema
            ->components([
                Forms\Components\Select::make('system_name')
                    ->options($bankOptions)
                    ->required()
                    ->live()
                    ->label('Банк'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Активен'),

                // Dynamic tune fields based on selected bank.
                Schemas\Components\Section::make('Настройки банка')
                    ->visible(fn (Schemas\Components\Utilities\Get $get) => filled($get('system_name')))
                    ->schema(function (Schemas\Components\Utilities\Get $get) {
                        $systemName = $get('system_name');
                        if (! $systemName || ! app(AdapterRegistry::class)->has($systemName)) {
                            return [];
                        }

                        $class = app(AdapterRegistry::class)->available()[$systemName] ?? null;
                        if (! $class || ! method_exists($class, 'configSchema')) {
                            return [];
                        }

                        /** @var array<string, array> $fields */
                        $fields = $class::configSchema();

                        return collect($fields)->map(function (array $field, string $key) {
                            return match ($field['type'] ?? 'text') {
                                'url'      => Forms\Components\TextInput::make("tune.{$key}")
                                    ->label($field['label'])
                                    ->url()
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                                'email'    => Forms\Components\TextInput::make("tune.{$key}")
                                    ->label($field['label'])
                                    ->email()
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                                'password' => Forms\Components\TextInput::make("tune.{$key}")
                                    ->label($field['label'])
                                    ->password()
                                    ->revealable()
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                                'number'   => Forms\Components\TextInput::make("tune.{$key}")
                                    ->label($field['label'])
                                    ->numeric()
                                    ->hint($field['hint'] ?? null),
                                'select'   => Forms\Components\Select::make("tune.{$key}")
                                    ->label($field['label'])
                                    ->options(['1' => 'Да', '0' => 'Нет', 'yes' => 'Да', 'no' => 'Нет'])
                                    ->default($field['default'] ?? null)
                                    ->hint($field['hint'] ?? null),
                                default    => Forms\Components\TextInput::make("tune.{$key}")
                                    ->label($field['label'])
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                            };
                        })->values()->all();
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        $bankNames = collect(app(AdapterRegistry::class)->available())
            ->mapWithKeys(fn (string $class, string $name) => [
                $name => app($class)::displayName() ?? $name,
            ])
            ->all();

        return $table
            ->recordTitleAttribute('system_name')
            ->columns([
                Tables\Columns\TextColumn::make('system_name')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $bankNames[$state] ?? $state)
                    ->label('Банк'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Активен'),
                Tables\Columns\TextColumn::make('label')
                    ->label('Название'),
                Tables\Columns\TextColumn::make('tune')
                    ->limit(50)
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : '')
                    ->label('Настройки'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}