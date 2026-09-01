<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per publish, so a bad save stops being an outage with no undo.
 *
 * `pages.body` has never had a history: overwriting it destroyed the previous
 * content. Handing an owner a more powerful editor without this makes that
 * strictly worse, so the history lands before the editor does.
 *
 * The snapshot is of what was published, taken at publish time — not of every
 * keystroke. Drafts are not versioned; the draft is already a safe place to
 * work because it is not what visitors see.
 *
 * `published_by` is nullable and nulls on user deletion: losing the author must
 * not lose the content. Retention is deliberately unbounded for now; a policy is
 * a later decision rather than a guess made here.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('page_revisions')) {
            return;
        }

        Schema::create('page_revisions', function (Blueprint $table): void {
            $table->collation = 'utf8mb4_unicode_ci';
            $table->charset = 'utf8mb4';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('page_id');
            $table->mediumText('body')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();

            // Revisions are always read newest-first for one page.
            $table->index(['page_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
