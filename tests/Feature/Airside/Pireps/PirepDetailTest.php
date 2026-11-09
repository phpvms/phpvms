<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingPirepDetail;
use App\Models\Pirep;
use Illuminate\Support\Facades\View;

final class PirepOutletProbe
{
    public function handle(RenderingPirepDetail $hook): void
    {
        $hook->add(Fill::view('pireptest::probe', ['pirep' => $hook->pirep]));
    }
}

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

it('renders the fills addons add to the pireps.detail.main hook', function (): void {
    $dir = sys_get_temp_dir().'/pirep-outlet-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/probe.blade.php', '<p id="pirep-outlet">outlet for {{ $pirep->ident }}</p>');
    View::addNamespace('pireptest', $dir);

    Hooks::listen(RenderingPirepDetail::class, PirepOutletProbe::class);

    $pirep = Pirep::factory()->create(['user_id' => $this->pilot->id]);

    $this->actingAs($this->pilot)->get(route('frontend.pireps.show', ['id' => $pirep->id]))
        ->assertOk()
        ->assertSee('outlet for '.$pirep->ident);

    unlink($dir.'/probe.blade.php');
    rmdir($dir);
});
