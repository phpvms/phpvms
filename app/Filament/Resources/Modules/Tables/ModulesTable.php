<?php

namespace App\Filament\Resources\Modules\Tables;

use App\Models\Module;
use App\Services\ModuleService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('enabled')
                    ->label(__('common.enabled'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): Heroicon => $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->before(function (array $data) {
                        app(ModuleService::class)
                            ->updateModule($data['id'], $data['enabled']);
                    }),
                DeleteAction::make()
                    ->before(function (Module $record) {
                        try {
                            File::deleteDirectory(base_path().'/modules/'.$record->name);
                        } catch (\Exception $e) {
                            Log::error('Folder Deleted Manually for Module : '.$record->name);
                        }
                    }),
            ])
            ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make()
                            ->before(function (Collection $records) {
                                $records->each(function (Module $record) {
                                    try {
                                        File::deleteDirectory(base_path().'/modules/'.$record->name);
                                    } catch (\Exception $e) {
                                        Log::error('Folder Deleted Manually for Module : '.$record->name);
                                    }
                                });
                            }),
                    ]),
            ]);
    }
}
