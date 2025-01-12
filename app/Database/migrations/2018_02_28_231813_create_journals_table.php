<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ledger_id')->nullable();
            $table->unsignedTinyInteger('type')->default(0);
            $table->bigInteger('balance')->default(0);
            $table->string('currency', 5);
            $table->nullableMorphs('morphed');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('journals');
    }
};
