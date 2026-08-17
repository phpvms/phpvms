<?php

declare(strict_types=1);

use Database\Seeders\RolesPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationManager;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    Auth::login(createAdminUser());
    Filament::setCurrentPanel('admin');
});

/**
 * Builds the admin panel navigation under the given locale.
 *
 * @return array<NavigationGroup>
 */
function navigationGroupsForLocale(string $locale): array
{
    app()->setLocale($locale);

    // NavigationManager snapshots the panel's groups in its constructor, so a
    // stale singleton would replay the previous locale's labels.
    app()->forgetInstance(NavigationManager::class);

    return Filament::getNavigation();
}

// The collapsed desktop sidebar renders the group icon instead of its items
// (see vendor/filament/filament/resources/views/components/sidebar/group.blade.php:13);
// a group without an icon silently loses its rail trigger and flyout.
it('gives every labelled admin navigation group an icon in every locale', function (string $locale): void {
    $labelled = array_filter(
        navigationGroupsForLocale($locale),
        fn (NavigationGroup $group): bool => filled($group->getLabel()),
    );

    expect($labelled)->not->toBeEmpty();

    foreach ($labelled as $group) {
        expect($group->getIcon())
            ->not->toBeNull("Group [{$group->getLabel()}] has no icon in locale [{$locale}]");
    }
})->with(['en', 'fr']);

it('renders the same group icons regardless of locale', function (): void {
    // Mirrors NavigationGroup::getIcon(); icons are Phosphor enum cases since
    // the heroicons swap, and enum cases compare identically across locales.
    $iconsFor = fn (string $locale): array => array_values(array_map(
        fn (NavigationGroup $group): string|BackedEnum|Htmlable|null => $group->getIcon(),
        array_filter(
            navigationGroupsForLocale($locale),
            fn (NavigationGroup $group): bool => filled($group->getLabel()),
        ),
    ));

    expect($iconsFor('fr'))->toBe($iconsFor('en'));
});
