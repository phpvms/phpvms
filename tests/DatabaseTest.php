<?php

namespace Tests;

use App\Services\DatabaseService;
use App\Support\Database;
use Symfony\Component\Yaml\Yaml;

class DatabaseTest extends TestCase
{
    /**
     * Make sure the seeder works correctly
     */
    public function testSeeder()
    {
        /** @var DatabaseService $dbSvc */
        $file = file_get_contents(base_path('tests/data/seed.yml'));
        $yml = Yaml::parse($file);

        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('default', $value);

        // Try updating the value now
        $yml['settings']['data'][0]['value'] = 'changed';

        // The value shouldn't change here
        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('default', $value);

        // Now the value should change
        $yml['settings']['ignore_on_update'] = [];
        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('changed', $value);
    }

    public function testSeederValueIgnoreValue()
    {
        /** @var DatabaseService $dbSvc */
        $file = file_get_contents(base_path('tests/data/seed.yml'));
        $yml = Yaml::parse($file);

        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('default', $value);

        // Try updating the value now
        $yml['settings']['data'][0]['value'] = 'changed';

        // The value shouldn't change here
        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('default', $value);
    }

    public function testSeederDontIgnoreValue()
    {
        /** @var DatabaseService $dbSvc */
        $file = file_get_contents(base_path('tests/data/seed.yml'));
        $yml = Yaml::parse($file);

        $yml['settings']['ignore_on_update'] = [];

        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('default', $value);

        // Change the value
        $yml['settings']['data'][0]['value'] = 'changed';

        // Now the value should change
        Database::seed_from_yaml($yml);
        $value = setting('test.setting');
        $this->assertEquals('changed', $value);
    }
}
