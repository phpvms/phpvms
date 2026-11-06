<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;

/**
 * Replaces the form's action footer with a save bar pinned to the bottom of the page: an unsaved-change
 * state line on the left, `[Delete] | [Cancel] [Save changes]` on the right.
 * The Delete button and its divider only render when the page supplies one
 * through `saveBarDeleteAction()`.
 *
 * Styled in the admin theme under `.save-bar` ("Save bar" block). The state
 * line is counted client-side (see `filament.components.save-bar-state`).
 * Keys listed by `autosaveKeys()` -- when the page uses [[AutosavesFields]] --
 * are excluded from the count, since they persist on their own.
 *
 * Pulls in [[FormActionIcons]] for the primary-last order; pages call
 * `reversePrimaryButtons()` in their own `getFormActions()`. Resource pages
 * only, like [[ReversePrimaryButtons]].
 *
 * Cancel does not leave the page: it resets the form to the saved record
 * (`getCancelFormAction()`), re-running the page's `mutateFormDataBeforeFill`,
 * clearing validation errors and re-taking the state line's snapshot.
 * Autosaved fields come back as saved, since they are already on the record.
 *
 * @see EditRecord::getFormContentComponent()
 */
trait HasSaveBar
{
    use FormActionIcons;

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName());
    }

    /**
     * The bar renders as the page's footer, after the relation managers, so it
     * can stick to the bottom of the whole page. Being outside the <form>, the
     * submit action points at it by id (`formId`).
     *
     * On a page with relation-manager tabs the bar is hidden while a manager's
     * tab is active, since the form is not on screen then (`activeRelationManager`
     * is empty or unknown while the Details tab is showing).
     */
    public function getFooter(): View
    {
        return view('filament.components.save-bar', [
            'visible' => !$this->isRelationManagerTabActive(),
        ]);
    }

    private function isRelationManagerTabActive(): bool
    {
        // Stacked (non-tab) relation managers also carry an activeRelationManager key.
        if (!method_exists($this, 'hasCombinedRelationManagerTabsWithContent') || !$this->hasCombinedRelationManagerTabsWithContent()) {
            return false;
        }

        if (blank($this->activeRelationManager)) {
            return false;
        }

        return array_key_exists($this->activeRelationManager, $this->getCachedRelationManagers());
    }

    /**
     * The page's delete action, shown at the left of the right-hand group with
     * a divider. Pages return their own configured `DeleteAction` (hooks,
     * modal text, redirects); null renders no Delete.
     */
    protected function saveBarDeleteAction(): ?Action
    {
        return null;
    }

    public function saveBar(Schema $schema): Schema
    {
        $actions = array_map(
            fn (mixed $action): mixed => $action instanceof Action && $action->canSubmitForm() ? $action->formId('form') : $action,
            $this->getFormActions(),
        );

        $delete = $this->saveBarDeleteAction();

        if ($delete instanceof Action) {
            array_unshift($actions, $delete->extraAttributes(['class' => 'save-bar-delete'], merge: true));
        }

        // Same Flex rules as ReversePrimaryButtons: End alignment on the Flex
        // (never on the Actions, which would row-reverse the pair), and
        // grow(false) on the slots. The state slot takes the free width.
        return $schema->model($this->getRecord())->components([
            Flex::make([
                ViewComponent::make('filament.components.save-bar-state')
                    ->viewData(['ignore' => method_exists($this, 'autosaveKeys') ? $this->autosaveKeys() : []]),
                Actions::make($actions)->grow(false),
            ])
                ->alignment(Alignment::Between)
                ->extraAttributes(['class' => 'save-bar'])
                ->grow(),
        ]);
    }

    /**
     * Resets the form to the saved record instead of navigating back to the
     * listing. Refills through `fillForm()` so `mutateFormDataBeforeFill` runs,
     * then tells the state line to re-snapshot once the refill has reached the
     * client.
     */
    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->color('gray')
            ->action(function (): void {
                $this->fillForm();
                $this->resetValidation();
                $this->dispatch('save-bar-saved');
            });
    }

    /** Tells the state line to re-take its snapshot. */
    protected function afterSave(): void
    {
        $this->dispatch('save-bar-saved');
    }
}
