<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\FlightBundle;
use App\Services\BidService;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
    tourSettingsBaseline();
});

it('lists enabled tours with their legs in order', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user] = makeTour(3);
    FlightBundle::factory()->create(['type' => BundleType::Tour, 'enabled' => false]);
    FlightBundle::factory()->create();
    app(AssetService::class)->storeLink(
        'https://example.com/tour.jpg',
        Asset::SLOT_BUNDLE,
        (string) $bundle->id,
    );

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Tours', false)
            ->has('tours', 1)
            ->where('tours.0.id', $bundle->id)
            ->where('tours.0.valid', true)
            ->where('tours.0.status', null)
            ->where('tours.0.image', 'https://example.com/tour.jpg')
            ->has('tours.0.legs', 3)
            ->where('tours.0.legs.0.routeLeg', 1)
            ->where('tours.0.legs.2.routeLeg', 3)
            ->where('tours.0.activeLegFlightId', $flights[0]->id));
});

it('shows the pilot run: progress, flown legs and the next leg to open', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('tours.0.status', 'in_progress')
            ->where('tours.0.legsCompleted', 1)
            ->where('tours.0.legs.0.flown', true)
            ->where('tours.0.legs.1.flown', false)
            ->where('tours.0.activeLegFlightId', $flights[1]->id));
});

it('lists the pilot in-progress tour first, ahead of alphabetically earlier tours', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(2);
    $bundle->update(['name' => 'Zulu Tour']);
    FlightBundle::factory()->create(['type' => BundleType::Tour, 'name' => 'Alpha Tour']);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('tours', 2)
            ->where('tours.0.name', 'Zulu Tour')
            ->where('tours.0.status', 'in_progress')
            ->where('tours.1.name', 'Alpha Tour'));
});

it('keeps a disabled tour listed while the pilot has a run in progress', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $bundle->update(['enabled' => false]);

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('tours', 1)
            ->where('tours.0.status', 'in_progress'));
});

it('renders the Blade tours page on a blade theme', function (): void {
    ['bundle' => $bundle, 'user' => $user] = makeTour(2);
    Theme::set('seven');
    updateSetting('general.theme', 'seven');

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertSee(e($bundle->name), false);
});
