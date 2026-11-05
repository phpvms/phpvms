<?php

declare(strict_types=1);

use App\Filament\GlobalSearch\CommandPaletteSearchProvider;
use App\Filament\Resources\Airlines\AirlineResource;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Permission;
use App\Models\Pirep;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResults;

use function Pest\Laravel\actingAs;

/**
 * @return array<string, array<int, string>> category name => result titles
 */
function paletteTitles(GlobalSearchResults $results): array
{
    return $results->getCategories()
        ->map(fn ($category): array => collect($category)->map(fn ($result): string => (string) $result->title)->values()->all())
        ->all();
}

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    Filament::setServingStatus();
});

it('lists a navigation page the user can open under Go to, and omits one they cannot', function (): void {
    foreach (['view:dashboard', 'view:pirep'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user = User::factory()->create();
    $user->givePermissionTo('view:dashboard', 'view:pirep');
    actingAs($user->fresh());

    $titles = paletteTitles(new CommandPaletteSearchProvider()->getResults(PirepResource::getNavigationLabel()));

    expect(array_key_first($titles))->toBe(__('filament.palette_go_to'))
        ->and($titles[__('filament.palette_go_to')])->toContain((string) PirepResource::getNavigationLabel());

    $users = paletteTitles(new CommandPaletteSearchProvider()->getResults(UserResource::getNavigationLabel()));

    expect($users[__('filament.palette_go_to')] ?? [])->not->toContain((string) UserResource::getNavigationLabel());
});

it('still returns record categories after Go to', function (): void {
    actingAs(createAdminUser());
    createAdminUser(['name' => 'Zebulon Quux']);

    $titles = paletteTitles(new CommandPaletteSearchProvider()->getResults('Zebulon'));

    expect($titles)->toHaveKey(UserResource::getPluralModelLabel())
        ->and($titles[UserResource::getPluralModelLabel()])->toContain('Zebulon Quux');
});

it('finds users by email and by pilot id', function (): void {
    actingAs(createAdminUser());
    $target = User::factory()->create(['email' => 'findme-unique@example.test', 'pilot_id' => 90417]);
    $label = UserResource::getPluralModelLabel();

    $byEmail = paletteTitles(new CommandPaletteSearchProvider()->getResults('findme-unique'));
    $byPilotId = paletteTitles(new CommandPaletteSearchProvider()->getResults('90417'));

    expect($byEmail[$label])->toContain($target->name)
        ->and($byPilotId[$label])->toContain($target->name);
});

it('returns aircraft results with a URL even though the resource is nested', function (): void {
    actingAs(createAdminUser());
    $aircraft = Aircraft::factory()->create(['registration' => 'N-PALETTE1']);

    $results = new CommandPaletteSearchProvider()->getResults('N-PALETTE1');
    $urls = $results->getCategories()->flatten(1)->map(fn ($result): string => $result->url);

    expect($urls->contains(fn (string $url): bool => str_contains($url, "/subfleets/{$aircraft->subfleet_id}/") && str_contains($url, (string) $aircraft->getRouteKey())))->toBeTrue();
});

it('returns flight results with a URL even though the resource is nested', function (): void {
    actingAs(createAdminUser());
    $flight = Flight::factory()->create(['flight_number' => 48213]);

    $results = new CommandPaletteSearchProvider()->getResults('48213');
    $urls = $results->getCategories()->flatten(1)->map(fn ($result): string => $result->url);

    expect($urls->contains(fn (string $url): bool => str_contains($url, (string) $flight->getRouteKey())))->toBeTrue();
});

it('searches pireps without lazy loading the airline', function (): void {
    actingAs(createAdminUser());
    // Lazy-loading violations only fire for models hydrated alongside others.
    Pirep::factory()->count(2)->create(['flight_number' => '59317']);

    $titles = paletteTitles(new CommandPaletteSearchProvider()->getResults('59317'));

    expect($titles)->toHaveKey(PirepResource::getPluralModelLabel());
});

it('offers an Add action that links to the create page for an admin', function (): void {
    actingAs(createAdminUser());

    $results = new CommandPaletteSearchProvider()->getResults('add airline');
    $actions = collect($results->getCategories()[__('filament.palette_actions')] ?? []);

    expect($actions->map(fn ($result): string => $result->url)->all())->toContain(AirlineResource::getUrl('create'));
});

it('omits the Add action when the user cannot create that record', function (): void {
    actingAs(User::factory()->create());

    $results = new CommandPaletteSearchProvider()->getResults('add airline');
    $actions = collect($results->getCategories()[__('filament.palette_actions')] ?? []);

    expect($actions->map(fn ($result): string => $result->url)->all())->not->toContain(AirlineResource::getUrl('create'));
});
