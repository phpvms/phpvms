<?php

use App\Contracts\Migration;
use App\Models\Enums\PirepState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change the PIREP state column to be a TINYINT
 */
return new class() extends Migration {
    public function up()
    {
        // Migrate the old rejected state
        DB::table('pireps')
            ->where(['state' => -1])
            ->update(['state' => PirepState::REJECTED]);

        // Change the column type to an unsigned small int (tinyint not supported on all)
        Schema::table('pireps', function (Blueprint $table) {
            $table->unsignedSmallInteger('state')->change();
        });
    }
};
