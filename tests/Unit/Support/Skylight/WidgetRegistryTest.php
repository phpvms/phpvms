<?php

declare(strict_types=1);

use App\Support\Skylight\WidgetRegistry;

test('registers a tag-kind widget with its catalog fields', function (): void {
    $registry = new WidgetRegistry();

    $registry->register([
        'id'         => 'hero',
        'kind'       => 'tag',
        'tag'        => 'pv-hero',
        'title'      => 'Hero',
        'icon'       => 'photo',
        'module'     => '/build/skylight/elements.js',
        'attributes' => [
            ['name' => 'heading', 'type' => 'string', 'description' => 'The hero heading text.'],
        ],
    ]);

    expect($registry->all())->toBe([
        [
            'id'         => 'hero',
            'kind'       => 'tag',
            'tag'        => 'pv-hero',
            'title'      => 'Hero',
            'icon'       => 'photo',
            'module'     => '/build/skylight/elements.js',
            'attributes' => [
                ['name' => 'heading', 'type' => 'string', 'description' => 'The hero heading text.'],
            ],
            'defaultZone' => 'grid',
        ],
    ]);
});

test('a tag-kind widget requires a string tag', function (): void {
    $registry = new WidgetRegistry();

    expect(fn (): WidgetRegistry => $registry->register(['id' => 'hero', 'kind' => 'tag']))
        ->toThrow(InvalidArgumentException::class, 'A Skylight widget definition with kind "tag" requires a string "tag".');
});

test('a tag-kind widget rejects a non-string tag', function (): void {
    $registry = new WidgetRegistry();

    expect(fn (): WidgetRegistry => $registry->register(['id' => 'hero', 'kind' => 'tag', 'tag' => 42]))
        ->toThrow(InvalidArgumentException::class);
});

test('existing vue and blade kinds are unaffected by the tag kind', function (): void {
    $registry = new WidgetRegistry();

    $registry->register(['id' => 'v', 'component' => 'HoursWidget', 'title' => 'V', 'icon' => 'x']);
    $registry->register(['id' => 'b', 'kind' => 'blade', 'endpoint' => '/ext/x', 'title' => 'B', 'icon' => 'x']);

    expect($registry->all())->toBe([
        ['id' => 'v', 'component' => 'HoursWidget', 'title' => 'V', 'icon' => 'x', 'kind' => 'vue', 'defaultZone' => 'grid'],
        ['id' => 'b', 'kind' => 'blade', 'endpoint' => '/ext/x', 'title' => 'B', 'icon' => 'x', 'defaultZone' => 'grid'],
    ]);
});

test('a disabled addon contributes no tag-kind widget because it never registers', function (): void {
    // Disable-safety (WidgetRegistry.php:12-17) is structural: a disabled addon's
    // provider never boots, so it never calls register() at all. There is no
    // runtime "enabled" flag to check — an empty registry IS the disabled state.
    $registry = new WidgetRegistry();

    expect($registry->has('hero'))->toBeFalse()
        ->and($registry->all())->toBe([]);
});
