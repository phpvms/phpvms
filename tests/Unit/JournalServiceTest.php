<?php

declare(strict_types=1);

use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Models\Pirep;
use App\Services\JournalService;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

/*
 * Covers JournalService::deleteAllForObject behavior.
 *
 * Migrated from tests/Feature/Characterization/JournalDeleteForObjectCharacterizationTest.php
 * (Phase 0 safety net) when JournalRepository::deleteAllForObject was absorbed
 * into JournalService::deleteAllForObject in Phase 7. Assertions are intentionally
 * identical to the original characterization tests.
 */

function journalSvc(): JournalService
{
    return app(JournalService::class);
}

/**
 * Build a JournalTransaction pointing at the given ref model.
 *
 * Does not use a factory because the JournalTransaction factory class name
 * (JournalTransactionsFactory — plural) does not match Laravel's default
 * resolver convention. Writing the row directly keeps the fixture intent
 * explicit.
 */
function makeJournalTxnForPirep(Journal $journal, Pirep $pirep): JournalTransaction
{
    return JournalTransaction::create([
        'id'                => Uuid::uuid4()->toString(),
        'transaction_group' => Uuid::uuid4()->toString(),
        'journal_id'        => $journal->id,
        'credit'            => 100,
        'debit'             => 0,
        'currency'          => 'USD',
        'memo'              => 'svc-test',
        'ref_model_type'    => Pirep::class,
        'ref_model_id'      => $pirep->id,
        'post_date'         => Carbon::now('UTC')->toDateTimeString(),
    ]);
}

/**
 * Count rows in journal_transactions matching a ref object.
 * Uses a raw query so post-delete state is read from the DB
 * rather than stale Eloquent instances.
 */
function countJournalTxnForPirep(Pirep $pirep, ?Journal $journal = null): int
{
    $query = JournalTransaction::where('ref_model_type', Pirep::class)
        ->where('ref_model_id', $pirep->id);

    if ($journal instanceof Journal) {
        $query->where('journal_id', $journal->id);
    }

    return $query->count();
}

test('deletes all transactions for object type and id', function () {
    /** @var Journal $journal */
    $journal = Journal::factory()->create();

    /** @var Pirep $pirep1 */
    $pirep1 = Pirep::factory()->create();
    /** @var Pirep $pirep2 */
    $pirep2 = Pirep::factory()->create();

    // 3 transactions for pirep1, 1 for pirep2 on the same journal.
    makeJournalTxnForPirep($journal, $pirep1);
    makeJournalTxnForPirep($journal, $pirep1);
    makeJournalTxnForPirep($journal, $pirep1);
    makeJournalTxnForPirep($journal, $pirep2);

    expect(countJournalTxnForPirep($pirep1))->toEqual(3)
        ->and(countJournalTxnForPirep($pirep2))->toEqual(1);

    journalSvc()->deleteAllForObject($pirep1);

    expect(countJournalTxnForPirep($pirep1))->toEqual(0)
        ->and(countJournalTxnForPirep($pirep2))->toEqual(1);
});

test('deletes only transactions for given journal when journal provided', function () {
    /** @var Journal $journalA */
    $journalA = Journal::factory()->create();
    /** @var Journal $journalB */
    $journalB = Journal::factory()->create();

    /** @var Pirep $pirep */
    $pirep = Pirep::factory()->create();

    // One transaction for the same pirep on each journal.
    makeJournalTxnForPirep($journalA, $pirep);
    makeJournalTxnForPirep($journalB, $pirep);

    expect(countJournalTxnForPirep($pirep, $journalA))->toEqual(1)
        ->and(countJournalTxnForPirep($pirep, $journalB))->toEqual(1);

    journalSvc()->deleteAllForObject($pirep, $journalA);

    expect(countJournalTxnForPirep($pirep, $journalA))->toEqual(0)
        ->and(countJournalTxnForPirep($pirep, $journalB))->toEqual(1);
});
