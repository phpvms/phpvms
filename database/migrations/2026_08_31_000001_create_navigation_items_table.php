<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation becomes its own model.
 *
 * Until now the menu *was* the pages table: `PageLinksComposer` selected every
 * enabled page and every enabled page therefore appeared in the nav, while a
 * `PageType::LINK` row was a menu entry wearing a page's schema. That made an
 * unlisted page, a nav ordering, and a nested menu all unrepresentable.
 *
 * Splitting them costs one table and buys all three. A nav item points at one of
 * three things — a page, a named route, or an external URL — via `target_type`
 * and `target_value` rather than a foreign key, because two of the three targets
 * are not rows. Page targets are cleaned up by `Page`'s deleting hook, since the
 * database cannot do it for us here.
 *
 * `public` mirrors `pages.public` so the existing guest filter keeps its meaning.
 * `visible` is the new thing: it hides an item from the menu without unpublishing
 * the page it points at, which is the whole reason a page and its nav entry
 * needed to stop being one row.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('navigation_items')) {
            return;
        }

        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            $table->bigIncrements('id');
            $table->string('label');
            $table->string('icon')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedSmallInteger('target_type')->default(0);
            $table->string('target_value')->nullable();
            $table->boolean('new_window')->default(false);
            $table->boolean('public')->default(true);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('navigation_items')->cascadeOnDelete();

            // The menu is always read whole and always in order.
            $table->index(['parent_id', 'sort']);
            $table->index(['target_type', 'target_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
