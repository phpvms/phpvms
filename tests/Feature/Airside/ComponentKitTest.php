<?php

declare(strict_types=1);

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/Support.php';

/**
 * The shared x-airside:: component kit the pages are built from.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

it('renders a page with a header, links slot and region label', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-airside::page name="logbook" label="Logbook">
            <x-airside::page-header headline="Logbook" title="My PIREPs" description="Most recent first.">
                <x-slot:links><span>12 total</span></x-slot:links>
            </x-airside::page-header>
            <p>body</p>
        </x-airside::page>
        BLADE);

    expect($html)
        ->toContain('pv-logbook')
        ->toContain('aria-label="Logbook"')
        ->toContain('My PIREPs')
        ->toContain('Most recent first.')
        ->toContain('12 total')
        ->toContain('body');
});

it('renders a card with title, body and footer, and omits empty regions', function (): void {
    $html = Blade::render('<x-airside::card title="Details" description="More">Body<x-slot:footer>Foot</x-slot:footer></x-airside::card>');

    // TallStackUI's card: the header sits above a bottom border, the footer below a top border.
    expect($html)->toContain('x-data="tallstackui_card(')->toContain('Details')->toContain('More')->toContain('Body')
        ->toMatch('/border-t[^>]*>.*Foot/s');

    $bare = Blade::render('<x-airside::card>Only</x-airside::card>');
    expect($bare)->toContain('Only')->not->toContain('<h2')->not->toContain('border-t');
});

it('renders an empty state with optional icon and actions', function (): void {
    $html = Blade::render('<x-airside::empty icon="map" title="Nothing" description="Nope"><x-slot:actions><a>Go</a></x-slot:actions></x-airside::empty>');

    expect($html)->toContain('Nothing')->toContain('Nope')->toContain('<svg')->toContain('<a>Go</a>');
    expect(Blade::render('<x-airside::empty title="Bare" />'))->not->toContain('<svg');
});

it('renders stat tiles with dash for null and the accent colour', function (): void {
    $plain = Blade::render('<x-airside::stat label="Flights" value="12" />');
    expect($plain)->toContain('x-data="tallstackui_stats(')->toContain('Flights')->toContain('12')->not->toContain('font-mono');

    $accented = Blade::render('<x-airside::stat label="Hours" value="3:10" mono accent="green" />');
    expect($accented)->toContain('font-mono')->toContain('green');

    expect(Blade::render('<x-airside::stat label="Rank" :value="null" />'))->toContain('—');
});

it('renders the stats grid with dash for null values', function (): void {
    $html = Blade::render('<x-airside::stats :items="$items" :columns="2" />', [
        'items' => [['label' => 'ETE', 'value' => '7:12'], ['label' => 'Expires', 'value' => null]],
    ]);

    expect($html)->toContain('ETE')->toContain('7:12')->toContain('Expires')->toContain('—')->toContain('repeat(2,');
});

it('renders flight ident with initials fallback, VA default and logo', function (): void {
    $initials = Blade::render('<x-airside::flight-ident callsign="PVA101" dpt="KJFK" arr="EGLL" airline="phpvms air" aircraft="N1 · B738" />');
    expect($initials)->toContain('PH')->toContain('PVA101')->toContain('KJFK')->toContain('EGLL')->toContain('N1 · B738')->not->toContain('<img');

    expect(Blade::render('<x-airside::flight-ident callsign="X1" />'))->toContain('VA')->toContain('—');

    $logo = Blade::render('<x-airside::flight-ident callsign="X1" airline="Acme" logo="/a.png" href="/flights/1" stacked size="lg" />');
    expect($logo)->toContain('<img')->toContain('/a.png')->toContain('<a ')->toContain('href="/flights/1"')->toContain('flex-col');
});

it('maps state colours to badge colours', function (string $state, string $color): void {
    $html = Blade::render('<x-airside::state-badge :state-color="$state" label="Label" />', ['state' => $state]);

    expect($html)->toContain('Label')->toContain($color.'-');
})->with([
    ['success', 'green'],
    ['warning', 'amber'],
    ['danger', 'red'],
    ['info', 'sky'],
    ['gray', 'slate'],
    ['unknown', 'slate'],
]);

it('renders an accessible loading state', function (): void {
    $html = Blade::render('<x-airside::loading text="Loading flights" />');

    expect($html)->toContain('role="status"')->toContain('aria-label="Loading flights"')->toContain('Loading flights');
    expect(Blade::render('<x-airside::loading />'))->toContain('aria-label="Loading"');
});

it('renders a drawer with slots, side classes and a livewire entangle', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-airside::drawer name="filters" title="Filters" description="Narrow it">
            <x-slot:trigger><b>Open</b></x-slot:trigger>
            Body
            <x-slot:footer>Foot</x-slot:footer>
        </x-airside::drawer>
        BLADE);

    // TallStackUI's slide: opened through $tsui, closed on Escape, the footer in its own bordered area.
    expect($html)->toContain('x-data="tallstackui_slide(')->toContain('Filters')->toContain('Narrow it')->toContain('<b>Open</b>')->toContain('Body')
        ->toMatch('/border-t[^>]*>.*Foot/s')
        ->toContain("x-on:click=\"\$tsui.open.slide('filters')\"")
        ->toContain('airside-drawer-open.window')
        ->toContain('x-on:slide:filters-open.window')
        ->toContain('translate-x-full');

    // The bottom variant slides up. The Livewire-bound form (wire:model) needs a Livewire
    // render and is covered by the bids and API connection drawer tests.
    $bottom = Blade::render('<x-airside::drawer name="sheet" side="bottom" />');
    expect($bottom)->toContain('translate-y-full')->not->toContain('wire:model');
});

it('renders an inline confirm that forwards wire attributes to the confirm button', function (): void {
    $html = Blade::render('<x-airside::confirm prompt="Cancel this bid?" confirm-label="Yes, cancel it" keep-label="Keep" wire:click="remove(5)"><x-slot:trigger>Cancel</x-slot:trigger></x-airside::confirm>');

    expect($html)->toContain('Cancel this bid?')->toContain('Yes, cancel it')->toContain('Keep')->toContain('wire:click="remove(5)"')->toContain('asking: false');
    expect(substr_count($html, 'wire:click'))->toBe(1);

    $custom = Blade::render('<x-airside::confirm><x-slot:trigger>T</x-slot:trigger><x-slot:confirm><button>Sure</button></x-slot:confirm></x-airside::confirm>');
    expect($custom)->toContain('<button>Sure</button>');
});

it('renders a back link', function (): void {
    expect(Blade::render('<x-airside::back-link href="/flights">flight manifest</x-airside::back-link>'))
        ->toContain('href="/flights"')->toContain('Back to flight manifest')->toContain('<svg')->toContain('wire:navigate');
});

it('renders an icon and nothing for an unknown name', function (): void {
    expect(Blade::render('<x-airside::icon name="map" />'))->toContain('<svg');
    expect(trim(Blade::render('<x-airside::icon name="no-such-icon-xyz" />')))->toBe('');
});

it('lists the kit in the showcase', function (): void {
    Route::middleware('web')->get('/airside-views/kit', fn (): Factory|View => view('airside::showcase'));

    $this->get('/airside-views/kit')->assertOk()->assertSee('Component kit')->assertSee('Cancel this bid?')->assertSee('Open drawer');
});
