<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Services\FinanceService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Ramsey\Uuid\Uuid;

/**
 * Saving the settings page used to rewrite the currency of every journal and
 * journal transaction, whether or not the currency had changed. On a VA with
 * hundreds of thousands of transactions that took seconds and held row locks
 * that blocked finance writes for as long.
 */
function currencyTxn(Journal $journal, string $currency): JournalTransaction
{
    return JournalTransaction::create([
        'id'                => Uuid::uuid4()->toString(),
        'transaction_group' => Uuid::uuid4()->toString(),
        'journal_id'        => $journal->id,
        'credit'            => 100,
        'debit'             => 0,
        'currency'          => $currency,
        'memo'              => 'currency-test',
        'post_date'         => Carbon::now('UTC')->toDateTimeString(),
    ]);
}

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
});

/** UPDATE statements run against the journal tables while $run executes. */
function journalUpdates(callable $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    return collect($queries)
        ->filter(fn (array $q): bool => preg_match('/^update [`"]?(journals|journal_transactions)[`"]? /i', (string) $q['query']) === 1)
        ->count();
}

it('leaves the journals alone when the settings are saved without a currency change', function (): void {
    $this->actingAs(createAdminUser());
    currencyTxn(Journal::factory()->create(['currency' => 'USD']), 'USD');

    $page = Livewire::test(Settings::class);

    expect(journalUpdates(fn () => $page->call('save')))->toBe(0);
});

it('moves the journals to the new currency when it changes', function (): void {
    $this->actingAs(createAdminUser());
    $journal = Journal::factory()->create(['currency' => 'USD']);
    $txn = currencyTxn($journal, 'USD');

    Livewire::test(Settings::class)
        ->set('data.units.currency', 'EUR')
        ->call('save');

    expect($journal->fresh()->currency)->toBe('EUR')
        ->and($txn->fresh()->currency)->toBe('EUR');
});

it('migrates the journals on a retry even when the cache already holds the new currency', function (): void {
    // A currency save that rolled back can leave its uncommitted value in the
    // application cache while the table and the journals keep the old one.
    app()->detectEnvironment(fn (): string => 'production');
    $this->actingAs(createAdminUser());
    $journal = Journal::factory()->create(['currency' => 'USD']);
    $txn = currencyTxn($journal, 'USD');
    Cache::put(config('cache.keys.SETTINGS.key').'units.currency', 'EUR', 60);

    Livewire::test(Settings::class)
        ->set('data.units.currency', 'EUR')
        ->call('save');

    expect($journal->fresh()->currency)->toBe('EUR')
        ->and($txn->fresh()->currency)->toBe('EUR');
});

it('only rewrites journal rows still on another currency', function (): void {
    setting_save('units.currency', 'EUR');
    $journal = Journal::factory()->create(['currency' => 'EUR']);
    $current = currencyTxn($journal, 'EUR');
    $stale = currencyTxn($journal, 'USD');
    $before = $current->fresh()->updated_at;

    $this->travel(5)->minutes();
    app(FinanceService::class)->changeJournalCurrencies();

    expect($stale->fresh()->currency)->toBe('EUR')
        ->and($current->fresh()->updated_at)->toEqual($before);
});
