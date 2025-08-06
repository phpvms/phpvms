<?php

namespace App\Filament\Resources;

use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\AirlineResource\Pages\CreateAirline;
use App\Filament\Resources\AirlineResource\Pages\EditAirline;
use App\Filament\Resources\AirlineResource\Pages\ListAirlines;
use App\Models\Airline;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use League\ISO3166\ISO3166;

class AirlineResource extends Resource
{
    protected static ?string $model = Airline::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Config';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Airlines';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Airline Informations')->schema([
                    TextInput::make('icao')->label('ICAO (3LD)')
                        ->required()
                        ->string()
                        ->length(3),

                    TextInput::make('iata')
                        ->label('IATA (2LD)')
                        ->string()
                        ->length(2),

                    TextInput::make('callsign')
                        ->label('Radio Callsign')
                        ->string(),

                    TextInput::make('name')
                        ->required()
                        ->string(),

                    TextInput::make('logo')
                        ->label('Logo URL')
                        ->string(),

                    Select::make('country')
                        ->options(collect((new ISO3166())->all())->mapWithKeys(fn ($item, $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                        ->searchable()
                        ->native(false),

                    Toggle::make('active')
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
                TextColumn::make('Code')
                    ->formatStateUsing(function (Airline $record) {
                        $html = '';
                        if (filled($record->country)) {
                            $html .= '<span class="flag-icon flag-icon-'.$record->country.'"></span> &nbsp;';
                        }
                        if (filled($record->iata)) {
                            $html .= $record->iata.'/';
                        }

                        return $html.$record->icao;
                    })
                    ->sortable()
                    ->searchable()
                    ->html(),

                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('active')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(function (Airline $record) {
                    $record->files()->each(function (File $file) {
                        app(FileService::class)->removeFile($file);
                    });
                }),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()->before(function (Collection $records) {
                        $records->each(fn (Airline $record) => $record->files()->each(function (File $file) {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
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
            'index'  => ListAirlines::route('/'),
            'create' => CreateAirline::route('/create'),
            'edit'   => EditAirline::route('/{record}/edit'),
        ];
    }
}
