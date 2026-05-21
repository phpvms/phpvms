<?php

declare(strict_types=1);

use App\Casts\FlightTypesCast;
use App\Enums\FlightType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

it('splits stored string into a FlightType collection on read', function (): void {
    $cast = new FlightTypesCast();

    $result = $cast->get(new stdClass(), 'route_types', 'C,F,J', []);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(3)
        ->and($result->contains(FlightType::CHARTER_PAX_ONLY))->toBeTrue()
        ->and($result->contains(FlightType::SCHED_CARGO))->toBeTrue()
        ->and($result->contains(FlightType::SCHED_PAX))->toBeTrue();
});

it('returns null when reading null', function (): void {
    $cast = new FlightTypesCast();

    $result = $cast->get(new stdClass(), 'route_types', null, []);

    expect($result)->toBeNull();
});

it('sorts and joins on write from collection', function (): void {
    $cast = new FlightTypesCast();

    $value = collect([
        FlightType::CHARTER_PAX_ONLY,
        FlightType::ADDITIONAL_CARGO,
        FlightType::SCHED_PAX,
    ]);

    $result = $cast->set(new stdClass(), 'route_types', $value, []);

    expect($result)->toBe('A,C,J');
});

it('sorts and joins on write from array', function (): void {
    $cast = new FlightTypesCast();

    $value = [
        FlightType::CHARTER_PAX_ONLY,
        FlightType::ADDITIONAL_CARGO,
        FlightType::SCHED_PAX,
    ];

    $result = $cast->set(new stdClass(), 'route_types', $value, []);

    expect($result)->toBe('A,C,J');
});

it('accepts comma-separated string on write and sorts it', function (): void {
    $cast = new FlightTypesCast();

    $result = $cast->set(new stdClass(), 'route_types', 'J,C,A', []);

    expect($result)->toBe('A,C,J');
});

it('collapses empty collection to null on write', function (): void {
    $cast = new FlightTypesCast();

    $result = $cast->set(new stdClass(), 'route_types', collect([]), []);

    expect($result)->toBeNull();
});

it('collapses empty array to null on write', function (): void {
    $cast = new FlightTypesCast();

    $result = $cast->set(new stdClass(), 'route_types', [], []);

    expect($result)->toBeNull();
});

it('returns null when writing null', function (): void {
    $cast = new FlightTypesCast();

    $result = $cast->set(new stdClass(), 'route_types', null, []);

    expect($result)->toBeNull();
});

it('round-trips through cast', function (): void {
    $cast = new FlightTypesCast();

    $original = collect([FlightType::SCHED_PAX, FlightType::CHARTER_PAX_ONLY]);
    $stored = $cast->set(new stdClass(), 'route_types', $original, []);
    $restored = $cast->get(new stdClass(), 'route_types', $stored, []);

    expect($stored)->toBe('C,J')
        ->and($restored)->toBeInstanceOf(Collection::class)
        ->and($restored)->toHaveCount(2)
        ->and($restored->contains(FlightType::SCHED_PAX))->toBeTrue()
        ->and($restored->contains(FlightType::CHARTER_PAX_ONLY))->toBeTrue();
});

it('removes duplicates on write', function (): void {
    $cast = new FlightTypesCast();

    $value = collect([
        FlightType::SCHED_PAX,
        FlightType::SCHED_PAX,
        FlightType::CHARTER_PAX_ONLY,
    ]);

    $result = $cast->set(new stdClass(), 'route_types', $value, []);

    expect($result)->toBe('C,J');
});

it('drops invalid characters and logs a warning identifying the dropped value', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->with(
            'FlightTypesCast: dropping unknown FlightType value',
            Mockery::on(fn (array $context): bool => isset($context['value']) && $context['value'] === 'U'),
        );

    $cast = new FlightTypesCast();

    $result = $cast->get(new stdClass(), 'route_types', 'J,U,F', []);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(2)
        ->and($result->pluck('value')->all())->toContain('F', 'J')
        ->and($result->pluck('value')->all())->not->toContain('U');
});
