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

it('pairs each tab button with its panel', function (): void {
    $html = renderSubtabs();

    preg_match_all('/role="tab"[^>]*aria-controls="([^"]+)"|aria-controls="([^"]+)"[^>]*role="tab"/', $html, $m);
    $controls = array_values(array_filter(array_merge($m[1], $m[2])));
    preg_match_all('/<[^>]*role="tabpanel"[^>]*>/', $html, $panels);
    $panelIds = [];
    foreach ($panels[0] as $tag) {
        if (preg_match('/\sid="([^"]+)"/', $tag, $id) === 1) {
            $panelIds[] = $id[1];
        }
    }

    expect($controls)->not->toBeEmpty();

    foreach ($controls as $target) {
        expect($panelIds)->toContain($target);
    }
});
