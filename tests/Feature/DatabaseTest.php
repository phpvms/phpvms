<?php

use App\Services\DatabaseService;
use App\Support\Database;
use Symfony\Component\Yaml\Yaml;

test('seeder', function () {
    /** @var DatabaseService $dbSvc */
    $file = file_get_contents(base_path('tests/data/seed.yml'));
    $yml = Yaml::parse($file);

    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Try updating the value now
    $yml['settings']['data'][0]['value'] = 'changed';

    // The value shouldn't change here
    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Now the value should change
    $yml['settings']['ignore_on_update'] = [];
    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('changed');
});

test('seeder value ignore value', function () {
    /** @var DatabaseService $dbSvc */
    $file = file_get_contents(base_path('tests/data/seed.yml'));
    $yml = Yaml::parse($file);

    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Try updating the value now
    $yml['settings']['data'][0]['value'] = 'changed';

    // The value shouldn't change here
    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');
});

test('seeder dont ignore value', function () {
    /** @var DatabaseService $dbSvc */
    $file = file_get_contents(base_path('tests/data/seed.yml'));
    $yml = Yaml::parse($file);

    $yml['settings']['ignore_on_update'] = [];

    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('default');

    // Change the value
    $yml['settings']['data'][0]['value'] = 'changed';

    // Now the value should change
    Database::seed_from_yaml($yml);
    $value = setting('test.setting');
    expect($value)->toEqual('changed');
});
