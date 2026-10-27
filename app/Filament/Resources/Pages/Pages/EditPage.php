<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Enums\PageStatus;
use App\Enums\PageType;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\RelationManagers\PageRevisionsRelationManager;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Services\PageService;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Override;

class EditPage extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = PageResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    /**
     * A restore writes the draft from the revisions table, which is a separate
     * component. Reload the field so a later save does not write the old draft
     * back over the restored one.
     */
    #[On(PageRevisionsRelationManager::RESTORED_EVENT)]
    public function refreshDraftBody(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['draft_body']);
    }

    /**
     * Losing a race for a path surfaces as the field error, not a 500.
     *
     * @param array<string, mixed> $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw app(PageService::class)->toPathValidationException($uniqueConstraintViolationException, 'data.path');
        }
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            $this->publishAction(),
            $this->unpublishAction(),
            DeleteAction::make()->icon(Phosphor::TrashLight),
        ];
    }

    /**
     * Saving edits the draft; this is the separate, deliberate act of making it
     * the version visitors see.
     */
    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label(__('filament.system_pages.publish'))
            ->icon(Phosphor::RocketLaunchLight)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('filament.system_pages.publish_confirm'))
            ->visible(fn (Page $record): bool => $record->type === PageType::PAGE)
            ->action(function (Page $record): void {
                try {
                    app(PageService::class)->publish($record, auth()->user());
                } catch (ValidationException $validationException) {
                    Notification::make()
                        ->title(collect($validationException->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('filament.system_pages.published'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Opens the draft the way a visitor would see the page, in a new tab.
     */
    private function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('filament.system_pages.preview'))
            ->icon(Phosphor::EyeLight)
            ->color('gray')
            ->url(fn (Page $record): string => route('frontend.pages.preview', ['page' => $record->getKey()]))
            ->openUrlInNewTab()
            ->visible(fn (Page $record): bool => $record->type === PageType::PAGE);
    }

    private function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label(__('filament.system_pages.unpublish'))
            ->icon(Phosphor::EyeSlashLight)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('filament.system_pages.unpublish_confirm'))
            ->visible(fn (Page $record): bool => $record->status === PageStatus::PUBLISHED)
            ->action(function (Page $record): void {
                app(PageService::class)->unpublish($record);

                Notification::make()
                    ->title(__('filament.system_pages.unpublished'))
                    ->warning()
                    ->send();
            });
    }
}
