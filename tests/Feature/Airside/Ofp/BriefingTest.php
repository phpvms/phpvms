<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Ofp\Livewire\BriefingActions;
use App\Models\Pirep;
use App\Models\SimBrief;
use App\Models\User;
use App\Services\SimBriefService;
use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

/**
 * 7.6: the OFP briefing page and its actions.
 */
beforeEach(function (): void {
    $this->fixture = airsideOfpFixture();
    $this->briefing = airsideBriefing($this->fixture);
    $this->withoutVite();
});

it('renders the briefing page with its sections', function (): void {
    $flight = $this->fixture['flight'];

    $this->actingAs($this->fixture['user'])
        ->get(route('frontend.ofp.briefing', $this->briefing->id))
        ->assertOk()
        ->assertSee($flight->ident)
        ->assertSee($this->fixture['aircraft']->registration)
        ->assertSee('data-airside-island="briefing-globe"', false);
});

it('hides cancel once a PIREP is attached and keeps the edit link off a briefing with no static id', function (): void {
    $pirep = Pirep::factory()->create(['user_id' => $this->fixture['user']->id]);
    $this->briefing->update(['pirep_id' => $pirep->id, 'static_id' => null]);

    $this->actingAs($this->fixture['user'])
        ->get(route('frontend.ofp.briefing', $this->briefing->id))
        ->assertOk()
        ->assertDontSee('Cancel unused briefing')
        ->assertDontSee('Edit OFP');
});

it('is a 404 for another pilot', function (): void {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('frontend.ofp.briefing', $this->briefing->id))
        ->assertNotFound();
});

it('renders the addon entries of the briefing actions slot with the context', function (): void {
    file_put_contents(
        sys_get_temp_dir().'/ofp-slot-'.getmypid().'.blade.php',
        '<span id="slot-probe">{{ $flight->summary->callsign }}|{{ $aircraft->registration }}|{{ $briefing->id }}</span>',
    );
    View::addNamespace('ofpslot', sys_get_temp_dir());
    Skylight::slots()->register(['slot' => 'flight.briefing.actions', 'view' => 'ofpslot::ofp-slot-'.getmypid()]);

    $this->actingAs($this->fixture['user'])
        ->get(route('frontend.ofp.briefing', $this->briefing->id))
        ->assertOk()
        ->assertSee('<span id="slot-probe">'.$this->fixture['flight']->ident.'|'.$this->fixture['aircraft']->registration.'|'.$this->briefing->id.'</span>', false);

    @unlink(sys_get_temp_dir().'/ofp-slot-'.getmypid().'.blade.php');
});

it('regenerates: drops the briefing and goes back to planning for the same aircraft', function (): void {
    Livewire::actingAs($this->fixture['user'])
        ->test(BriefingActions::class, ['briefingId' => (string) $this->briefing->id])
        ->call('regenerate')
        ->assertRedirect(route('frontend.ofp.planning', [
            'flight_id'   => $this->fixture['flight']->id,
            'aircraft_id' => $this->fixture['aircraft']->id,
        ]));

    expect(SimBrief::query()->find($this->briefing->id))->toBeNull();
});

it('cancels: drops the unused briefing and goes to the flight', function (): void {
    Livewire::actingAs($this->fixture['user'])
        ->test(BriefingActions::class, ['briefingId' => (string) $this->briefing->id])
        ->call('cancel')
        ->assertRedirect(route('frontend.flights.show', $this->fixture['flight']->id));

    expect(SimBrief::query()->find($this->briefing->id))->toBeNull();
});

it('refuses to cancel or regenerate a briefing that is attached to a PIREP', function (): void {
    $pirep = Pirep::factory()->create(['user_id' => $this->fixture['user']->id]);
    $this->briefing->update(['pirep_id' => $pirep->id]);

    Livewire::actingAs($this->fixture['user'])
        ->test(BriefingActions::class, ['briefingId' => (string) $this->briefing->id])
        ->call('cancel')
        ->assertNoRedirect()
        ->assertSet('failure', 'A briefing attached to a PIREP cannot be cancelled.')
        ->call('regenerate')
        ->assertNoRedirect()
        ->assertSet('failure', 'A briefing attached to a PIREP cannot be regenerated.');

    expect(SimBrief::query()->find($this->briefing->id))->not->toBeNull();
});

it('does not let another pilot act on a briefing', function (): void {
    $other = User::factory()->create();

    Livewire::actingAs($other)
        ->test(BriefingActions::class, ['briefingId' => (string) $this->briefing->id])
        ->assertNotFound();
});

it('keeps the editor open with a message while SimBrief has not published the edit', function (): void {
    $this->mock(SimBriefService::class)->shouldReceive('downloadOfp')->once()->andReturnNull();

    Livewire::actingAs($this->fixture['user'])
        ->test(BriefingActions::class, ['briefingId' => (string) $this->briefing->id])
        ->call('syncEdited')
        ->assertReturned(false)
        ->assertNoRedirect()
        ->assertSet('failure', 'The updated OFP could not be retrieved yet. Keep editing or try again shortly.')
        ->assertSee('The updated OFP could not be retrieved yet.');
});

it('reloads the briefing once the edited OFP is ready', function (): void {
    $this->mock(SimBriefService::class)->shouldReceive('downloadOfp')->once()->andReturn($this->briefing);

    Livewire::actingAs($this->fixture['user'])
        ->test(BriefingActions::class, ['briefingId' => (string) $this->briefing->id])
        ->call('syncEdited')
        ->assertReturned(true)
        ->assertRedirect(route('frontend.ofp.briefing', $this->briefing->id));
});
