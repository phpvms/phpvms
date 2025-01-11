<?php

use App\Contracts\Migration;
use App\Models\Enums\FareType;
use App\Models\PirepFare;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::table('fares', function (Blueprint $table) {
            $table->softDeletes();
        });

        if (!Schema::hasColumns('pirep_fares', ['code', 'name'])) {
            Schema::table('pirep_fares', function (Blueprint $table) {
                $table->unsignedBigInteger('fare_id')->nullable()->change();
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->unsignedDecimal('price')->nullable()->default(0.00);
                $table->unsignedDecimal('cost')->nullable()->default(0.00);
                $table->unsignedInteger('capacity')->nullable()->default(0);
                $table->unsignedTinyInteger('type')
                    ->default(FareType::PASSENGER)
                    ->nullable()
                    ->after('capacity');
                $table->softDeletes();
            });
        }

        /**
         * Update all of the existing PIREP fares to include the existing fare info
         */
        $all_fares = PirepFare::with('fare')->get();
        /** @var PirepFare $fare */
        foreach ($all_fares as $fare) {
            if (empty($fare->fare)) {
                continue;
            }

            $fare->code = $fare->fare->code;
            $fare->name = $fare->fare->name;
            $fare->capacity = $fare->fare->capacity;
            $fare->price = $fare->fare->price;
            $fare->cost = $fare->fare->cost;
            $fare->type = $fare->fare->type;
            $fare->fare_id = null;

            $fare->save();
        }
    }
};
