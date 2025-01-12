<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    public function up()
    {
        Schema::create('fares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50)->unique();
            $table->string('name', 50);
            $table->unsignedDecimal('price')->nullable()->default(0.00);
            $table->unsignedDecimal('cost')->nullable()->default(0.00);
            $table->unsignedInteger('capacity')->nullable()->default(0);
            $table->string('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fares');
    }
};
