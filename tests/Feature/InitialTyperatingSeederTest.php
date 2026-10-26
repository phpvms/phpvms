<?php

declare(strict_types=1);

use App\Models\Typerating;
use Database\Seeders\BaseDataSeeder;

test('a fresh install receives common airliner type ratings', function (): void {
    $this->seed(BaseDataSeeder::class);

    expect(Typerating::query()->count())->toBe(25);
    $this->assertDatabaseHas('typeratings', ['type' => 'BD500', 'name' => 'Airbus A220']);
    $this->assertDatabaseHas('typeratings', ['type' => 'A-320', 'name' => 'Airbus A320 family']);
    $this->assertDatabaseHas('typeratings', ['type' => 'B-747-4', 'name' => 'Boeing 747-400 / 747-8']);
    $this->assertDatabaseHas('typeratings', ['type' => 'ERJ-190', 'name' => 'Embraer E190 / E195']);

    Typerating::query()->where('type', 'A-320')->update(['active' => false]);
    $this->seed(BaseDataSeeder::class);

    expect(Typerating::query()->count())->toBe(25);
    expect((bool) Typerating::query()->where('type', 'A-320')->value('active'))->toBeFalse();
});

test('existing type ratings are left untouched', function (): void {
    Typerating::query()->create(['name' => 'Custom fleet rating', 'type' => 'CUSTOM']);

    $this->seed(BaseDataSeeder::class);

    expect(Typerating::query()->pluck('type')->all())->toBe(['CUSTOM']);
});
