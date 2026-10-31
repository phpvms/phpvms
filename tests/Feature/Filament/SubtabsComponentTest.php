<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/**
 * The admin subtabs component is the PIREP view's tab pattern made reusable.
 */
function renderSubtabs(): string
{
    return Blade::render(<<<'BLADE'
        <x-admin.subtabs default="first" label="Demo sections">
            <x-slot:tabs>
                <x-admin.subtabs.tab key="first" icon="phosphor-paint-brush-light">First</x-admin.subtabs.tab>
                <x-admin.subtabs.tab key="second" :badge="3">Second</x-admin.subtabs.tab>
            </x-slot:tabs>

            <x-admin.subtabs.panel key="first">One</x-admin.subtabs.panel>
            <x-admin.subtabs.panel key="second">Two</x-admin.subtabs.panel>
        </x-admin.subtabs>
        BLADE);
}

it('renders a tablist with the initial active tab', function (): void {
    $html = renderSubtabs();

    expect($html)
        ->toContain('role="tablist"')
        ->toContain('aria-label="Demo sections"')
        ->toContain("x-data=\"{ activeTab: 'first' }\"");
});

it('pairs each tab button with its panel', function (): void {
    $html = renderSubtabs();

    expect(substr_count($html, 'role="tab"'))->toBe(2)
        ->and(substr_count($html, 'role="tabpanel"'))->toBe(2);

    foreach (['first', 'second'] as $key) {
        expect($html)
            ->toContain('aria-controls="tab-'.$key.'"')
            ->toContain('id="tab-'.$key.'"');
    }
});

it('renders the badge in an em and the icon as an svg', function (): void {
    $html = renderSubtabs();

    expect($html)
        ->toMatch('/<em>\s*3\s*<\/em>/')
        ->toContain('<svg');
});
