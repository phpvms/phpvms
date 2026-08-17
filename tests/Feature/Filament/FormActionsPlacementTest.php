<?php

declare(strict_types=1);

use App\Filament\Resources\Awards\Pages\EditAward;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Award;
use App\Models\Flight;
use App\Models\FlightBundle;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Str;
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

it('splits the section footer, escape action before the primary one', function (string $page, array $args, array $expected, string $alignment): void {
    $html = Livewire::test($page, $args)->html();

    expect(footerButtonOrder($html))->toBe($expected)
        // The three CSS facts this layout rests on, each of which silently
        // broke it once: the row must span the footer (`fi-growable` inside
        // the inline container), it must justify the submit pair to the right
        // edge, and neither slot may grow -- a growing slot eats the row and
        // wraps the other.
        //
        // The justification depends on how many slots there are.
        // `fi-align-between` is `space-between`, which pins one child to each
        // edge -- correct with a footer action on the left, but an
        // `Actions::make([])` renders no element at all, so with no footer
        // action the row has a single child and space-between lays it out at
        // flex-start. That left Cancel and Save on the left of every page
        // whose form has no footer actions. One slot therefore takes
        // `fi-align-end`, a plain `justify-content: flex-end`.
        ->and(preg_replace('/\s+/', ' ', $html))
        ->toMatch('/class="fi-growable" > <div class="fi-sc-component" ?> <div class="fi-sc-flex[^"]*'.$alignment.'"/')
        // `.fi-ac.fi-align-end` is `flex-direction: row-reverse`, which would
        // flip the submit pair back to primary-first.
        ->and($html)->not->toContain('fi-ac fi-align-end')
        // Icons on the submit pair, or they render shorter than any button
        // that has one and the row looks misaligned. Phosphor, like the rest of
        // the panel -- and nothing from heroicons, which only reaches the app
        // as a Filament dependency.
        // Phosphor SVGs carry no identifying class, so the 256 viewBox is the
        // discriminator: heroicons and Tabler both draw on 24.
        ->and($html)->toContain('viewBox="0 0 256 256"')
        ->and($html)->not->toContain('icon-heroicon');
})->with([
    // Two slots: the schema's own footer action holds the left edge.
    'award edit' => fn (): array => [
        EditAward::class,
        ['record' => Award::factory()->rules()->create()->getRouteKey()],
        ['Run test', 'Cancel', 'Save changes'],
        'fi-align-between',
    ],
    // One slot: nothing on the left, so the pair is justified to the end.
    'role edit' => fn (): array => [
        EditRole::class,
        ['record' => Role::first()->getRouteKey()],
        ['Cancel', 'Save changes'],
        'fi-align-end',
    ],
]);

/**
 * `Section::footer()` replaces the footer schema, so writing the submit row
 * into it used to drop whatever the form had put there. FlightForm's `enabled`
 * toggle is the only such component in the app, and losing it left the flight
 * edit page with no way to disable a flight — silently, because the page still
 * rendered and still saved.
 */
it('keeps a form-declared footer component on its own row above the submit row', function (): void {
    $bundle = FlightBundle::factory()->create(['start_date' => null, 'end_date' => null]);
    $flight = Flight::factory()->create(['bundle_id' => $bundle->id]);

    $component = Livewire::test(EditFlight::class, [
        'record'       => $flight->getRouteKey(),
        'parentRecord' => $bundle,
    ])->assertSuccessful();

    $component->assertFormFieldExists('enabled');

    // Stacked, not inline beside Cancel/Save: the one-column Grid is what the
    // `.fi-section-footer .fi-grid > * + *` rule hangs the divider off.
    $footer = Str::between($component->html(), '<footer class="fi-section-footer">', '</footer>');

    expect($footer)->toContain('fi-grid')
        ->and($footer)->toContain('fi-fo-toggle')
        ->and(footerButtonOrder($footer))->toBe(['Cancel', 'Save changes']);
});
