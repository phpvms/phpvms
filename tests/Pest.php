<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Never let addon lifecycle tests write the real bootstrap/cache/addons.php.
        // A unique path per test keeps both parallel runs and sequential tests
        // isolated (a shared path would leak boot-cache state between tests).
        config(['addons.paths.boot_cache' => sys_get_temp_dir().'/phpvms-addons-boot-'.uniqid('', true).'.php']);

        $this->seed(SettingsSeeder::class);
    })
    ->afterEach(function (): void {
        $path = config('addons.paths.boot_cache');

        if (is_string($path) && str_starts_with($path, sys_get_temp_dir()) && file_exists($path)) {
            @unlink($path);
        }
    })
    ->in('Unit', 'Feature', 'Arch', '../resources/views');
