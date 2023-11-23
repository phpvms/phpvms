<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\AirlineResource\Pages;
use App\Models\Airline;
use App\Models\File;
use App\Services\FileService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use League\ISO3166\ISO3166;

class AirlineResource extends Resource
{
    protected static ?string $model = Airline::class;

    protected static ?string $navigationGroup = 'Config';
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Airlines';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Airline Informations')->schema([
                    Forms\Components\TextInput::make('icao')->label('ICAO (3LD)')
                        ->required()
                        ->string()
                        ->length(3),

                    Forms\Components\TextInput::make('iata')
                        ->label('IATA (2LD)')
                        ->string()
                        ->length(2),

                    Forms\Components\TextInput::make('callsign')
                        ->label('Radio Callsign')
                        ->string(),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->string(),

                    Forms\Components\TextInput::make('logo')
                        ->label('Logo URL')
                        ->string(),

                    Forms\Components\Select::make('country')
                    ->options(collect((new ISO3166())->all())->mapWithKeys(fn ($item, $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                    ->searchable()
                    ->native(false),

                    Forms\Components\Toggle::make('active')
                        ->inline()
                        ->onColor('success')
                        ->onIcon('heroicon-m-check-circle')
                        ->offColor('danger')
                        ->offIcon('heroicon-m-x-circle'),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('Code')
                    ->formatStateUsing(function (Airline $record) {
                        $html = '';
                        if (filled($record->country)) {
                            $html .= '<span class="flag-icon flag-icon-'.$record->country.'"></span> &nbsp;';
                        }
                        if (filled($record->iata)) {
                            $html .= $record->iata.'/';
                        }
                        return $html.$record->icao;
                    })->html(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\IconColumn::make('active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()->before(function (Airline $record) {
                    $record->files()->each(function (File $file) {
                        app(FileService::class)->removeFile($file);
                    });
                }),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(fn (Airline $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Airline'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAirlines::route('/'),
            'create' => Pages\CreateAirline::route('/create'),
            'edit'   => Pages\EditAirline::route('/{record}/edit'),
        ];
    }
}
