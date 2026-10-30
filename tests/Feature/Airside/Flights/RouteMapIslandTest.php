<?php

declare(strict_types=1);

/**
 * The route map island contract other pages rely on (flight page, dashboard):
 * mounts on its selector, tolerates a missing destination, is idempotent, and
 * cleans up only what it mounted.
 */
beforeEach(function (): void {
    $this->source = file_get_contents(base_path('modules/phpvms-airside-theme/resources/js/islands/route-map.js'));
});

it('mounts on the route-map selector from the child JSON, on load and on navigation', function (): void {
    expect($this->source)
        ->toContain('data-airside-island="${NAME}"')
        ->toContain('const NAME = "route-map"')
        ->toContain('application/json')
        ->toContain('"DOMContentLoaded"')
        ->toContain('"livewire:navigated"')
        ->toContain('"livewire:navigating"')
        ->toContain('data.height');
});

it('skips elements it already mounted and cleans up only those', function (): void {
    expect($this->source)
        ->toContain('root.dataset.mounted === "true"')
        ->toContain('cleanups.set(root, mountIsland(root))')
        ->toContain('delete root.dataset.mounted');
});

it('draws only the origin when there is no destination', function (): void {
    expect($this->source)
        ->toContain('const to = hasPoint(data.to)')
        ->toContain('if (to) {')
        ->toContain('if (to) frame.append(status)');
});
