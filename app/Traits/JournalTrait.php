<?php

namespace App\Traits;

use App\Models\Journal;
use Exception;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait JournalTrait
{
    /**
     * Initialize a new journal when a new record is created
     */
    public static function bootJournalTrait(): void
    {
        static::created(function ($model): void {
            $model->initJournal(setting('units.currency'));
        });
    }

    /**
     * Morph to Journal.
     */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'morphed');
    }

    /**
     * Initialize a journal for a given model object
     *
     *
     *
     * @throws Exception
     */
    public function initJournal(string $currency_code = 'USD'): ?Journal
    {
        if (!$this->journal) {
            $journal = new Journal();
            $journal->type = $this->journal_type;
            $journal->currency = $currency_code;
            $journal->balance = 0;
            $this->journal()->save($journal);

            $journal->refresh();

            // The `!$this->journal` guard above lazy-loaded the relation and cached
            // it as null. Saving through the relation does not update that cache, so
            // without this the model keeps returning null for ->journal until it is
            // reloaded from the database. Reflect the freshly-created journal here.
            $this->setRelation('journal', $journal);

            return $journal;
        }

        return null;
    }
}
