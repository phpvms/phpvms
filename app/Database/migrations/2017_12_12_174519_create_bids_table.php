<?php

use App\Contracts\Migration;
use App\Contracts\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('flight_id', Model::ID_MAX_LENGTH);
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'flight_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bids');
    }
};
