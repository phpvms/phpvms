<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\FinanceService;
use App\Settings\UnitsSettings;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Move the journals and journal transactions to the `units.currency` setting.
 *
 * Dispatched once the currency change commits (see SettingsChangeSubscriber). On a busy VA the
 * update touches hundreds of thousands of transaction rows, so it runs off the
 * request; the admin who changed the currency gets a database notification
 * when it is done, or when it fails.
 */
class ChangeJournalCurrencies implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $notifyUserId = null) {}

    public function handle(FinanceService $financeService): void
    {
        // Read from the table when the job runs, not from the cache or a value
        // captured at dispatch: two quick currency changes then both converge
        // on the latest one, whichever order the jobs run in.
        $units = app(UnitsSettings::class);
        $units->refresh();

        $currency = $units->currency;

        $financeService->changeJournalCurrencies($currency);

        $this->notify(
            Notification::make()
                ->title('Journal currencies updated')
                ->body("Journals and transactions are now in {$currency}.")
                ->success()
        );
    }

    public function failed(?Throwable $exception): void
    {
        $this->notify(
            Notification::make()
                ->title('Journal currency update failed')
                ->body($exception?->getMessage())
                ->danger()
        );
    }

    private function notify(Notification $notification): void
    {
        $user = $this->notifyUserId === null ? null : User::find($this->notifyUserId);

        if (!$user instanceof User) {
            return;
        }

        $notification->sendToDatabase($user);
    }
}
