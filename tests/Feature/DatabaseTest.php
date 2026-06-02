<?php

use App\Models\Airline;
use App\Models\Flight;
use App\Services\YamlDatabaseService;
use Database\Factories\JournalTransactionsFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

test('seeder', function (): void {
    $file = file_get_contents(base_path('tests/data/seed.yml'));
    $yml = Yaml::parse($file);

    $databaseSvc = app(YamlDatabaseService::class);

    $databaseSvc->seedFromYaml($yml);

    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Try updating the value now
    $yml['settings']['data'][0]['value'] = 'changed';

    // The value shouldn't change here
    $databaseSvc->seedFromYaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Now the value should change
    $yml['settings']['ignore_on_update'] = [];
    $databaseSvc->seedFromYaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('changed');
});

test('a failing seed row does not poison the surrounding transaction', function (): void {
    $databaseSvc = app(YamlDatabaseService::class);

    // Seed a row that is guaranteed to fail (unknown column). insertRow()
    // swallows the QueryException, but on PostgreSQL a failed statement aborts
    // the whole surrounding transaction. The write must be isolated in a
    // SAVEPOINT so later queries in the same (RefreshDatabase) transaction work.
    $databaseSvc->seedFromYaml([
        'airlines' => [
            'data' => [
                ['icao' => 'XAA', 'name' => 'Boom', 'this_column_does_not_exist' => 'boom'],
            ],
        ],
    ]);

    // Without the savepoint this throws "current transaction is aborted" on pgsql.
    $airline = Airline::factory()->create();

    expect($airline->exists)->toBeTrue();
});

test('seeding formats time and date columns to match their column type', function (): void {
    $flight = Flight::factory()->create();
    $transaction = JournalTransactionsFactory::new()->create();

    $databaseSvc = app(YamlDatabaseService::class);

    // A datetime input must be narrowed to the column's real type: `time`
    // columns store time-only and `date` columns date-only. Passing a full
    // datetime into a PostgreSQL `time`/`date` column otherwise errors.
    $databaseSvc->seedFromYaml([
        'flights' => [
            'ignore_if_exists' => false,
            'data'             => [
                [
                    'id'             => $flight->id,
                    'departure_time' => '2026-06-02 14:30:00',
                    'arrival_time'   => '2026-06-02 16:45:00',
                ],
            ],
        ],
        'journal_transactions' => [
            'ignore_if_exists' => false,
            'data'             => [
                ['id' => $transaction->id, 'post_date' => '2026-06-02 14:30:00'],
            ],
        ],
    ]);

    // Read raw values via the query builder to bypass model casts.
    expect(DB::table('flights')->where('id', $flight->id)->value('departure_time'))->toBe('14:30:00')
        ->and(DB::table('flights')->where('id', $flight->id)->value('arrival_time'))->toBe('16:45:00')
        ->and(DB::table('journal_transactions')->where('id', $transaction->id)->value('post_date'))->toBe('2026-06-02');
});

test('seedFromYaml surfaces query errors when ignore_errors is false', function (): void {
    $databaseSvc = app(YamlDatabaseService::class);

    // A bad row must propagate the QueryException in strict mode rather than
    // being silently swallowed. The write is still SAVEPOINT-isolated, so the
    // surrounding transaction stays usable.
    expect(fn () => $databaseSvc->seedFromYaml([
        'airlines' => [
            'data' => [
                ['icao' => 'XAA', 'name' => 'Boom', 'this_column_does_not_exist' => 'boom'],
            ],
        ],
    ], ignore_errors: false))->toThrow(QueryException::class);
});

test('seeder value ignore value', function (): void {
    $file = file_get_contents(base_path('tests/data/seed.yml'));
    $yml = Yaml::parse($file);

    $databaseSvc = app(YamlDatabaseService::class);

    $databaseSvc->seedFromYaml($yml);

    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Try updating the value now
    $yml['settings']['data'][0]['value'] = 'changed';

    // The value shouldn't change here
    $databaseSvc->seedFromYaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');
});

test('seeder dont ignore value', function (): void {
    $file = file_get_contents(base_path('tests/data/seed.yml'));
    $yml = Yaml::parse($file);

    $yml['settings']['ignore_on_update'] = [];

    $databaseSvc = app(YamlDatabaseService::class);

    $databaseSvc->seedFromYaml($yml);

    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Change the value
    $yml['settings']['data'][0]['value'] = 'changed';

    // Now the value should change
    $databaseSvc->seedFromYaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('changed');
});
