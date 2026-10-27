<?php

declare(strict_types=1);

namespace App\Filament\RelationManagers;

use App\Models\Page;
use App\Models\PageRevision;
use App\Services\PageService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;

/**
 * The undo history for a page.
 *
 * Read-only by design: a revision is a record of something that was live, so
 * editing one would make the history lie. The only thing you can do with one is
 * copy it back onto the draft.
 */
class PageRevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament.system_pages.revisions'))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('published_at')
                    ->label(__('filament.system_pages.published'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label(trans_choice('common.user', 1))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label(__('filament.system_pages.restore_revision'))
                    ->icon(Phosphor::ArrowCounterClockwiseLight)
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament.system_pages.restore_confirm'))
                    ->action(function (PageRevision $record): void {
                        /** @var Page $page */
                        $page = $this->getOwnerRecord();

                        app(PageService::class)->restoreRevision($page, $record);

                        Notification::make()
                            ->title(__('filament.system_pages.restored'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
