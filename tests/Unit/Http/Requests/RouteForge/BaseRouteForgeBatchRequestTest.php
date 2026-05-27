<?php

declare(strict_types=1);

use App\Http\Requests\RouteForge\LintRequest;
use Illuminate\Support\Facades\DB;

it('executes zero Eloquent queries while materializing rules()', function (): void {
    $request = LintRequest::create('/admin/route-forge/api/lint', 'POST', [
        'airline_id'   => 1,
        'subfleet_ids' => [1, 2, 3],
        'origins'      => ['KSFO'],
        'destinations' => ['KLAX'],
        'bundle'       => ['existing_bundle_id' => null, 'name' => 'X', 'enabled' => true],
        'rows'         => [['airline_id' => 1]],
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $rules = $request->rules();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
    expect($rules)->toHaveKey('subfleet_ids.*');
});
