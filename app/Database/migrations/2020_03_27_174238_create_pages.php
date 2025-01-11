<?php

use App\Contracts\Migration;
use App\Models\Enums\PageType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the pages
 * https://github.com/nabeelio/phpvms/issues/641
 */
return new class() extends Migration {
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug');
            $table->string('icon');
            $table->unsignedSmallInteger('type')->default(PageType::PAGE);
            $table->boolean('public');
            $table->boolean('enabled');
            $table->mediumText('body');
            $table->timestamps();

            $table->index('slug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pages');
    }
};
