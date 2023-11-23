<?php

namespace App\Filament\Resources\AirportResource\RelationManagers;

use App\Models\File;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class FilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->string(),
                Forms\Components\TextInput::make('description')->string(),
                Forms\Components\TextInput::make('url')->url()->requiredWithout('file'),
                Forms\Components\FileUpload::make('file')->disk(config('filesystems.public_files'))->directory('files')->requiredWithout('url'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('download_count')->label('Downloads'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->icon('heroicon-o-plus-circle')->label('Add File')->mutateFormDataUsing(function (array $data): array {
                    if (!empty($data['url'])) {
                        $data['path'] = $data['url'];
                    } elseif (!empty($data['file'])) {
                        $data['path'] = $data['file'];
                        $data['disk'] = config('filesystems.public_files');
                    }

                    return $data;
                }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')->icon('heroicon-m-link')->label('Link to file')
                    ->action(fn (File $record) => Storage::disk($record->disk)->download($record->path, Str::kebab($record->name)))
                    ->visible(fn (File $record): bool => $record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)),

                Tables\Actions\Action::make('view_file')->icon('heroicon-m-link')->label('Link to file')
                    ->url(fn (File $record): string => $record->path, shouldOpenInNewTab: true)
                    ->hidden(fn (File $record): bool => $record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)),

                Tables\Actions\DeleteAction::make()->before(function (File $record) {
                    if ($record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)) {
                        Storage::disk($record->disk)->delete($record->path);
                    }
                }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(function (File $record) {
                            if ($record->disk && !str_contains($record->path, 'http') && Storage::disk($record->disk)->exists($record->path)) {
                                Storage::disk($record->disk)->delete($record->path);
                            }
                        });
                    }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->icon('heroicon-o-plus-circle')->label('Add File')->mutateFormDataUsing(function (array $data): array {
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
