<?php

namespace App\Filament\Resources\News;

use App\Enums\NavigationGroup;
use App\Filament\Resources\News\Pages\ListNews;
use App\Filament\Resources\News\Schemas\NewsForm;
use App\Filament\Resources\News\Tables\NewsTable;
use App\Models\News;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Override;
use UnitEnum;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $recordTitleAttribute = 'subject';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return NewsForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return NewsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListNews::route('/'),
        ];
    }

    #[Override]
    public static function getGloballySearchableAttributes(): array
    {
        return ['subject', 'body'];
    }

    /**
     * @param News $record
     */
    #[Override]
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->subject;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('common.news');
    }
}
