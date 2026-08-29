<?php

declare(strict_types=1);

use App\Models\Award;
use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
});

it('marks the signed-in pilot own profile as own', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Profile', false)
            ->where('profile.isOwnProfile', true));
});

it("does not mark another pilot's profile as own", function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile/'.$other->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('profile.isOwnProfile', false));
});

it("shows another pilot's shortened name, not their full name", function (): void {
    // A two-word name discriminates: name_private returns "John S" for
    // "John Smith" -- a single-word name would return unchanged and prove
    // nothing.
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'John Smith']);

    $this->actingAs($user)
        ->get('/profile/'.$other->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('profile.name', 'John S'));
});

it('shows the full name on your own profile', function (): void {
    $user = User::factory()->create(['name' => 'John Smith']);

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('profile.name', 'John Smith'));
});

it('normalizes a blank award description to null', function (string $blankDescription): void {
    $user = User::factory()->create();
    $award = Award::factory()->create(['description' => $blankDescription]);
    $user->awards()->attach($award);

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('profile.awards', 1)
            ->where('profile.awards.0.description', null));
})->with([
    'empty paragraph'          => ['<p></p>'],
    'paragraph with only nbsp' => ['<p>&nbsp;</p>'],
]);

it('keeps a real award description, flattened to plain text', function (): void {
    $user = User::factory()->create();
    // Descriptions are plain text now; markup from the old RichEditor is
    // flattened on write by Award::description(). See AwardDescriptionTest.
    $award = Award::factory()->create(['description' => '<p>Real <strong>text</strong></p>']);
    $user->awards()->attach($award);

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('profile.awards.0.description', 'Real text'));
});

it('renders the Blade profile with the Edit button on your own profile', function (): void {
    $user = User::factory()->create();
    Theme::set('seven');
    updateSetting('general.theme', 'seven');

    $this->actingAs($user)
        ->get('/profile/'.$user->id)
        ->assertOk()
        ->assertSee('/profile/'.$user->id.'/edit', false);
});

it('hides the Edit button on the Blade profile of another pilot', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Theme::set('seven');
    updateSetting('general.theme', 'seven');

    $this->actingAs($user)
        ->get('/profile/'.$other->id)
        ->assertOk()
        ->assertDontSee('/profile/'.$other->id.'/edit', false);
});
