<?php

declare(strict_types=1);

use App\Features\Typeratings\Queries\TyperatingOptions;
use App\Models\Typerating;

beforeEach(function (): void {
    foreach (range(1, 60) as $i) {
        Typerating::create([
            'name'   => sprintf('Rating %02d', $i),
            'type'   => sprintf('R%02d', $i),
            'active' => 1,
        ]);
    }
});

it('pages through all type ratings', function (): void {
    $query = new TyperatingOptions();

    $first = $query->page('', 1, 50);
    $second = $query->page('', 2, 50);

    expect($first['options'])->toHaveCount(50)
        ->and($first['hasMore'])->toBeTrue()
        ->and($second['options'])->toHaveCount(10)
        ->and($second['hasMore'])->toBeFalse();
});

it('leaves out ratings excluded by the scope on every page', function (): void {
    $excluded = Typerating::where('name', 'Rating 05')->firstOrFail();
    $scope = fn ($q) => $q->whereKeyNot($excluded->id);
    $query = new TyperatingOptions();

    $first = $query->page('', 1, 50, $scope);
    $second = $query->page('', 2, 50, $scope);

    $values = array_merge(
        array_column($first['options'], 'value'),
        array_column($second['options'], 'value'),
    );

    expect($first['options'])->toHaveCount(50)
        ->and($second['options'])->toHaveCount(9)
        ->and($values)->not->toContain($excluded->id);
});
