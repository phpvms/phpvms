<?php

declare(strict_types=1);

use App\Models\Kvp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('widens kvp.value to text, keeps the rows, and is safe to run again', function (): void {
    $migration = require database_path('migrations/2026_11_19_000000_widen_kvp_value_to_text.php');
    Schema::table('kvp', fn (Blueprint $table) => $table->string('value')->change());
    Kvp::query()->create(['key' => 'kept', 'value' => 'yes']);

    $migration->up();
    $migration->up();

    expect(Schema::getColumnType('kvp', 'value'))->toBe('text')
        ->and(Kvp::query()->where('key', 'kept')->value('value'))->toBe('yes')
        ->and(collect(Schema::getColumns('kvp'))->firstWhere('name', 'value')['nullable'])->toBeFalse();
});
