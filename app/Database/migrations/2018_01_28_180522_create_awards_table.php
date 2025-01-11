<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Awards\Awards\PilotFlightAwards;

return new class() extends Migration {
    public function up()
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();

            // ref fields are expenses tied to some model object
            // EG, the airports has an internal expense for gate costs
            $table->string('ref_model')->nullable();
            $table->text('ref_model_params')->nullable();

            $table->timestamps();

            $table->index(['ref_model']);
        });

        Schema::create('user_awards', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('award_id');
            $table->timestamps();

            $table->index(['user_id', 'award_id']);
        });

        /**
         * Add a default, sample award
         */
        $award = [
            'name'             => 'Pilot 50 flights',
            'description'      => 'When a pilot has 50 flights, give this award',
            'ref_model'        => PilotFlightAwards::class,
            'ref_model_params' => 50,
        ];

        $this->addAward($award);
    }

    public function down()
    {
        Schema::dropIfExists('awards');
        Schema::dropIfExists('user_awards');
    }
};
