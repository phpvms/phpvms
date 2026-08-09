<?php

declare(strict_types=1);

namespace App\Filament\Resources\AwardSnippets;

use App\Enums\NavigationGroup;
use App\Filament\Resources\AwardSnippets\Pages\ManageAwardSnippets;
use App\Filament\Resources\AwardSnippets\Schemas\AwardSnippetForm;
use App\Filament\Resources\AwardSnippets\Tables\AwardSnippetsTable;
use App\Models\AwardSnippet;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Override;
use UnitEnum;

/**
 * The snippet library: named, reusable fragments of an award criteria tree.
 * Sits next to Awards because it is only useful there.
 */
class AwardSnippetResource extends Resource
{
    protected static ?string $model = AwardSnippet::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Pilots;

    protected static ?int $navigationSort = 6;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected static ?string $recordTitleAttribute = 'label';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return AwardSnippetForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return AwardSnippetsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ManageAwardSnippets::route('/'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('filament.award_snippet');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('filament.award_snippets');
    }
}
