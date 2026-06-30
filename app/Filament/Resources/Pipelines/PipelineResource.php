<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pipelines;

use App\Filament\Resources\Pipelines\Pages;
use App\Models\Pipeline;
use App\Filament\Resources\Pipelines\RelationManagers;
use App\Pipelines\ProviderSchemas;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PipelineResource extends Resource
{
    protected static ?string $model = Pipeline::class;

    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Пайплайн';

    protected static ?string $pluralModelLabel = 'Пайплайны';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->label('Пользователь'),

                Forms\Components\Select::make('provider')
                    ->options(ProviderSchemas::types())
                    ->required()
                    ->live()
                    ->label('Провайдер'),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Название'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Активен'),

                // Dynamic provider_config fields based on selected provider.
                Schemas\Components\Section::make('Настройки провайдера')
                    ->visible(fn (Schemas\Components\Utilities\Get $get) => filled($get('provider')))
                    ->schema(function (Schemas\Components\Utilities\Get $get) {
                        $provider = $get('provider');
                        if (! $provider) {
                            return [];
                        }

                        $schema = ProviderSchemas::get($provider);
                        if ($schema === []) {
                            return [
                                Forms\Components\Placeholder::make('no_config')
                                    ->content('У этого провайдера нет настроек.'),
                            ];
                        }

                        return collect($schema)->map(function (array $field, string $key) {
                            return match ($field['type'] ?? 'text') {
                                'url'      => Forms\Components\TextInput::make("provider_config.{$key}")
                                    ->label($field['label'])
                                    ->url()
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                                'email'    => Forms\Components\TextInput::make("provider_config.{$key}")
                                    ->label($field['label'])
                                    ->email()
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                                'password' => Forms\Components\TextInput::make("provider_config.{$key}")
                                    ->label($field['label'])
                                    ->password()
                                    ->revealable()
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                                'select'   => Forms\Components\Select::make("provider_config.{$key}")
                                    ->label($field['label'])
                                    ->options(['1' => 'Да', '0' => 'Нет', 'yes' => 'Да', 'no' => 'Нет'])
                                    ->default($field['default'] ?? null)
                                    ->hint($field['hint'] ?? null),
                                default    => Forms\Components\TextInput::make("provider_config.{$key}")
                                    ->label($field['label'])
                                    ->required($field['required'] ?? false)
                                    ->hint($field['hint'] ?? null),
                            };
                        })->values()->all();
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Название'),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ProviderSchemas::types()[$state] ?? $state)
                    ->label('Провайдер'),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label('Пользователь'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Активен'),
                Tables\Columns\TextColumn::make('receivers_count')
                    ->counts('receivers')
                    ->label('Ресиверы'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Создан'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')
                    ->options(ProviderSchemas::types())
                    ->label('Провайдер'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReceiversRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPipelines::route('/'),
            'create' => Pages\CreatePipeline::route('/create'),
            'edit'   => Pages\EditPipeline::route('/{record}/edit'),
        ];
    }
}