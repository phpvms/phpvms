<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * 7.10: the airside profile page: addon card outlet context and the edit redirect.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('renders addon entries in the profile.cards slot outlet with the profile as context', function (): void {
    $dir = sys_get_temp_dir().'/airside-profile-slot-'.uniqid('', true);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/card.blade.php', 'ADDON-CARD for {{ $profile->name }} own={{ $profile->isOwnProfile ? "yes" : "no" }}');
    View::addNamespace('profileslot', $dir);
    Skylight::slots()->register(['slot' => 'profile.cards', 'view' => 'profileslot::card']);

    $this->pilot->update(['name' => 'Grace Hopper']);

    $this->actingAs($this->pilot)
        ->get('/profile')
        ->assertOk()
        ->assertSee('ADDON-CARD for Grace Hopper own=yes');

    File::deleteDirectory($dir);
});

it('sends the profile edit and connections pages to the profile page', function (): void {
    $this->actingAs($this->pilot)
        ->get(route('frontend.profile.connections'))
        ->assertRedirect(route('frontend.profile.index'));
});
