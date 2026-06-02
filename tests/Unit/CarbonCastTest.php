<?php

use App\Casts\CarbonCast;
use App\Models\Pirep;
use Carbon\Carbon;

test('set converts Carbon instance to datetime string', function (): void {
    $cast = new CarbonCast();
    $carbon = Carbon::parse('2026-06-01T01:42:49.595862Z');

    $result = $cast->set(new Pirep(), 'block_on_time', $carbon, []);

    expect($result)->toEqual('2026-06-01 01:42:49');
});

test('set converts ISO 8601 Zulu string to datetime string', function (): void {
    $cast = new CarbonCast();
    $value = '2026-06-01T01:42:49.595862Z';

    $result = $cast->set(new Pirep(), 'block_on_time', $value, []);

    expect($result)->toEqual('2026-06-01 01:42:49');
});

test('set converts ISO 8601 string to datetime string', function (): void {
    $cast = new CarbonCast();
    $value = '2026-06-01T01:42:49+00:00';

    $result = $cast->set(new Pirep(), 'block_on_time', $value, []);

    expect($result)->toEqual('2026-06-01 01:42:49');
});

test('set converts standard datetime string to datetime string', function (): void {
    $cast = new CarbonCast();
    $value = '2026-06-01 01:42:49';

    $result = $cast->set(new Pirep(), 'block_on_time', $value, []);

    expect($result)->toEqual('2026-06-01 01:42:49');
});

test('set passes through null unchanged', function (): void {
    $cast = new CarbonCast();

    $result = $cast->set(new Pirep(), 'block_on_time', null, []);

    expect($result)->toBeNull();
});

test('get converts string to Carbon instance', function (): void {
    $cast = new CarbonCast();

    $result = $cast->get(new Pirep(), 'block_on_time', '2026-06-01 01:42:49', []);

    expect($result)->toBeInstanceOf(Carbon::class);
    expect($result->toDateTimeString())->toEqual('2026-06-01 01:42:49');
});
