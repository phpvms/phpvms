<?php

use App\Models\Airline;
use App\Services\YamlDatabaseService;
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
