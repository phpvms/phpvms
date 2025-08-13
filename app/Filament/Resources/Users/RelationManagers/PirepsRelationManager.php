<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Pireps\Tables\PirepsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PirepsRelationManager extends RelationManager
{
    protected static string $relationship = 'pireps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

            ]);
    }

    public function table(Table $table): Table
    {
        return PirepsTable::configure($table);
    }

    public static function getModelLabel(): string
    {
        return trans_choice('common.pirep', 1);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('common.pirep', 2);
    }
}
