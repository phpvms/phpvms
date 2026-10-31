<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Jobs\ChangeJournalCurrencies;
use App\Models\Journal;
use App\Models\JournalTransaction;
use App\Services\FinanceService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

/**
 * The row's stored currency. journal_transactions.currency is a char(5), which
 * Postgres returns padded with spaces ('EUR  ').
 */
function currencyOf(Journal|JournalTransaction $model): string
{
    return trim((string) $model->fresh()->currency);
}

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
    Queue::fake();
    $this->actingAs(createAdminUser());
    currencyTxn(Journal::factory()->create(['currency' => 'USD']), 'USD');

    $page = Livewire::test(Settings::class);

    expect(journalUpdates(fn () => $page->call('save')))->toBe(0);
    Queue::assertNotPushed(ChangeJournalCurrencies::class);
});

it('queues the journal migration for the admin when the currency changes', function (): void {
    Queue::fake();
    $admin = createAdminUser();
    $this->actingAs($admin);
    currencyTxn(Journal::factory()->create(['currency' => 'USD']), 'USD');

    $page = Livewire::test(Settings::class)->set('data.units.currency', 'EUR');

    expect(journalUpdates(fn () => $page->call('save')))->toBe(0);
    $page->assertNotified();
    Queue::assertPushed(
        ChangeJournalCurrencies::class,
        fn (ChangeJournalCurrencies $job): bool => $job->notifyUserId === $admin->id,
    );
});

it('moves the journals to the new currency and notifies the admin', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);
    $journal = Journal::factory()->create(['currency' => 'USD']);
    $txn = currencyTxn($journal, 'USD');

    Livewire::test(Settings::class)
        ->set('data.units.currency', 'EUR')
        ->call('save');

    expect(currencyOf($journal))->toBe('EUR')
        ->and(currencyOf($txn))->toBe('EUR')
        ->and($admin->notifications()->count())->toBe(1)
        ->and($admin->notifications()->first()->data['title'])->toBe('Journal currencies updated');
});

it('queues the migration on a retry even when the cache already holds the new currency', function (): void {
    // A currency save that rolled back can leave its uncommitted value in the
    // application cache while the table and the journals keep the old one.
    Queue::fake();
    app()->detectEnvironment(fn (): string => 'production');
    $this->actingAs(createAdminUser());
    Cache::put(config('cache.keys.SETTINGS.key').'units.currency', 'EUR', 60);

    Livewire::test(Settings::class)
        ->set('data.units.currency', 'EUR')
        ->call('save');

    Queue::assertPushed(ChangeJournalCurrencies::class);
});

it('moves the journals to the stored currency, not a stale cached one', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    setting_save('units.currency', 'EUR');
    Cache::put(config('cache.keys.SETTINGS.key').'units.currency', 'USD', 60);
    $journal = Journal::factory()->create(['currency' => 'USD']);
    $txn = currencyTxn($journal, 'USD');

    new ChangeJournalCurrencies()->handle(app(FinanceService::class));

    expect(currencyOf($journal))->toBe('EUR')
        ->and(currencyOf($txn))->toBe('EUR');
});

it('notifies the admin when the journal migration fails', function (): void {
    $admin = createAdminUser();

    new ChangeJournalCurrencies($admin->id)->failed(new RuntimeException('Lock wait timeout exceeded'));

    expect($admin->notifications()->count())->toBe(1)
        ->and($admin->notifications()->first()->data['title'])->toBe('Journal currency update failed')
        ->and($admin->notifications()->first()->data['body'])->toBe('Lock wait timeout exceeded');
});

it('only rewrites journal rows still on another currency', function (): void {
    setting_save('units.currency', 'EUR');
    $journal = Journal::factory()->create(['currency' => 'EUR']);
    $current = currencyTxn($journal, 'EUR');
    $stale = currencyTxn($journal, 'USD');
    $before = $current->fresh()->updated_at;

    $this->travel(5)->minutes();
    app(FinanceService::class)->changeJournalCurrencies();

    expect(currencyOf($stale))->toBe('EUR')
        ->and($current->fresh()->updated_at)->toEqual($before);
});
