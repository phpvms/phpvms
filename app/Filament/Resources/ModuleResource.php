<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Modules';
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Edit Only (we are not using default create action)
                Forms\Components\Toggle::make('enabled')->offIcon('heroicon-m-x-circle')->offColor('danger')->onIcon('heroicon-m-check-circle')->onColor('success')->hiddenOn('create'),
                Forms\Components\Hidden::make('name')->hiddenOn('create')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('enabled')->color(fn ($state) => $state ? 'success' : 'danger')->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->before(function (array $data) {
                    if ($data['enabled']) {
                        app(ModuleService::class)->enableModule($data['name']);
                    }
                }),
                Tables\Actions\DeleteAction::make()->before(function (Module $record) {
                    try {
                        File::deleteDirectory(base_path() . '/modules/' . $record->name);
                    } catch (\Exception $e) {
                        Log::error('Folder Deleted Manually for Module : '.$record->name);
                    }
                }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(function (Module $record) {
                            try {
                                File::deleteDirectory(base_path() . '/modules/' . $record->name);
                            } catch (\Exception $e) {
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
            'index' => Pages\ManageModules::route('/'),
        ];
    }
}
