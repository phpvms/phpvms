<?php

use App\Contracts\Migration;
use App\Services\Installer\SeederService;
use Illuminate\Database\Schema\Blueprint;

return new class() extends Migration {
    private $seederSvc;

    public function __construct()
    {
        $this->seederSvc = app(SeederService::class);
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('id');
            $table->unsignedInteger('offset')->default(0);
            $table->unsignedInteger('order')->default(99);
            $table->string('key');
            $table->string('name');
            $table->string('value');
            $table->string('default')->nullable();
            $table->string('group')->nullable();
            $table->string('type')->nullable();
            $table->text('options')->nullable();
            $table->string('description')->nullable();

            $table->primary('id');
            $table->index('key');
            $table->timestamps();
        });

        $this->seederSvc->syncAllSettings();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
};
