<?php

declare(strict_types=1);

use App\Enums\SimType;
use App\Http\Requests\Acars\PrefileRequest;
use Illuminate\Support\Facades\Validator;

/**
 * The prefile `sim_type` must validate against the SimType enum, not just
 * `integer` — Pirep casts the column to SimType, so unsupported values (the
 * deliberate gaps at 3 and 8, or anything out of range) would break on save.
 */
function simTypeRule(): array
{
    return ['sim_type' => new PrefileRequest()->rules()['sim_type']];
}

it('accepts valid SimType values', function (int $value): void {
    expect(Validator::make(['sim_type' => $value], simTypeRule())->passes())->toBeTrue();
})->with(array_map(fn (SimType $c): int => $c->value, SimType::cases()));

it('accepts a missing or null sim_type', function (): void {
    expect(Validator::make([], simTypeRule())->passes())->toBeTrue()
        ->and(Validator::make(['sim_type' => null], simTypeRule())->passes())->toBeTrue();
});

it('rejects integers outside the SimType enum', function (int $value): void {
    expect(Validator::make(['sim_type' => $value], simTypeRule())->fails())->toBeTrue();
})->with([3, 8, 99, -1]);
