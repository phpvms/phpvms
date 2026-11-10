<?php

declare(strict_types=1);

use Modules\PhpvmsAirsideTheme\Support\TimelineStack;

test('timed items order by offset, stack on a shared offset, and untimed items follow unstacked', function (): void {
    $items = [
        ['id' => 'arrival', 'at' => null],
        ['id' => 'fix', 'at' => 42],
        ['id' => 'departure', 'at' => 0],
        ['id' => 'notice', 'at' => 42],
        ['id' => 'alternate', 'at' => null],
        ['id' => 'early', 'at' => -15],
    ];

    $stacks = TimelineStack::stack($items, fn (array $item): ?int => $item['at']);

    expect(array_map(fn (array $stack): array => array_column($stack, 'id'), $stacks))->toBe([
        ['early'],
        ['departure'],
        ['fix', 'notice'],
        ['arrival'],
        ['alternate'],
    ]);
});

test('an offset label is signed hours and minutes', function (int $minutes, string $label): void {
    expect(TimelineStack::label($minutes))->toBe($label);
})->with([
    [0, '+0:00'],
    [42, '+0:42'],
    [125, '+2:05'],
    [-15, '-0:15'],
]);
