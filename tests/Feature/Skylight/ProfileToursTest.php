<?php

declare(strict_types=1);

use App\Features\Tour\Models\UserTour;
use App\Models\Flight;
use App\Models\User;
use App\Services\BidService;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
    tourSettingsBaseline();
});

it('shows a completed tour on the profile, with its name and leg count', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft, 'bundle' => $bundle] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);
    fileTourLeg($user, $flights[1], $aircraft);

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile', false)
            ->has('profile.tours', 1)
            ->where('profile.tours.0.name', $bundle->name)
            ->where('profile.tours.0.legs', 2));
});

it('does not show an in-progress tour on the profile', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('profile.tours', 0));
});

it('does not show a cancelled tour on the profile', function (): void {
    $user = User::factory()->create();
    UserTour::factory()->cancelled()->for($user)->create();

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('profile.tours', 0));
});

it('still shows a completed tour after its bundle is deleted', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft, 'bundle' => $bundle] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);
    fileTourLeg($user, $flights[1], $aircraft);
    $name = $bundle->name;

    // Flights restrict-delete their bundle; clear them first so the bundle
    // itself can go, leaving the run's snapshot as the only trace of it.
    Flight::where('bundle_id', $bundle->id)->delete();
    $bundle->delete();

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('profile.tours', 1)
            ->where('profile.tours.0.name', $name));
});

it("does not show another pilot's completed tour on this profile", function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    UserTour::factory()->completed()->for($other)->create(['name' => 'Someone Else Tour']);

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('profile.tours', 0));
});

it('renders the completed tour name on the profile in the blade theme', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft, 'bundle' => $bundle] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);
    fileTourLeg($user, $flights[1], $aircraft);

    Theme::set('seven');
    updateSetting('general.theme', 'seven');

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertSee(e($bundle->name), false);
});
