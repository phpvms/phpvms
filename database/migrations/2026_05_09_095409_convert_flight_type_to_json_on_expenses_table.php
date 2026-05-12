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
    {// 1. Convert existing comma-separated strings to JSON arrays
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

        // 2. Officially change the column type to JSON
        // Note: You must have doctrine/dbal installed for this step on older Laravel versions
        Schema::table('expenses', function (Blueprint $table): void {
            $table->json('flight_type')->nullable()->change();
        });
    }
};
