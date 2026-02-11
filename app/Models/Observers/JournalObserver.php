<?php

namespace App\Models\Observers;

use App\Models\Journal;

/**
 * Class JournalObserver
 */
class JournalObserver
{
    public function creating(Journal $journal): void
    {
        $journal->balance = 0;
    }
}
