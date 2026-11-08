<?php

declare(strict_types=1);

use App\Features\AddonSettings\Setting;

it('emits the type each factory represents', function (): void {
    expect(Setting::text('a', 'A')->type)->toBe('text')
        ->and(Setting::int('a', 'A')->type)->toBe('int')
        ->and(Setting::float('a', 'A')->type)->toBe('float')
        ->and(Setting::bool('a', 'A')->type)->toBe('bool')
        ->and(Setting::date('a', 'A')->type)->toBe('date')
        ->and(Setting::select('a', 'A', ['x' => 'X'], 'x')->type)->toBe('select')
        ->and(Setting::hidden('a')->type)->toBe('hidden');
});

it('rejects a select default that is not an option key', function (): void {
    Setting::select('a', 'A', ['x' => 'X'], 'X');
})->throws(InvalidArgumentException::class);

it('rejects a select with empty options', function (): void {
    Setting::select('a', 'A', [], 'x');
})->throws(InvalidArgumentException::class);

it('serialises options as the key=label list the admin select reads', function (): void {
    expect(Setting::select('a', 'A', ['x' => 'Ex', 'y' => 'Why'], 'y')->toArray()['options'])->toBe('x=Ex,y=Why');
});

it('returns exactly the keys syncEntry reads', function (): void {
    expect(array_keys(Setting::text('a', 'A')->toArray()))
        ->toBe(['key', 'name', 'default', 'group', 'order', 'type', 'options', 'description']);
});
