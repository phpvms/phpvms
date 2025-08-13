<?php

namespace App\Filament\Resources\Subfleets\RelationManagers;

use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Tables\AircraftTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AircraftRelationManager extends RelationManager
{
    protected static string $relationship = 'aircraft';

    protected static ?string $relatedResource = AircraftResource::class;

    public function table(Table $table): Table
    {
        return AircraftTable::configure($table)
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlusCircle),
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('common.aircraft');
    }
}
