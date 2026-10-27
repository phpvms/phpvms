<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;

it('shows the draft on the preview and the published body on the live url', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $page = Page::factory()->published()->claiming('/about')->create([
        'body'       => 'Published copy',
        'draft_body' => 'Draft copy',
    ]);

    $this->actingAs(createAdminUser())
        ->get(route('frontend.pages.preview', ['page' => $page->id]))
        ->assertOk()
        ->assertSee('Draft copy')
        ->assertDontSee('Published copy');

    auth()->logout();

    $this->get('/about')->assertOk()->assertSee('Published copy')->assertDontSee('Draft copy');

    expect($page->refresh()->body)->toBe('Published copy');
});

it('previews a page that was never published', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $page = Page::factory()->create(['draft_body' => 'Unreleased']);

    $this->actingAs(createAdminUser())
        ->get(route('frontend.pages.preview', ['page' => $page->id]))
        ->assertOk()
        ->assertSee('Unreleased');
});

it('forbids a user without the edit page permission', function (): void {
    $page = Page::factory()->create(['draft_body' => 'Unreleased']);

    $this->actingAs(User::factory()->create())
        ->get(route('frontend.pages.preview', ['page' => $page->id]))
        ->assertForbidden();
});

it('lets a user holding edit:page preview', function (): void {
    $page = Page::factory()->create(['draft_body' => 'Unreleased']);
    Permission::firstOrCreate(['name' => 'edit:page', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('edit:page');

    $this->actingAs($user->fresh())
        ->get(route('frontend.pages.preview', ['page' => $page->id]))
        ->assertOk();
});

it('redirects a guest to login', function (): void {
    $page = Page::factory()->create();

    $this->get(route('frontend.pages.preview', ['page' => $page->id]))
        ->assertRedirect();
});
