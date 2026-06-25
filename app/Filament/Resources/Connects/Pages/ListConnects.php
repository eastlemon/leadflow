<?php

namespace App\Filament\Resources\Connects\Pages;

use App\Filament\Resources\Connects\ConnectResource;
use App\Models\Connect;
use App\Services\FileUploadService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListConnects extends ListRecords
{
    protected static string $resource = ConnectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('uploadLeads')
                ->label('Загрузить файл')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('connect_id')
                        ->label('Банк')
                        ->options(fn () => Connect::query()->pluck('display_name', 'id')->all())
                        ->required(),
                    FileUpload::make('files')
                        ->label('Файлы (xlsx, xls, ods, csv)')
                        ->multiple()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/vnd.oasis.opendocument.spreadsheet',
                            'text/csv',
                            'text/plain',
                        ])
                        ->maxFiles(10)
                        ->maxSize(1024) // KB; Yii limit was 1 MB
                        ->disk('local')
                        ->directory('live-uploads')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = Auth::user();
                    if (! $user) {
                        Notification::make()->danger()->title('Не авторизован')->send();
                        return;
                    }

                    $service = app(FileUploadService::class);
                    $count = 0;
                    foreach ($data['files'] as $uploadedPath) {
                        // FileUpload with disk=local stores at live-uploads/{path}.
                        $absolute = storage_path('app/private/'.$uploadedPath);
                        $uploaded = new \Illuminate\Http\UploadedFile(
                            $absolute,
                            basename($uploadedPath),
                            null,
                            null,
                            true,
                        );
                        $service->store($user, $uploaded, (int) $data['connect_id']);
                        @unlink($absolute);
                        $count++;
                    }

                    Notification::make()
                        ->success()
                        ->title("Загружено файлов: {$count}")
                        ->send();
                }),
        ];
    }
}
