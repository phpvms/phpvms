<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up()
    {
        Schema::create('journal_transactions', function (Blueprint $table) {
            $table->char('id', 36)->unique();
            $table->string('transaction_group')->nullable();
            $table->integer('journal_id');
            $table->unsignedBigInteger('credit')->nullable();
            $table->unsignedBigInteger('debit')->nullable();
            $table->char('currency', 5);
            $table->text('memo')->nullable();
            $table->string('tags')->nullable();
            $table->string('ref_model', 50)->nullable();
            $table->string('ref_model_id', 36)->nullable();
            $table->timestamps();
            $table->date('post_date');

            $table->primary('id');
            $table->index('journal_id');
            $table->index('transaction_group');
            $table->index(['ref_model', 'ref_model_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('journal_transactions');
    }
};
