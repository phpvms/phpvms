<?php

declare(strict_types=1);

namespace App\Features\Ofp\Livewire;

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingBriefingActions;
use App\Http\Data\SimBriefBriefingData;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\SkylightSimBriefService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
 * @property-read list<Fill>           $actionFills
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
     * The fills addons added to the actions area. Dispatched once per request;
     * the view renders these and draws its separator only when it has any.
     *
     * @return list<Fill>
     */
    #[Computed]
    public function actionFills(): array
    {
        $briefing = $this->briefing;

        return Hooks::filter(new RenderingBriefingActions($briefing->flight, $briefing->bid, $briefing, $briefing->aircraft));
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

        Session::flash('success', 'The briefing was updated from SimBrief.');
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
        $user = Auth::user();

        return $user;
    }
}
