<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Exceptions\AutosaveFailed;
use Closure;
use Filament\Notifications\Notification;

/**
 * Field-level autosave for Filament form pages: wire a field with
 * `->live()->afterStateUpdated($this->autosave())` and every change
 * validates the form, persists the page's autosave keys, and confirms with
 * a notification. Pure Filament/Livewire machinery — no JS hooks.
 *
 * The full form state (not just the changed field) is resolved on every
 * save: `getState()` runs validation and dehydration, which is what moves
 * freshly uploaded files onto their disk before the path is persisted.
 *
 * Implementors provide the key list and single-key storage; override
 * {@see afterAutosave()} for whole-state side effects (cache rebuilds,
 * derived settings) and {@see canAutosave()} for authorization.
 */
trait AutosavesFields
{
    /**
     * Re-entrancy guard: resolving form state can itself mutate field state
     * (file uploads do), which would re-fire afterStateUpdated mid-save.
     */
    protected bool $isAutosaving = false;

    /**
     * The form keys this page persists on every autosave.
     *
     * @return list<string>
     */
    abstract protected function autosaveKeys(): array;

    /**
     * Persist a single key. Runs once per key in {@see autosaveKeys()},
     * before {@see afterAutosave()}.
     *
     * Throw {@see AutosaveFailed} with a translated message to report a failure
     * the admin can act on; do not send a Notification by hand, or the success
     * toast contradicts it. Anything else propagates as a real error.
     */
    abstract protected function persistAutosavedField(string $key, mixed $value): void;

    /**
     * The keys this run should write: just the field that triggered it, when
     * that field is one of them.
     *
     * Writing every key on every autosave is only safe when there is one key.
     * With several -- Branding has logo, logo_dark and banner -- the untouched
     * fields hold null on a fresh page load, because a FileUpload is not
     * prefilled from the value it saved in an earlier session. Persisting those
     * nulls blanked the settings the admin had not touched, so uploading a logo
     * destroyed their banner and dark logo.
     *
     * Falls back to every key when the caller passes no component, which is how
     * a page-level autosave (rather than a single field's) still works.
     *
     * @return list<string>
     */
    protected function autosavingKeys(mixed $component = null): array
    {
        $keys = $this->autosaveKeys();

        if ($component === null || !method_exists($component, 'getName')) {
            return $keys;
        }

        $name = $component->getName();

        return in_array($name, $keys, true) ? [$name] : $keys;
    }

    /**
     * Gate writes; override where the page has its own authorization.
     */
    protected function canAutosave(): bool
    {
        return true;
    }

    /**
     * Whole-state hook after every key has been persisted.
     *
     * @param array<string, mixed> $state
     */
    protected function afterAutosave(array $state): void {}

    /**
     * Confirmation toast title; override for a field-specific message.
     */
    protected function autosaveNotificationTitle(): string
    {
        return __('common.saved');
    }

    /**
     * The afterStateUpdated closure fields wire onto. Filament injects the
     * updated component, which feeds the inline saved-tick indicator.
     */
    protected function autosave(): Closure
    {
        return function ($component = null): void {
            $this->runAutosave($component);
        };
    }

    /**
     * Run one autosave cycle. Public so schema classes shared between pages
     * (e.g. AirlineForm) can delegate to whichever page mounted them:
     * `$livewire->runAutosave($component)` from an afterStateUpdated hook.
     *
     * @param mixed $component the updated field, when known — drives the
     *                         browser-side saved indicator via the
     *                         `autosaved` event
     */
    public function runAutosave(mixed $component = null): void
    {
        if ($this->isAutosaving) {
            return;
        }

        abort_unless($this->canAutosave(), 403);

        $this->isAutosaving = true;

        try {
            $state = $this->form->getState();

            foreach ($this->autosavingKeys($component) as $key) {
                $this->persistAutosavedField($key, $state[$key] ?? null);
            }

            $this->afterAutosave($state);
        } catch (AutosaveFailed $autosaveFailed) {
            // The one report of the failure. Falling through would put the
            // success toast and the saved tick on top of it, which is what the
            // hand-rolled Notification at each call site used to do.
            Notification::make()
                ->title($autosaveFailed->getMessage())
                ->danger()
                ->send();

            return;
        } finally {
            $this->isAutosaving = false;
        }

        if ($component !== null && method_exists($component, 'getStatePath')) {
            // Transient check-mark inside the field (autosave-indicator.js).
            $this->dispatch('autosaved', statePath: $component->getStatePath());
        }

        Notification::make()
            ->title($this->autosaveNotificationTitle())
            ->success()
            ->send();
    }
}
