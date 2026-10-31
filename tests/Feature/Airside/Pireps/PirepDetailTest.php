<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Enums\AcarsType;
use App\Models\Acars;
use App\Models\Pirep;
use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\Facades\View;

/**
 * 7.7: the PIREP detail page, its conditional panels and the
 * `pireps.detail.main` outlet.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('renders the summary, the stat strip and the flight details', function (): void {
    $pirep = Pirep::factory()->create([
        'user_id'      => $this->pilot->id,
        'flight_time'  => 90,
        'landing_rate' => -212.4,
        'score'        => 91,
        'submitted_at' => '2026-03-14 10:30:00',
    ]);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.show', ['id' => $pirep->id]))
        ->assertOk()
        ->assertSee($pirep->ident)
        ->assertSee($pirep->dpt_airport_id)
        ->assertSee($pirep->arr_airport_id)
        ->assertSee($this->pilot->name)
        ->assertSee('Filed 14 Mar 2026, 10:30')
        ->assertSee('1h 30m')
        ->assertSee('-212 fpm');
});

it('shows the notes panel when the PIREP has notes', function (): void {
    $pirep = Pirep::factory()->create(['user_id' => $this->pilot->id, 'notes' => 'Smooth flight, light chop.']);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.show', ['id' => $pirep->id]))
        ->assertOk()
        ->assertSee('NOTES')
        ->assertSee('Smooth flight, light chop.');
});

it('shows the ACARS log panel when the PIREP has log entries', function (): void {
    $pirep = Pirep::factory()->create(['user_id' => $this->pilot->id]);
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Pushback approved']);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.show', ['id' => $pirep->id]))
        ->assertOk()
        ->assertSee('ACARS LOG')
        ->assertSee('Pushback approved');
});

it('is public', function (): void {
    $pirep = Pirep::factory()->create();

    $this->get(route('frontend.pireps.show', ['id' => $pirep->id]))->assertOk()->assertSee($pirep->ident);
});

it('renders the views addons register in the pireps.detail.main outlet', function (): void {
    $dir = sys_get_temp_dir().'/pirep-outlet-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/probe.blade.php', '<p id="pirep-outlet">outlet for {{ $pirep->ident }}</p>');
    View::addNamespace('pireptest', $dir);

    Skylight::slots()->register(['slot' => 'pireps.detail.main', 'view' => 'pireptest::probe']);

    $pirep = Pirep::factory()->create(['user_id' => $this->pilot->id]);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.show', ['id' => $pirep->id]))
        ->assertOk()
        ->assertSee('outlet for '.$pirep->ident);

    unlink($dir.'/probe.blade.php');
    rmdir($dir);
});
