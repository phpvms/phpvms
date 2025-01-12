<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('user_oauth_tokens', function (Blueprint $table) {
                $foreignKeys = Schema::getForeignKeys('user_oauth_tokens');

                foreach ($foreignKeys as $foreignKey) {
                    if (in_array('user_id', $foreignKey['columns'], true)) {
                        $table->dropForeign(['user_id']);
                        break;
                    }
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
