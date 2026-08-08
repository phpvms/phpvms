<?php

declare(strict_types=1);

use App\Filament\Resources\Fares\Pages\ListFares;
use App\Filament\Resources\Fares\Support\FareTrace;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * FareTrace mirrors FareService::getFareWithPivot on replicated fares, so
 * these assert the full cascade the admin map/probe display: base →
 * subfleet (absolute or percentage) → flight stacking on the subfleet's
 * values, capacity flooring, and the flight pivot's inability to touch
 * auto-price inputs.
 */
function traceFixture(array $subfleetPivot = [], array $flightPivot = []): array
{
    $fare = Fare::factory()->create([
        'price'      => 100, 'cost' => 40, 'capacity' => 180,
        'base_price' => 50, 'per_nm' => 0.10, 'multiplier' => 1.0,
    ]);

    $subfleet = null;
    if ($subfleetPivot !== []) {
        $subfleet = Subfleet::factory()->create();
        $subfleet->fares()->attach($fare->id, $subfleetPivot);
    }

    $flight = null;
    if ($flightPivot !== []) {
        $flight = Flight::factory()->create();
        $flight->fares()->attach($fare->id, $flightPivot);
    }

    return FareTrace::resolve(
        $fare,
        $subfleet?->fares()->first()->pivot,
        $flight?->fares()->first()->pivot,
    );
}

it('traces a fare with no overrides to its base values', function (): void {
    $trace = traceFixture();

    expect($trace['price'])->toMatchArray(['source' => 'base'])
        ->and((float) $trace['price']['value'])->toBe(100.0)
        ->and($trace['capacity']['source'])->toBe('base');
});

it('traces subfleet absolute and percentage overrides', function (): void {
    $trace = traceFixture(['price' => '120', 'cost' => '110%']);

    expect($trace['price']['source'])->toBe('subfleet')
        ->and((float) $trace['price']['value'])->toBe(120.0)
        ->and($trace['cost']['source'])->toBe('subfleet')
        ->and((float) $trace['cost']['value'])->toBe(44.0)
        ->and($trace['cost']['subfleet']['raw'])->toBe('110%')
        ->and($trace['capacity']['source'])->toBe('base');
});

it('stacks a flight percentage on top of the subfleet value', function (): void {
    $trace = traceFixture(['price' => '120'], ['price' => '125%']);

    expect($trace['price']['source'])->toBe('flight')
        ->and((float) $trace['price']['value'])->toBe(150.0)
        ->and((float) $trace['price']['subfleet']['value'])->toBe(120.0);
});

it('floors percentage capacity overrides to whole seats', function (): void {
    $trace = traceFixture(['capacity' => '105%']);

    expect($trace['capacity']['value'])->toBe(189)
        ->and($trace['capacity']['source'])->toBe('subfleet');
});

it('keeps auto-price inputs out of reach of flight pivots', function (): void {
    $trace = traceFixture(['per_nm' => '0.25'], ['price' => '200']);

    expect($trace['per_nm']['source'])->toBe('subfleet')
        ->and((float) $trace['per_nm']['value'])->toBe(0.25)
        ->and($trace['base_price']['source'])->toBe('base');
});

it('tells bare attachments apart from real overrides', function (): void {
    $fare = Fare::factory()->create();
    $subfleet = Subfleet::factory()->create();
    $subfleet->fares()->attach($fare->id);

    expect(FareTrace::pivotOverrides($subfleet->fares()->first()->pivot))->toBeFalse();

    $subfleet->fares()->updateExistingPivot($fare->id, ['price' => '+10%']);

    expect(FareTrace::pivotOverrides($subfleet->fares()->first()->pivot))->toBeTrue();
});

it('renders the override map blade with both layers', function (): void {
    $fare = Fare::factory()->create(['price' => 100, 'cost' => 40, 'capacity' => 180]);
    $subfleet = Subfleet::factory()->create(['name' => 'Mainline 738']);
    $subfleet->fares()->attach($fare->id, ['cost' => '110%']);
    $flight = Flight::factory()->create();
    $flight->fares()->attach($fare->id, ['price' => '125%']);

    $html = view('filament.fares.override-map', [
        'fare' => $fare->load(['subfleets', 'flights']),
    ])->render();

    expect($html)->toContain('Mainline 738')
        ->toContain('110%')
        ->toContain($flight->ident)
        ->toContain('125%');
});

it('renders the probe results blade with provenance badges', function (): void {
    $fare = Fare::factory()->create(['price' => 100, 'cost' => 40, 'capacity' => 180]);
    $subfleet = Subfleet::factory()->create();
    $subfleet->fares()->attach($fare->id, ['price' => '120']);
    $flight = Flight::factory()->create();
    $flight->subfleets()->attach($subfleet->id);
    $flight->fares()->attach($fare->id, ['capacity' => '160']);

    $html = view('filament.fares.probe-results', [
        'flight' => $flight->load(['subfleets.fares', 'fares', 'airline']),
    ])->render();

    expect($html)->toContain('SUBF')
        ->toContain('FLT')
        ->toContain($fare->code);
});

it('mounts the overrides and probe actions on the fares list', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $fare = Fare::factory()->create();
    $subfleet = Subfleet::factory()->create();
    $subfleet->fares()->attach($fare->id, ['price' => '+10%']);

    // The overrides action is schema-less (modalContent only), which the
    // action test harness can't mount — its content is covered by the
    // blade-render test above.
    Livewire::test(ListFares::class)
        ->assertSuccessful()
        ->assertActionVisible(TestAction::make('overrides')->table($fare));

    Livewire::test(ListFares::class)
        ->mountAction('probe')
        ->assertHasNoActionErrors();
});
