<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('bids')
            ->select(['user_id', 'flight_id'])
            ->groupBy('user_id', 'flight_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(sprintf(
                'Cannot add bids_user_flight_unique: duplicate bids exist for user %s and flight %s.',
                $duplicate->user_id,
                $duplicate->flight_id,
            ));
        }

        Schema::table('bids', function (Blueprint $table): void {
            $table->unique(['user_id', 'flight_id'], 'bids_user_flight_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table): void {
            $table->dropUnique('bids_user_flight_unique');
        });
    }
};
