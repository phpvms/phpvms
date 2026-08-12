<?php

declare(strict_types=1);

namespace App\Filament\Resources\AwardSnippets\Tables;

use App\Models\AwardSnippet;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AwardSnippetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('label')
                    ->label(__('common.name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('filament.award_snippet_name'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('common.description'))
                    ->limit(80)
                    ->placeholder('—'),

                TextColumn::make('rules_count')
                    ->label(__('filament.award_snippet_referenced_by'))
                    ->counts('rules')
                    ->badge(),
            ])
            ->recordActions([
                ActionGroup::make([
                    // The rule builder needs the room.
                    EditAction::make()
                        ->modalWidth(Width::FiveExtraLarge),

                    // The `award_rule_snippet` foreign key already refuses this,
                    // but only as a QueryException. Refuse first, naming what is
                    // in the way.
                    DeleteAction::make()
                        ->before(function (AwardSnippet $record, DeleteAction $action): void {
                            $awards = $record->referencingAwardNames();

                            if ($awards === []) {
                                return;
                            }

                            Notification::make()
                                ->title(__('filament.award_snippet_delete_blocked'))
                                ->body(implode(', ', $awards))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }),
                ]),
            ]);
    }
}
