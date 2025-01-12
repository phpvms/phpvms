<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::create('stats', function (Blueprint $table) {
            $table->string('id');
            $table->string('value');
            $table->unsignedInteger('order');
            $table->string('type')->nullable();
            $table->string('description')->nullable();

            $table->primary('id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stats');
    }
};
