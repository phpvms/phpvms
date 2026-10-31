<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

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
        ->toContain('aria-label="Logbook"')
        ->toContain('My PIREPs')
        ->toContain('Most recent first.')
        ->toContain('12 total')
        ->toContain('body');
});

it('renders a card with title, body and footer, and omits empty regions', function (): void {
    $html = Blade::render('<x-airside::card title="Details" description="More">Body<x-slot:footer>Foot</x-slot:footer></x-airside::card>');

    expect($html)->toContain('Details')->toContain('More')->toContain('Body')->toContain('Foot');

    $bare = Blade::render('<x-airside::card>Only</x-airside::card>');
    expect($bare)->toContain('Only')->not->toContain('<h2');
});

it('renders an empty state with optional icon and actions', function (): void {
    $html = Blade::render('<x-airside::empty icon="map" title="Nothing" description="Nope"><x-slot:actions><a>Go</a></x-slot:actions></x-airside::empty>');

    expect($html)->toContain('Nothing')->toContain('Nope')->toContain('<svg')->toContain('<a>Go</a>');
    expect(Blade::render('<x-airside::empty title="Bare" />'))->not->toContain('<svg');
});

it('renders stat tiles with dash for null and the accent colour', function (): void {
    $plain = Blade::render('<x-airside::stat label="Flights" value="12" />');
    expect($plain)->toContain('Flights')->toContain('12');

    expect(Blade::render('<x-airside::stat label="Rank" :value="null" />'))->toContain('—');
});

it('renders the stats grid with dash for null values', function (): void {
    $html = Blade::render('<x-airside::stats :items="$items" :columns="2" />', [
        'items' => [['label' => 'ETE', 'value' => '7:12'], ['label' => 'Expires', 'value' => null]],
    ]);

    expect($html)->toContain('ETE')->toContain('7:12')->toContain('Expires')->toContain('—');
});

it('renders flight ident with initials fallback, VA default and logo', function (): void {
    $initials = Blade::render('<x-airside::flight-ident callsign="PVA101" dpt="KJFK" arr="EGLL" airline="phpvms air" aircraft="N1 · B738" />');
    expect($initials)->toContain('PH')->toContain('PVA101')->toContain('KJFK')->toContain('EGLL')->toContain('N1 · B738')->not->toContain('<img');

    expect(Blade::render('<x-airside::flight-ident callsign="X1" />'))->toContain('VA')->toContain('—');

    $logo = Blade::render('<x-airside::flight-ident callsign="X1" airline="Acme" logo="/a.png" href="/flights/1" stacked size="lg" />');
    expect($logo)->toContain('<img')->toContain('/a.png')->toContain('<a ')->toContain('href="/flights/1"');
});

it('renders an accessible loading state', function (): void {
    $html = Blade::render('<x-airside::loading text="Loading flights" />');

    expect($html)->toContain('role="status"')->toContain('aria-label="Loading flights"')->toContain('Loading flights');
    expect(Blade::render('<x-airside::loading />'))->toContain('aria-label="Loading"');
});

it('gives unnamed drawers unique ids', function (): void {
    // Two unnamed drawers must not share a slide id, or one trigger opens both.
    $pair = Blade::render('<x-airside::drawer title="A" /><x-airside::drawer title="B" />');
    preg_match_all('/x-on:slide:(drawer-[A-Za-z0-9]+)-open\.window/', $pair, $ids);
    expect($ids[1])->toHaveCount(2)->and($ids[1][0])->not->toBe($ids[1][1]);
});

it('renders an inline confirm that forwards wire attributes to the confirm button', function (): void {
    $html = Blade::render('<x-airside::confirm prompt="Cancel this bid?" confirm-label="Yes, cancel it" keep-label="Keep" wire:click="remove(5)"><x-slot:trigger>Cancel</x-slot:trigger></x-airside::confirm>');

    expect($html)->toContain('Cancel this bid?')->toContain('Yes, cancel it')->toContain('Keep');
    expect($html)->toMatch('/<button[^>]*wire:click="remove\(5\)"[^>]*>\s*Yes, cancel it/s');

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
