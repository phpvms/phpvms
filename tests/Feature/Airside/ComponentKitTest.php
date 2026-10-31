<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

require_once __DIR__.'/Support.php';

/**
 * The x-vms:: anonymous components (page, ui, flights) the pages are built from.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

it('renders a page with a header, links slot and region label', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-vms::page name="logbook" label="Logbook">
            <x-vms::page.header headline="Logbook" title="My PIREPs" description="Most recent first.">
                <x-slot:links><span>12 total</span></x-slot:links>
            </x-vms::page.header>
            <p>body</p>
        </x-vms::page>
        BLADE);

    expect($html)
        ->toContain('aria-label="Logbook"')
        ->toContain('My PIREPs')
        ->toContain('Most recent first.')
        ->toContain('12 total')
        ->toContain('body');
});

it('renders an empty state with optional icon and actions', function (): void {
    $html = Blade::render('<x-vms::ui.empty icon="map" title="Nothing" description="Nope"><x-slot:actions><a>Go</a></x-slot:actions></x-vms::ui.empty>');

    expect($html)->toContain('Nothing')->toContain('Nope')->toContain('<svg')->toContain('<a>Go</a>');
    expect(Blade::render('<x-vms::ui.empty title="Bare" />'))->not->toContain('<svg');
});

it('renders the stats grid with dash for null values', function (): void {
    $html = Blade::render('<x-vms::ui.stats :items="$items" :columns="2" />', [
        'items' => [['label' => 'ETE', 'value' => '7:12'], ['label' => 'Expires', 'value' => null]],
    ]);

    expect($html)->toContain('ETE')->toContain('7:12')->toContain('Expires')->toContain('—');
});

it('renders flight ident with initials fallback, VA default and logo', function (): void {
    $initials = Blade::render('<x-vms::flights.ident callsign="PVA101" dpt="KJFK" arr="EGLL" airline="phpvms air" aircraft="N1 · B738" />');
    expect($initials)->toContain('PH')->toContain('PVA101')->toContain('KJFK')->toContain('EGLL')->toContain('N1 · B738')->not->toContain('<img');

    expect(Blade::render('<x-vms::flights.ident callsign="X1" />'))->toContain('VA')->toContain('—');

    $logo = Blade::render('<x-vms::flights.ident callsign="X1" airline="Acme" logo="/a.png" href="/flights/1" stacked size="lg" />');
    expect($logo)->toContain('<img')->toContain('/a.png')->toContain('<a ')->toContain('href="/flights/1"');
});

it('gives unnamed drawers unique ids', function (): void {
    // Two unnamed drawers must not share a slide id, or one trigger opens both.
    $pair = Blade::render('<x-vms::ui.drawer title="A" /><x-vms::ui.drawer title="B" />');
    preg_match_all('/x-on:slide:(drawer-[A-Za-z0-9]+)-open\.window/', $pair, $ids);
    expect($ids[1])->toHaveCount(2)->and($ids[1][0])->not->toBe($ids[1][1]);
});

it('renders an icon and nothing for an unknown name', function (): void {
    expect(Blade::render('<x-vms::ui.icon name="map" />'))->toContain('<svg');
    expect(trim(Blade::render('<x-vms::ui.icon name="no-such-icon-xyz" />')))->toBe('');
});
