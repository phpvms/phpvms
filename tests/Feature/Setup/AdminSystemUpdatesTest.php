<?php

declare(strict_types=1);

use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Filament\Auth\AdminLogin;
use App\Filament\Pages\SystemUpdates;
use App\Http\Data\BrandingData;
use App\Http\Middleware\AdminGate;
use App\Http\Middleware\SetupGate;
use App\Models\User;
use App\Support\Branding;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

function adminPendingWork(array $pending): void
{
    app()->instance(MigrationPaths::class, new class($pending) extends MigrationPaths
    {
        public function __construct(private readonly array $pending) {}

        public function pendingSchema(): array
        {
            return $this->pending;
        }

        public function pendingData(): array
        {
            return [];
        }
    });
}

beforeEach(function (): void {
    // TestCase turns the gate off suite-wide; this file covers it.
    $this->withMiddleware([SetupGate::class, AdminGate::class]);
    adminPendingWork(['2026_01_01_000000_pending']);
});

function skipOnMysql(): Closure
{
    return fn (): bool => in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
}

test('branding falls back to the default colour when the assets table is missing, and picks up stored branding once it exists', function (): void {
    fakeAssetDisks();
    updateSetting('branding.brand_color', '#ff0000');
    createBrandingAsset(Branding::KEY_LOGO);

    Schema::rename('assets', 'assets_v7');

    try {
        Branding::flush();

        $branding = app(Branding::class);
        expect($branding->brandColor())->toBe('#067ec1')
            ->and($branding->getBranding()->hasLogo)->toBeFalse();
    } finally {
        Schema::rename('assets_v7', 'assets');
    }

    // A new request: the scoped instance goes, the shared cache stays.
    app()->forgetInstance(BrandingData::class);

    expect($branding->brandColor())->toBe('#ff0000')
        ->and($branding->getBranding()->hasLogo)->toBeTrue();
})->skip(skipOnMysql(), 'Renaming a table is DDL, which MySQL commits outside the test transaction.');

test('an admin is sent to system/update while work is pending after the upgrade', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    app(UpgradeCompleted::class)->write();

    $this->actingAs(createAdminUser());

    $this->get('/admin')->assertRedirect(SystemUpdates::getUrl());
    $this->get('/admin/system/update')->assertOk();
});

test('an admin is sent to /system/upgrade before the upgrade is completed', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());

    $this->get('/admin')->assertRedirect('/system/upgrade');
});

test('a guest is sent from the admin login to /system/upgrade before the upgrade is completed', function (): void {
    // A site without users is a fresh install, not a v7 site.
    User::factory()->create();

    $this->get('/admin/login')->assertRedirect('/system/upgrade');
});

test('a session that survived the deploy is sent to /system/upgrade, before any roles lookup', function (): void {
    $this->actingAs(User::factory()->create());
    Schema::rename('model_has_roles', 'model_has_roles_v7');

    try {
        $this->get('/admin')->assertRedirect('/system/upgrade');
        $this->post('/admin/logout')->assertRedirect('/system/upgrade');
    } finally {
        Schema::rename('model_has_roles_v7', 'model_has_roles');
    }
})->skip(skipOnMysql(), 'Renaming a table is DDL, which MySQL commits outside the test transaction.');

test('submitting the admin login before the upgrade goes to /system/upgrade, before any roles lookup', function (): void {
    $user = User::factory()->create(['password' => bcrypt('secret')]);
    Schema::rename('model_has_roles', 'model_has_roles_v7');

    try {
        Livewire::test(AdminLogin::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'secret')
            ->call('authenticate')
            ->assertRedirect('/system/upgrade');
    } finally {
        Schema::rename('model_has_roles_v7', 'model_has_roles');
    }

    $this->assertGuest();
})->skip(skipOnMysql(), 'Renaming a table is DDL, which MySQL commits outside the test transaction.');

test('a guest reaches the admin login after the upgrade, or when nothing is pending', function (): void {
    User::factory()->create();
    app(UpgradeCompleted::class)->write();
    $this->get('/admin/login')->assertOk();

    adminPendingWork([]);
    $this->get('/admin/login')->assertOk();
});

test('a non-admin gets 403 on system/update', function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    app(UpgradeCompleted::class)->write();

    $this->actingAs(User::factory()->create());

    $this->get('/admin/system/update')->assertForbidden();
});
