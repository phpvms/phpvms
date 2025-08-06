<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages\ManageModules;
use App\Models\Module;
use App\Services\ModuleService;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Modules';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Edit Only (we are not using default create action)
                Toggle::make('enabled')
                    ->offIcon('heroicon-m-x-circle')
                    ->offColor('danger')
                    ->onIcon('heroicon-m-check-circle')
                    ->onColor('success')
                    ->hiddenOn('create'),

                Hidden::make('id')
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('enabled')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->before(function (array $data) {
                    app(ModuleService::class)->updateModule($data['id'], $data['enabled']);
                }),
                DeleteAction::make()->before(function (Module $record) {
                    try {
                        File::deleteDirectory(base_path().'/modules/'.$record->name);
                    } catch (Exception $e) {
                        Log::error('Folder Deleted Manually for Module : '.$record->name);
                    }
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(function (Module $record) {
                            try {
                                File::deleteDirectory(base_path().'/modules/'.$record->name);
                            } catch (Exception $e) {
                                Log::error('Folder Deleted Manually for Module : '.$record->name);
                            }
                        });
                    }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageModules::route('/'),
        ];
    }
}
