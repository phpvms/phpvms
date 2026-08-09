<?php

declare(strict_types=1);

namespace App\Filament\Resources\Awards\Pages;

use App\Filament\Resources\Awards\AwardResource;
use App\Services\Awards\AwardExport;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Override;
use Throwable;

class ListAwards extends ListRecords
{
    protected static string $resource = AwardResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('filament.award_import'))
                ->icon(TablerIcon::Upload)
                ->color('gray')
                ->schema([
                    Textarea::make('document')
                        ->label(__('filament.award_import_document'))
                        ->rows(12)
                        ->required(),
                ])
                ->action($this->importAward(...)),

            CreateAction::make()
                ->icon(TablerIcon::CirclePlus),
        ];
    }

    /**
     * Imported awards arrive inactive and open for review.
     *
     * @param array<string, mixed> $data
     */
    protected function importAward(array $data): void
    {
        try {
            // AwardExport owns parsing and validation, so a malformed document
            // fails there rather than half-decoded here.
            $award = AwardExport::fromJson((string) $data['document']);
        } catch (Throwable $throwable) {
            Notification::make()
                ->title(__('filament.award_import_failed'))
                ->body($throwable->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('filament.award_imported', ['name' => $award->name]))
            ->success()
            ->send();

        $this->redirect(AwardResource::getUrl('edit', ['record' => $award]));
    }
}
