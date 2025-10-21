<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->renameColumn('ref_model', 'ref_model_type');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('ref_model', 'ref_model_type');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->renameColumn('ref_model', 'ref_model_type');
        });

        Schema::table('journal_transactions', function (Blueprint $table) {
            $table->renameColumn('ref_model', 'ref_model_type');
        });
    }
};
