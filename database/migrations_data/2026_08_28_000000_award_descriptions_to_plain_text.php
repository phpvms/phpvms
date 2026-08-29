<?php

use App\Models\Award;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: flatten `awards.description` from HTML to plain text.
 *
 * The field was a Filament `RichEditor`, so the column holds markup — and an
 * admin who cleared the editor left `<p></p>` behind, which rendered as those
 * literal characters on the pilot profile rather than as "no description".
 * The field is a `Textarea` now and `Award::description()` normalises on write,
 * so this brings existing rows in line with what every new write produces.
 *
 * Conversion goes through `Award::toPlainText()` — the same function the
 * mutator uses, so a row migrated here and a row saved afterwards cannot drift.
 *
 * Rows already free of markup come back from it unchanged and are skipped, so
 * this is idempotent and safe to re-run. There is no `down()`: the original
 * markup is not recoverable from the text, and plain text is the target state
 * rather than a step on the way somewhere else.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('awards')) {
            return;
        }

        DB::table('awards')
            ->select(['id', 'description'])
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($awards): void {
                foreach ($awards as $award) {
                    $plain = Award::toPlainText($award->description);

                    if ($plain === $award->description) {
                        continue;
                    }

                    DB::table('awards')
                        ->where('id', $award->id)
                        ->update(['description' => $plain === '' ? null : $plain]);
                }
            });
    }
};
