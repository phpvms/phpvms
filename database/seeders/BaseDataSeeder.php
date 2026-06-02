<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Kvp;
use App\Models\Rank;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->createInitialRanks();
    }

    /**
     * Check if a specific key has been seeded
     */
    private function isSeeded(string $key): bool
    {
        $key .= '_seeded';

        return Kvp::where('key', $key)->exists();
    }

    /**
     * Mark a specific key as seeded
     */
    private function setSeeded(string $key): void
    {
        $key .= '_seeded';
        Kvp::updateOrCreate(['key' => $key], ['value' => Carbon::now('UTC')->toDateTimeString()]);
    }

    /**
     * Create initial ranks if they don't exist
     */
    private function createInitialRanks(): void
    {
        // Seems like they added ranks, so we don't need to do anything
        // This is mainly a check for updates
        if ($this->isSeeded('ranks') || Rank::count() > 1) {
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

        $this->setSeeded('ranks');
    }
}
