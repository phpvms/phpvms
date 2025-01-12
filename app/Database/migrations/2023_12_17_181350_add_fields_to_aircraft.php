<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        if (!Schema::hasColumns('aircraft', ['selcal', 'dow', 'mlw', 'simbrief_type'])) {
            Schema::table('aircraft', function (Blueprint $table) {
                $table->string('selcal', 5)->nullable()->after('hex_code');
                $table->unsignedDecimal('dow', 8, 2)->nullable()->after('selcal');
                $table->unsignedDecimal('mlw', 8, 2)->nullable()->after('mtow');
                $table->string('simbrief_type', 25)->nullable()->after('zfw');
            });
        }
    }
};
