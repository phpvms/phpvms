<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->unsignedDecimal('dow', 10, 2)->nullable()->change();
            $table->unsignedDecimal('zfw', 10, 2)->nullable()->change();
            $table->unsignedDecimal('mlw', 10, 2)->nullable()->change();
            $table->unsignedDecimal('mtow', 10, 2)->nullable()->change();
        });
    }
};
