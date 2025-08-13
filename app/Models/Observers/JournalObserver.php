<?php

namespace App\Models\Observers;

use App\Models\Journal;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Class JournalObserver
 */
class JournalObserver
{
    /**
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function creating(Journal $journal): void
    {
        $journal->balance = 0;
    }
}
