<?php

declare(strict_types=1);

namespace App\Filament\Resources\NavigationItems\Tables;

use App\Enums\NavigationTarget;
use App\Models\NavigationItem;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NavigationItemsTable
{
    public static function configure(Table $table): Table
    {
        // Built once, on first use, by the query modifier or the name column. Local to
        // this configure() call so nothing is shared between requests.
        $depths = null;

        return $table
            // The menu has one order and it is this one, so drag-to-reorder
            // writes straight to the column the front end reads. Rows are laid
            // out as a tree (see treeOrder()), so a drag renumbers `sort` in tree
            // order: siblings keep their relative order, and a child stays under
            // its parent. Dragging a row to another parent is done in the form.
            ->reorderable('sort')
            ->modifyQueryUsing(function (Builder $query) use (&$depths): Builder {
                $depths ??= self::treeOrder();

                if ($depths === []) {
                    return $query;
                }

                $cases = '';
                $position = 0;

                foreach (array_keys($depths) as $id) {
                    $cases .= ' WHEN '.(int) $id.' THEN '.$position++;
                }

                return $query->orderByRaw('CASE navigation_items.id'.$cases.' ELSE '.$position.' END');
            })
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.navigation_items.name'))
                    ->formatStateUsing(function (string $state, NavigationItem $record) use (&$depths): string {
                        $depths ??= self::treeOrder();

                        return str_repeat('— ', $depths[$record->id] ?? 0).$state;
                    })
                    ->searchable(),

                TextColumn::make('label')
                    ->label(__('filament.navigation_items.label'))
                    ->searchable(),

                TextColumn::make('parent.name')
                    ->label(__('filament.navigation_items.parent'))
                    ->placeholder(__('filament.navigation_items.parent_none')),

                TextColumn::make('target_type')
                    ->label(__('filament.navigation_items.target_type'))
                    ->badge(),

                TextColumn::make('target_value')
                    ->label(__('common.link'))
                    ->limit(40)
                    ->state(fn (NavigationItem $record): ?string => $record->resolveUrl()),

                ToggleColumn::make('visible')
                    ->label(__('filament.navigation_items.visible')),

                IconColumn::make('public')
                    ->label(__('common.public'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('target_type')
                    ->label(__('filament.navigation_items.target_type'))
                    ->options(NavigationTarget::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->hidden(fn (NavigationItem $record): bool => $record->children()->exists()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Every item depth-first: a parent, then its children by `sort`, and so on.
     * Returned as id => depth in display order. An item whose parent is missing
     * is shown at the top level, and a parent loop cannot recurse forever.
     *
     * @return array<int, int>
     */
    public static function treeOrder(): array
    {
        $byParent = NavigationItem::query()
            ->ordered()
            ->get(['id', 'parent_id'])
            ->groupBy(fn (NavigationItem $item): int => (int) $item->parent_id);

        $known = $byParent->flatten(1)->pluck('id')->all();
        $order = [];

        $walk = function (int $parentId, int $depth) use (&$walk, &$order, $byParent): void {
            foreach ($byParent->get($parentId, []) as $item) {
                if (isset($order[$item->id])) {
                    continue;
                }

                $order[$item->id] = $depth;
                $walk((int) $item->id, $depth + 1);
            }
        };

        $walk(0, 0);

        // Orphans: parent deleted or part of a loop.
        foreach ($byParent->flatten(1) as $item) {
            if (!isset($order[$item->id]) && !in_array((int) $item->parent_id, $known, true)) {
                $order[$item->id] = 0;
                $walk((int) $item->id, 1);
            }
        }

        return $order;
    }
}
