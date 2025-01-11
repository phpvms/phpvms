<?php

use App\Contracts\Migration;
use App\Contracts\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Create the files table. Acts as a morphable
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->string('id', Model::ID_MAX_LENGTH);
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('disk')->nullable();
            $table->string('path');
            $table->boolean('public')->default(true);
            $table->unsignedInteger('download_count')->default(0);
            $table->string('ref_model', 50)->nullable();
            $table->string('ref_model_id', 36)->nullable();
            $table->timestamps();

            $table->primary('id');
            $table->index(['ref_model', 'ref_model_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
};
