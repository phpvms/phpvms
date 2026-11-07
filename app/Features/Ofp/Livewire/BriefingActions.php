<?php

declare(strict_types=1);

namespace App\Features\Ofp\Livewire;

use App\Http\Data\SimBriefBriefingData;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\SkylightSimBriefService;
use App\Support\Extensions\Facades\Extensions;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The briefing page's action card: addon slot, Edit OFP, Regenerate, Cancel,
 * Downloads and the network prefile links.
 *
 * Calls `SkylightSimBriefService` directly; the service scopes every lookup to
 * the signed-in pilot, so another pilot's briefing id is a 404.
 *
 * @property-read SimBriefBriefingData $briefing
 * @property-read bool                  $hasSlotActions
 */
class BriefingActions extends Component
{
    #[Locked]
    public string $briefingId = '';

    public ?string $failure = null;

    public function mount(string $briefingId): void
    {
        $this->briefingId = $briefingId;
    }

    #[Computed]
    public function briefing(): SimBriefBriefingData
    {
        $user = $this->pilot();
        $briefing = app(SkylightSimBriefService::class)->briefingFor($user, $this->briefingId);

        return SimBriefBriefingData::fromModel(
            $briefing,
            $user->bids()->where('flight_id', $briefing->flight_id)->first(),
        );
    }

    /**
     * Whether any addon registered a Blade entry for the actions slot, so the
     * card can draw its separator.
     */
    #[Computed]
    public function hasSlotActions(): bool
    {
        return Extensions::slots()->bladeEntriesFor('flight.briefing.actions') !== [];
    }

    /**
     * Drop the briefing and plan the same flight and aircraft again.
     */
    public function regenerate(): void
    {
        $this->failure = null;

        try {
            $briefing = app(SkylightSimBriefService::class)->regenerate($this->pilot(), $this->briefingId);
        } catch (ValidationException $validationException) {
            $this->failure = $this->firstError($validationException);

            return;
        }

        $this->redirect(route('frontend.ofp.planning', [
            'flight_id'   => $briefing->flight_id,
            'aircraft_id' => $briefing->aircraft_id,
        ]));
    }

    /**
     * Drop an unused briefing and go back to the flight.
     */
    public function cancel(): void
    {
        $this->failure = null;

        try {
            $briefing = app(SkylightSimBriefService::class)->cancel($this->pilot(), $this->briefingId);
        } catch (ValidationException $validationException) {
            $this->failure = $this->firstError($validationException);

            return;
        }

        $this->redirect(route('frontend.flights.show', $briefing->flight_id));
    }

    /**
     * Pull the OFP the pilot edited in SimBrief. Returns whether it was ready,
     * so the editor can close.
     */
    public function syncEdited(): bool
    {
        $this->failure = null;

        try {
            $briefing = app(SkylightSimBriefService::class)->syncEdited($this->pilot(), $this->briefingId);
        } catch (ValidationException $validationException) {
            $this->failure = $this->firstError($validationException);

            return false;
        }

        if (!$briefing instanceof SimBrief) {
            $this->failure = 'The updated OFP could not be retrieved yet. Keep editing or try again shortly.';

            return false;
        }

        session()->flash('success', 'The briefing was updated from SimBrief.');
        $this->redirect(route('frontend.ofp.briefing', $briefing->id));

        return true;
    }

    public function render(): View
    {
        return view('livewire.ofp.briefing-actions');
    }

    private function firstError(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->first() ?? 'The briefing could not be updated.';
    }

    private function pilot(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
