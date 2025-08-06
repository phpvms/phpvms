<?php

namespace App\Filament\RelationManagers;

use App\Models\File;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->string(),

                TextInput::make('description')
                    ->string(),

                TextInput::make('url')
                    ->url()
                    ->requiredWithout('file'),

                FileUpload::make('file')
                    ->disk(config('filesystems.public_files'))
                    ->directory('files')
                    ->requiredWithout('url'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('download_count')->label('Downloads'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->icon('heroicon-o-plus-circle')->label('Add File')->mutateDataUsing(function (array $data): array {
                    if (!empty($data['url'])) {
                        $data['path'] = $data['url'];
                    } elseif (!empty($data['file'])) {
                        $data['path'] = $data['file'];
                        $data['disk'] = config('filesystems.public_files');
                    }

                    return $data;
                }),
            ])
            ->recordActions([
                Action::make('download')->icon('heroicon-m-link')->label('Link to file')
                    ->action(fn (File $record) => Storage::disk($record->disk)->download($record->path, Str::kebab($record->name)))
                    ->visible(fn (File $record): bool => $record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)),

                Action::make('view_file')->icon('heroicon-m-link')->label('Link to file')
                    ->url(fn (File $record): string => $record->path, shouldOpenInNewTab: true)
                    ->hidden(fn (File $record): bool => $record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)),

                DeleteAction::make()->before(function (File $record) {
                    if ($record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)) {
                        Storage::disk($record->disk)->delete($record->path);
                    }
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(function (File $record) {
                            if ($record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)) {
                                Storage::disk($record->disk)->delete($record->path);
                            }
                        });
                    }),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()->icon('heroicon-o-plus-circle')->label('Add File')->mutateDataUsing(function (array $data): array {
                    if (!empty($data['url'])) {
                        $data['path'] = $data['url'];
                    } elseif (!empty($data['file'])) {
                        $data['path'] = $data['file'];
                        $data['disk'] = config('filesystems.public_files');
                    }

                    return $data;
                }),
            ]);
    }
}
