<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('airline_id')->nullable();

            $table->string('name');
            $table->unsignedInteger('amount');
            $table->char('type');
            $table->boolean('charge_to_user')->nullable()->default(false);
            $table->boolean('multiplier')->nullable()->default(0);
            $table->boolean('active')->nullable()->default(true);

            // ref fields are expenses tied to some model object
            // EG, the airports has an internal expense for gate costs
            $table->string('ref_model')->nullable();
            $table->string('ref_model_id', 36)->nullable();

            $table->timestamps();

            $table->index(['ref_model', 'ref_model_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expenses');
    }
};
