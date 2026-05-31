<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Kvp;
use App\Models\Rank;
use Illuminate\Database\Seeder;

class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Kvp::where('key', 'BaseDataSeeder')->exists()) {
            return;
        }

        Rank::firstOrCreate(
            ['id' => 1],
            [
                'name'                 => 'New Pilot',
                'hours'                => 0,
                'acars_base_pay_rate'  => 50,
                'manual_base_pay_rate' => 25,
            ],
        );

        Kvp::create([
            'key'   => 'BaseDataSeeder',
            'value' => now()->toDateTimeString(),
        ]);
    }
}
