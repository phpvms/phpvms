<?php

declare(strict_types=1);

use App\Support\Permissions\Scope;

it('defaults the group to API and the label to null', function (): void {
    $scope = new Scope('read-flights');

    expect($scope->name)->toBe('read-flights')
        ->and($scope->group)->toBe('API')
        ->and($scope->label)->toBeNull();
});

it('rejects an empty name', function (): void {
    new Scope('  ');
})->throws(InvalidArgumentException::class);
