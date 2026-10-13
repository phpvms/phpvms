<?php

declare(strict_types=1);

use App\Enums\FareType;
use App\Models\Fare;
use Database\Seeders\BaseDataSeeder;
use Database\Seeders\SampleDataSeeder;

test('a fresh install receives eleven passenger fare classes', function (): void {
    $this->seed(BaseDataSeeder::class);

    $expectedFares = [
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

    expect(Fare::query()->pluck('name', 'code')->all())->toBe($expectedFares);
    expect(Fare::query()->where('type', FareType::PASSENGER)->count())->toBe(11);

    Fare::query()->where('code', 'Y')->update(['price' => 123]);
    $this->seed(BaseDataSeeder::class);

    expect(Fare::query()->count())->toBe(11);
    expect(Fare::query()->where('code', 'Y')->value('price'))->toBe(123.0);
});

test('existing fare data is left untouched', function (): void {
    Fare::factory()->create(['code' => 'B', 'name' => 'Business', 'price' => 500]);

    $this->seed(BaseDataSeeder::class);

    expect(Fare::query()->count())->toBe(1);
    expect(Fare::query()->first()->only('code', 'name', 'price'))->toBe([
        'code'  => 'B',
        'name'  => 'Business',
        'price' => 500.0,
    ]);
});

test('soft deleted fare data prevents fresh install defaults', function (): void {
    Fare::factory()->create(['code' => 'Y'])->delete();

    $this->seed(BaseDataSeeder::class);

    expect(Fare::withTrashed()->count())->toBe(1);
});

test('demo data uses the seeded fare codes', function (): void {
    $this->seed(BaseDataSeeder::class);
    $this->seed(SampleDataSeeder::class);

    $this->assertDatabaseHas('fares', ['id' => 1, 'code' => 'Y', 'name' => 'Economy Flexi', 'price' => 100]);
    $this->assertDatabaseHas('fares', ['id' => 2, 'code' => 'J', 'name' => 'Business Flexi', 'price' => 500]);
    $this->assertDatabaseHas('fares', ['id' => 3, 'code' => 'F', 'name' => 'First', 'price' => 800]);
    $this->assertDatabaseHas('subfleet_fare', ['subfleet_id' => 1, 'fare_id' => 2]);
    $this->assertDatabaseHas('pirep_fares', ['id' => 2, 'fare_id' => 2, 'code' => 'J', 'name' => 'Business Flexi']);
    expect(Fare::query()->count())->toBe(11);
});
