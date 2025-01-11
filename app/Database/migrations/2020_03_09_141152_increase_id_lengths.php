<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Increase string ID lengths because of collisions
 */
return new class() extends Migration {
    public function up()
    {
        $tables = [
            'acars'               => ['id', 'pirep_id'],
            'bids'                => ['flight_id'],
            'flights'             => ['id'],
            'pireps'              => ['id', 'flight_id'],
            'flight_fare'         => ['flight_id'],
            'flight_field_values' => ['flight_id'],
            'flight_subfleet'     => ['flight_id'],
            'pirep_comments'      => ['pirep_id'],
            'pirep_fares'         => ['pirep_id'],
            'pirep_field_values'  => ['pirep_id'],
            'users'               => ['last_pirep_id'],
        ];

        foreach ($tables as $table_name => $columns) {
            Schema::table($table_name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column, 36)->change();
                }
            });
        }
    }
};
