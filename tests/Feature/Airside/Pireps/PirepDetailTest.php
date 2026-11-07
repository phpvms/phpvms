<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Models\Pirep;
use App\Support\Extensions\Facades\Extensions;
use Illuminate\Support\Facades\View;

/**
 * 7.7: public access to the PIREP detail page and the `pireps.detail.main` outlet.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('is public', function (): void {
    $pirep = Pirep::factory()->create();

    $this->get(route('frontend.pireps.show', ['id' => $pirep->id]))->assertOk();
});

it('renders the views addons register in the pireps.detail.main outlet', function (): void {
    $dir = sys_get_temp_dir().'/pirep-outlet-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/probe.blade.php', '<p id="pirep-outlet">outlet for {{ $pirep->ident }}</p>');
    View::addNamespace('pireptest', $dir);

    Extensions::slots()->register(['slot' => 'pireps.detail.main', 'view' => 'pireptest::probe']);

    $pirep = Pirep::factory()->create(['user_id' => $this->pilot->id]);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.show', ['id' => $pirep->id]))
        ->assertOk()
        ->assertSee('outlet for '.$pirep->ident);

    unlink($dir.'/probe.blade.php');
    rmdir($dir);
});
