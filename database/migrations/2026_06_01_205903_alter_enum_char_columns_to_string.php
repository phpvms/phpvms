<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('ALTER TABLE expenses ALTER COLUMN type TYPE varchar(1) USING type::varchar(1)');
            DB::statement('ALTER TABLE aircraft ALTER COLUMN status TYPE varchar(1) USING status::varchar(1)');
            DB::statement('ALTER TABLE flights ALTER COLUMN flight_type TYPE varchar(1) USING flight_type::varchar(1)');
            DB::statement('ALTER TABLE pireps ALTER COLUMN flight_type TYPE varchar(1) USING flight_type::varchar(1)');
            DB::statement('ALTER TABLE pireps ALTER COLUMN status TYPE varchar(3) USING status::varchar(3)');
        } else {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->string('type', 1)->change();
            });

            Schema::table('aircraft', function (Blueprint $table): void {
                $table->string('status', 1)->default('A')->change();
            });

            Schema::table('flights', function (Blueprint $table): void {
                $table->string('flight_type', 1)->default('J')->change();
            });

            Schema::table('pireps', function (Blueprint $table): void {
                $table->string('flight_type', 1)->default('J')->change();
                $table->string('status', 3)->default('SCH')->change();
            });
        }
    }

    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('ALTER TABLE pireps ALTER COLUMN status TYPE character(3) USING status::character(3)');
            DB::statement('ALTER TABLE pireps ALTER COLUMN flight_type TYPE character(1) USING flight_type::character(1)');
            DB::statement('ALTER TABLE flights ALTER COLUMN flight_type TYPE character(1) USING flight_type::character(1)');
            DB::statement('ALTER TABLE aircraft ALTER COLUMN status TYPE character(1) USING status::character(1)');
            DB::statement('ALTER TABLE expenses ALTER COLUMN type TYPE character(1) USING type::character(1)');
        } else {
            Schema::table('pireps', function (Blueprint $table): void {
                $table->char('status', 3)->default('SCH')->change();
                $table->char('flight_type', 1)->default('J')->change();
            });

            Schema::table('flights', function (Blueprint $table): void {
                $table->char('flight_type', 1)->default('J')->change();
            });

            Schema::table('aircraft', function (Blueprint $table): void {
                $table->char('status', 1)->default('A')->change();
            });

            Schema::table('expenses', function (Blueprint $table): void {
                $table->char('type')->change();
            });
        }
    }
};
