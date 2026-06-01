<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('airlines', function (Blueprint $table): void {
            $table->string('icao', 12)->change();
            $table->string('iata', 12)->nullable()->change();
            $table->string('country', 3)->nullable()->change();
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->string('subject_id', 128)->nullable()->change();
            $table->string('batch_uuid', 36)->nullable()->change();
        });

        Schema::table('aircraft', function (Blueprint $table): void {
            $table->string('icao', 12)->nullable()->change();
            $table->string('iata', 12)->nullable()->change();
        });

        Schema::table('pireps', function (Blueprint $table): void {
            $table->decimal('zfw', 12, 2)->unsigned()->nullable()->change();
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->string('subject', 200)->change();
        });

        Schema::table('flights', function (Blueprint $table): void {
            $table->string('callsign', 10)->nullable()->change();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->increments('id')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_field_values ALTER COLUMN user_id TYPE bigint USING user_id::bigint');
        } else {
            Schema::table('user_field_values', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_field_values ALTER COLUMN user_id TYPE varchar(16) USING user_id::varchar');
        } else {
            Schema::table('user_field_values', function (Blueprint $table): void {
                $table->string('user_id', 16)->change();
            });
        }

        Schema::table('events', function (Blueprint $table): void {
            $table->integer('id')->primary()->change();
        });

        Schema::table('flights', function (Blueprint $table): void {
            $table->string('callsign', 4)->nullable()->change();
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->string('subject', 191)->change();
        });

        Schema::table('pireps', function (Blueprint $table): void {
            $table->decimal('zfw', 8, 2)->unsigned()->nullable()->change();
        });

        Schema::table('aircraft', function (Blueprint $table): void {
            $table->string('icao', 4)->nullable()->change();
            $table->string('iata', 4)->nullable()->change();
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->char('subject_id', 36)->nullable()->change();
            $table->char('batch_uuid', 36)->nullable()->change();
        });

        Schema::table('airlines', function (Blueprint $table): void {
            $table->string('icao', 5)->change();
            $table->string('iata', 5)->nullable()->change();
            $table->string('country', 2)->nullable()->change();
        });
    }
};
