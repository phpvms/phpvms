<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FareType;
use App\Models\Fare;
use App\Models\Kvp;
use App\Models\Rank;
use App\Models\Typerating;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->createInitialRanks();
        $this->createInitialFares();
        $this->createInitialTypeRatings();
    }

    private function createInitialTypeRatings(): void
    {
        if ($this->isSeeded('typeratings') || Typerating::query()->exists()) {
            return;
        }

        $typeRatings = [
            'BD500'   => 'Airbus A220',
            'A-300'   => 'Airbus A300',
            'A-310'   => 'Airbus A310 / A300-600',
            'A-320'   => 'Airbus A320 family',
            'A-330'   => 'Airbus A330',
            'A-340'   => 'Airbus A340',
            'A-350'   => 'Airbus A350',
            'A-380'   => 'Airbus A380',
            'ATR-42'  => 'ATR 42',
            'ATR-72'  => 'ATR 72',
            'BAE-146' => 'BAe 146',
            'B-737'   => 'Boeing 737',
            'B-747'   => 'Boeing 747 classic',
            'B-747-4' => 'Boeing 747-400 / 747-8',
            'B-757'   => 'Boeing 757',
            'B-767'   => 'Boeing 767',
            'B-777'   => 'Boeing 777',
            'B-787'   => 'Boeing 787',
            'CL-65'   => 'Bombardier CRJ',
            'DHC-8'   => 'De Havilland Dash 8',
            'EMB-145' => 'Embraer ERJ 135 / 145',
            'ERJ-170' => 'Embraer E170 / E175',
            'ERJ-190' => 'Embraer E190 / E195',
            'FK-100'  => 'Fokker 70 / 100',
            'SF-340'  => 'Saab 340',
        ];

        foreach ($typeRatings as $type => $name) {
            Typerating::query()->create(['type' => $type, 'name' => $name]);
        }

        $this->setSeeded('typeratings');
    }

    private function createInitialFares(): void
    {
        if ($this->isSeeded('fares') || Fare::withTrashed()->exists()) {
            return;
        }

        /** The first three codes match the fare IDs in the demo fixture. */
        $fares = [
            'Y' => 'Economy Flexi',
            'J' => 'Business Flexi',
            'F' => 'First',
            'V' => 'Economy Lite',
            'Q' => 'Economy Value',
            'M' => 'Economy Standard',
            'R' => 'Premium Economy Lite',
            'P' => 'Premium Economy Standard',
            'S' => 'Premium Economy Flexi',
            'D' => 'Business Lite',
            'U' => 'Business Standard',
        ];

        foreach ($fares as $code => $name) {
            Fare::query()->create([
                'code' => $code,
                'name' => $name,
                'type' => FareType::PASSENGER,
            ]);
        }

        $this->setSeeded('fares');
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
