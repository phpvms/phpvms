<?php

declare(strict_types=1);

use App\Enums\NavigationGroup;
use App\Filament\Pages\Branding;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function brandingUser(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('renders for a user with the view permission', function (): void {
    $this->actingAs(brandingUser('view:branding'));

    Livewire::test(Branding::class)->assertSuccessful();
});

it('appears in the Config navigation group', function (): void {
    expect(Branding::getNavigationGroup())->toBe(NavigationGroup::Config);
});

it('denies access without the view permission', function (): void {
    $this->actingAs(brandingUser());

    expect(Branding::canAccess())->toBeFalse();
});

it('saves the airline name to general.site_name', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->fillForm([
            'general'  => ['site_name' => 'Acme Air'],
            'branding' => ['brand_color' => '#4f46e5'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->toBe('Acme Air')
        ->and(Setting::where('id', Setting::formatKey('branding.brand_color'))->value('value'))->toBe('#4f46e5');
});

it('rejects an invalid hex colour and writes nothing', function (): void {
    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->fillForm([
            'general'  => ['site_name' => 'Acme Air'],
            'branding' => ['brand_color' => 'not-a-color'],
        ])
        ->call('save')
        ->assertHasFormErrors(['branding.brand_color']);

    expect(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->not->toBe('Acme Air')
        ->and(Setting::where('id', Setting::formatKey('branding.brand_color'))->value('value'))->toBe('');
});

it('autosaves the logo upload without calling save', function (): void {
    Storage::fake(config('filesystems.public_files'));

    $this->actingAs(brandingUser('view:branding', 'edit:branding'));

    Livewire::test(Branding::class)
        ->set('data.logo', UploadedFile::fake()->image('logo.png', 64, 64))
        ->assertDispatched('autosaved');

    $url = Setting::where('id', Setting::formatKey('branding.logo_url'))->value('value');

    expect($url)->not->toBeEmpty()
        ->and(Setting::where('id', Setting::formatKey('general.site_name'))->value('value'))->not->toBe('Acme Air');
});
