<?php

declare(strict_types=1);

use App\Filament\Resources\Airports\Pages\ListAirports;
use App\Filament\Resources\Users\Pages\ListUsers;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Actions\Action;
use Filament\Pages\Page;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

/**
 * An action with no color falls back to the button blade's 'primary' default,
 * which the admin theme renders as the dark accent fill. Only the Create CTA
 * may do that; every other header action must carry an explicit color so it
 * renders as a neutral field.
 *
 * @param  class-string<Page> $page
 * @return list<string>
 */
function headerActionsFallingBackToPrimary(string $page): array
{
    return collect(livewireInstance($page)->getCachedHeaderActions())
        ->filter(fn (Action $action): bool => ($action->getColor() ?? 'primary') === 'primary')
        ->map(fn (Action $action): string => $action->getName())
        ->values()
        ->all();
}

it('only the add-airports CTA is primary on the airports header', function (): void {
    expect(headerActionsFallingBackToPrimary(ListAirports::class))->toBe(['addAirports']);
})->group('filament');

it('only the create CTA is primary on the users header', function (): void {
    expect(headerActionsFallingBackToPrimary(ListUsers::class))->toBe(['create']);
})->group('filament');
