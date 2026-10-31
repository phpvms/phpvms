<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Models\Award;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserIdentity;
use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * 7.10: the airside profile page (cards, own-profile-only cards, public view).
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('renders the record cards and the own-profile cards for your own profile', function (): void {
    $this->pilot->update(['name' => 'Ada Lovelace']);

    $this->actingAs($this->pilot)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Edit profile')
        ->assertSee('API access')
        ->assertDontSee('Type ratings')
        ->assertDontSee('ACARS config');
});

it('shows only the read-only cards on another pilot profile', function (): void {
    $other = User::factory()->create(['name' => 'John Smith']);

    $this->actingAs($this->pilot)
        ->get('/profile/'.$other->id)
        ->assertOk()
        ->assertSee('John S')
        ->assertDontSee('John Smith')
        ->assertSee('Record')
        ->assertDontSee('Edit profile')
        ->assertDontSee('API access')
        ->assertDontSee('Connected accounts');
});

it('lists earned awards and public details', function (): void {
    $award = Award::factory()->create(['name' => 'Century Club']);
    $this->pilot->awards()->attach($award);
    $field = UserField::query()->create(['name' => 'Favourite Type', 'internal' => false, 'active' => true, 'show_on_registration' => true]);
    $this->pilot->fields()->create(['user_field_id' => $field->id, 'value' => 'B738']);

    $this->actingAs($this->pilot)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Century Club')
        ->assertSee('1 earned')
        ->assertDontSee('No awards earned yet.');
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

it('offers to link an enabled provider with a full-page redirect', function (): void {
    OAuthConnection::query()->where('connection_id', 'discord')->firstOrFail()->update([
        'display_name'    => 'Discord Crew',
        'client_id'       => 'client-id',
        'client_secret'   => 'client-secret',
        'enabled'         => true,
        'linking_enabled' => true,
    ]);

    $this->actingAs($this->pilot)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Connected accounts')
        ->assertSee('Discord Crew')
        ->assertSee('Not connected')
        ->assertSee('href="'.url('/oauth/discord/redirect?intent=link').'"', false)
        ->assertDontSee('Unlink');
});

it('offers to unlink a linked provider through a confirmed DELETE', function (): void {
    OAuthConnection::query()->where('connection_id', 'discord')->firstOrFail()->update([
        'display_name'    => 'Discord Crew',
        'client_id'       => 'client-id',
        'client_secret'   => 'client-secret',
        'enabled'         => true,
        'linking_enabled' => true,
    ]);
    UserIdentity::query()->create([
        'user_id'          => $this->pilot->id,
        'connection_id'    => 'discord',
        'provider_user_id' => 'subject-123',
    ]);

    $this->actingAs($this->pilot)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Connected')
        ->assertSee('subject-123')
        ->assertSee('Unlink Discord Crew?')
        ->assertSee('action="'.route('oauth.unlink', ['provider' => 'discord']).'"', false)
        ->assertSee('name="_method" value="DELETE"', false);
});
