<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Services\FinanceService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Move the journals and journal transactions to the `units.currency` setting.
 *
 * Dispatched by the settings page when the currency changes. On a busy VA the
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
        // Read from the table when the job runs, not from setting() or a value
        // captured at dispatch: two quick currency changes then both converge
        // on the latest one, whichever order the jobs run in.
        $currency = Setting::query()->byKey('units.currency')->value('value') ?? 'USD';

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
