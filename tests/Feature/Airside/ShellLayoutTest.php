<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Models\ThemeSetting;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;

/**
 * 6.1: the admin link by permission, and stored theme css escaping.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->withoutVite();
});

it('offers Go to Admin only to a user with admin access, as a full-load link', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $adminUrl = Filament::getPanel('admin')->getUrl();

    $pilotHtml = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();
    expect($pilotHtml)->not->toContain(__('ui.go_to_admin'));

    // ShellData is request-scoped; a test process keeps the first request's instance.
    app()->forgetScopedInstances();

    $adminHtml = $this->actingAs(createAdminUser())->get('/airside-probe/pilot')->getContent();
    expect($adminHtml)->toContain(__('ui.go_to_admin'))
        ->toMatch('/<a\s+href="'.preg_quote($adminUrl, '/').'"(?![^>]*wire:navigate)[^>]*>/s');
});

it('neutralises a closing style tag in the stored theme css', function (): void {
    ThemeSetting::factory()->create(['theme' => 'airside', 'name' => 'css', 'content' => 'a{}</STYLE><script>x</script>']);

    $html = $this->actingAs($this->pilot)->get('/airside-probe/pilot')->getContent();

    expect($html)->toContain('a{}<\/style><script>x</script>');
});
