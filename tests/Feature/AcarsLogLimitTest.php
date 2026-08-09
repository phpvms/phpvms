<?php

declare(strict_types=1);

use App\Http\Requests\Acars\EventRequest;
use App\Http\Requests\Acars\LogRequest;
use App\Http\Requests\Acars\PositionRequest;
use Illuminate\Support\Facades\Validator;

/**
 * The ACARS `log` column is TEXT, but the API caps incoming log/event strings
 * at 1000 chars so a client can't stuff arbitrarily large payloads into it.
 */
it('caps logs.*.log at 1000 chars', function (): void {
    $rules = new LogRequest()->rules();

    expect(Validator::make(['logs' => [['log' => str_repeat('x', 1000)]]], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['logs' => [['log' => str_repeat('x', 1001)]]], $rules)->fails())->toBeTrue();
});

it('caps events.*.event at 1000 chars', function (): void {
    $rules = new EventRequest()->rules();

    expect(Validator::make(['events' => [['event' => str_repeat('x', 1000)]]], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['events' => [['event' => str_repeat('x', 1001)]]], $rules)->fails())->toBeTrue();
});

it('caps positions.*.log at 1000 chars', function (): void {
    $rules = new PositionRequest()->rules();
    $base = ['lat' => 1.0, 'lon' => 1.0];

    expect(Validator::make(['positions' => [$base + ['log' => str_repeat('x', 1000)]]], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['positions' => [$base + ['log' => str_repeat('x', 1001)]]], $rules)->fails())->toBeTrue();
});
