<?php

namespace App\Models\Traits;

use App\Models\Journal;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait JournalTrait
{
    /**
     * Initialize a new journal when a new record is created
     */
    public static function bootJournalTrait(): void
    {
        static::created(function ($model) {
            $model->initJournal(setting('units.currency'));
        });
    }

    /**
     * Morph to Journal.
     *
     * @return mixed
     */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'morphed');
    }

    /**
     * Initialize a journal for a given model object
     *
     *
     * @throws \Exception
     *
     * @return Journal
     */
    public function initJournal(string $currency_code = 'USD')
    {
        if (!$this->journal) {
            $journal = new Journal();
            $journal->type = $this->journal_type;
            $journal->currency = $currency_code;
            $journal->balance = 0;
            $this->journal()->save($journal);

            $journal->refresh();

            return $journal;
        }
    }
}
