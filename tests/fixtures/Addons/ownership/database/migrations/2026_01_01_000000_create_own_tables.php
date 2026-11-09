<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('own_widgets', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('own_widget_tags', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('own_widget_tags');
        Schema::dropIfExists('own_widgets');
    }
};
