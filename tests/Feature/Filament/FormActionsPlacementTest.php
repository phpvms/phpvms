<?php

declare(strict_types=1);

use App\Filament\Resources\Awards\Pages\EditAward;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Award;
use Database\Seeders\RolesPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

/**
 * The rendered labels of the form's footer buttons, in DOM order.
 *
 * Asserted against the markup rather than the component tree on purpose: the
 * tree said "right-aligned" while the page rendered every button bunched at the
 * left, because alignment on an `Actions` component does nothing inside a
 * `Flex` -- only `.fi-sc-flex.fi-align-between` separates the two slots.
 *
 * @return array<int, string>
 */
function footerButtonOrder(string $html): array
{
    preg_match_all('/>\s*(Run test|Save changes|Cancel)\s*</', $html, $matches);

    return $matches[1];
}

it('splits the section footer, escape action before the primary one', function (string $page, array $args, array $expected): void {
    $html = Livewire::test($page, $args)->html();

    expect(footerButtonOrder($html))->toBe($expected)
        // The three CSS facts this layout rests on, each of which silently
        // broke it once: the row must span the footer (`fi-growable` inside
        // the inline container), it must space its slots apart, and neither
        // slot may grow -- a growing slot eats the row and wraps the other.
        ->and(preg_replace('/\s+/', ' ', $html))
        ->toMatch('/class="fi-growable" > <div class="fi-sc-component" ?> <div class="fi-sc-flex[^"]*fi-align-between"/')
        // `.fi-ac.fi-align-end` is `flex-direction: row-reverse`, which would
        // flip the submit pair back to primary-first.
        ->and($html)->not->toContain('fi-ac fi-align-end')
        // Icons on the submit pair, or they render shorter than any button
        // that has one and the row looks misaligned. Tabler, like the rest of
        // the panel -- and nothing from heroicons, which only reaches the app
        // as a Filament dependency.
        ->and($html)->toContain('icon-tabler-device-floppy')
        ->and($html)->toContain('icon-tabler-x')
        ->and($html)->not->toContain('icon-heroicon');
})->with([
    'award edit' => fn (): array => [
        EditAward::class,
        ['record' => Award::factory()->rules()->create()->getRouteKey()],
        ['Run test', 'Cancel', 'Save changes'],
    ],
    'role edit' => fn (): array => [
        EditRole::class,
        ['record' => Role::first()->getRouteKey()],
        ['Cancel', 'Save changes'],
    ],
]);
