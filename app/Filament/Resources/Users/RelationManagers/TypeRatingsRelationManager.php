<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Features\Typeratings\Queries\TyperatingOptions;
use App\Filament\Forms\Components\InfiniteSelect;
use App\Models\Typerating;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class TypeRatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    /**
     * Its own table rather than the shared `TyperatingsTable`: that one belongs
     * to the standalone resource, so its edit and delete actions operate on the
     * `Typerating` record itself. On a user's page the only meaningful
     * operations are attaching and detaching the pivot row.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('type')
                    ->label(__('common.type')),

                TextColumn::make('name')
                    ->label(__('common.name')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(function (): InfiniteSelect {
                        $scope = fn (Builder $query) => $query->whereNotIn('id', $this->getRelationship()->select(new Typerating()->qualifyColumn('id')));

                        return InfiniteSelect::make('recordId')
                            ->hiddenLabel()
                            ->required()
                            ->getPageUsing(fn (string $search, int $page, int $perPage): array => new TyperatingOptions()->page($search, $page, $perPage, $scope))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => new TyperatingOptions()->label($value, $scope));
                    }),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    protected static function getModelLabel(): string
    {
        return __('common.typerating');
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.typerating'))
            ->plural()
            ->toString();
    }
}
