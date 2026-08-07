<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

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
     */
    abstract protected function persistAutosavedField(string $key, mixed $value): void;

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

            foreach ($this->autosaveKeys() as $key) {
                $this->persistAutosavedField($key, $state[$key] ?? null);
            }

            $this->afterAutosave($state);
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
