<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `global_slots`: ordered, switchable rows of CSS or HTML that are
 * emitted into the head or the footer of every theme. `key` is the owner scope
 * (`global` for site-wide rows), `slot` the output position and `type` the
 * content kind.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('global_slots')) {
            return;
        }

        Schema::create('global_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64);
            $table->string('slot', 32);
            $table->string('type', 16);
            $table->string('name');
            $table->longText('value')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['key', 'slot', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_slots');
    }
};
