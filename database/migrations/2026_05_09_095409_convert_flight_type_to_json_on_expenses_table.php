<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Widen the column first so the JSON representation fits during conversion.
        // The original VARCHAR(50) is too small for the encoded array once an expense
        // has many flight types (e.g. all of them), which would truncate/fail the update.
        Schema::table('expenses', function (Blueprint $table): void {
            $table->text('flight_type')->nullable()->change();
        });

        // 2. Convert existing comma-separated strings to JSON arrays
        DB::table('expenses')->orderBy('id')->chunk(100, function ($expenses): void {
            foreach ($expenses as $expense) {
                // Only process if it has a value and isn't already a JSON array
                if ($expense->flight_type && !str_starts_with(trim((string) $expense->flight_type), '[')) {

                    // Split by comma and trim whitespace
                    $asArray = array_map(trim(...), explode(',', (string) $expense->flight_type));

                    // Remove any empty values that might have snuck in
                    $asArray = array_filter($asArray);

                    DB::table('expenses')
                        ->where('id', $expense->id)
                        ->update([
                            'flight_type' => json_encode(array_values($asArray)),
                        ]);
                }
            }
        });

        // 3. Officially change the column type to JSON
        // Note: You must have doctrine/dbal installed for this step on older Laravel versions

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expenses ALTER COLUMN flight_type TYPE JSON USING flight_type::json');
        } else {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->json('flight_type')->nullable()->change();
            });
        }
    }
};
