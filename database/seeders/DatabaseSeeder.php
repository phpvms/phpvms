<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seeders = [
            RolesPermissionsSeeder::class,
            BaseDataSeeder::class,
            PassportSeeder::class,
        ];

        // Always insert the samples in the demo environment
        if (app()->environment('demo')) {
            $seeders[] = SampleDataSeeder::class;
        }

        $this->call($seeders);
    }
}
