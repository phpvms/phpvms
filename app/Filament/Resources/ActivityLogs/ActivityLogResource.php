<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ActivityLogs\Infolists\ActivityLogInfolist;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Override;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::NewspaperLight;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ActivityLogInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view'  => ViewActivityLog::route('/{record}'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('activities.activity');
    }
}
