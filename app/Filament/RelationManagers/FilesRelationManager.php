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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('common.name'))
                    ->required()
                    ->string(),

                TextInput::make('description')
                    ->label(__('common.description'))
                    ->string(),

                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->requiredWithout('file'),

                FileUpload::make('file')
                    ->label(__('common.file'))
                    ->disk(config('filesystems.public_files'))
                    ->directory('files')
                    ->requiredWithout('url'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name')),

                TextColumn::make('download_count')
                    ->label(trans_choice('common.download', 1)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->mutateDataUsing(function (array $data): array {
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
                Action::make('download')
                    ->icon(Heroicon::Link)
                    ->label(__('common.link_to_file'))
                    ->action(fn (File $record) => Storage::disk($record->disk)->download($record->path, Str::kebab($record->name)))
                    ->visible(fn (File $record): bool => $record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)),

                Action::make('view_file')
                    ->icon(Heroicon::Link)
                    ->label(__('common.link_to_file'))
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
                CreateAction::make()->icon(Heroicon::OutlinedPlusCircle)
                    ->mutateDataUsing(function (array $data): array {
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

    public static function getModelLabel(): string
    {
        return __('common.file');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.file'))
            ->plural()
            ->toString();
    }
}
