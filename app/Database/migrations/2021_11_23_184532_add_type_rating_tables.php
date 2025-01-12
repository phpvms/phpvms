<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        if (!Schema::hasTable('typeratings')) {
            Schema::create('typeratings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('type');
                $table->string('description')->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique('id');
                $table->unique('name');
            });
        }

        if (!Schema::hasTable('typerating_user')) {
            Schema::create('typerating_user', function (Blueprint $table) {
                $table->unsignedInteger('typerating_id');
                $table->unsignedInteger('user_id');

                $table->primary(['typerating_id', 'user_id']);
                $table->index(['typerating_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('typerating_subfleet')) {
            Schema::create('typerating_subfleet', function (Blueprint $table) {
                $table->unsignedInteger('typerating_id');
                $table->unsignedInteger('subfleet_id');

                $table->primary(['typerating_id', 'subfleet_id']);
                $table->index(['typerating_id', 'subfleet_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('typeratings');
        Schema::dropIfExists('typerating_user');
        Schema::dropIfExists('typerating_subfleet');
    }
};
