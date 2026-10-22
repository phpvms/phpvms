<?php

declare(strict_types=1);

use App\Models\Pirep;
use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
});

it('renders the logbook page for the signed-in pilot', function (): void {
    $user = User::factory()->create();
    Pirep::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/pireps')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Pireps/Index', false)
            ->has('pireps', 2));
});

it('sends the page size so the pager can derive its page count', function (): void {
    // UPagination computes page count from total/perPage, so perPage has to be
    // in the payload -- currentPage/lastPage/total alone are not enough.
    $user = User::factory()->create();
    Pirep::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/pireps?limit=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('pagination.perPage', 2)
            ->where('pagination.total', 3)
            ->where('pagination.lastPage', 2)
            ->where('pagination.currentPage', 1));
});
