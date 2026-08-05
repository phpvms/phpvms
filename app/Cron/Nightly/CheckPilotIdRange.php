<?php

declare(strict_types=1);

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Messages\PilotIdRangeUtilization;
use App\Services\KvpService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Notify super-admins when the pilot ID range's utilization newly crosses
 * 80%, 90%, 95%, or is exhausted. Fires once per newly crossed threshold;
 * re-arms if utilization drops back below a previously notified threshold.
 */
class CheckPilotIdRange extends Listener
{
    /**
     * Thresholds checked from highest to lowest so the highest one crossed wins.
     */
    private const array THRESHOLDS = [100, 95, 90, 80];

    private const string KVP_KEY = 'pilots.id_range_last_notified_threshold';

    public function __construct(private readonly KvpService $kvpSvc) {}

    public function handle(CronNightly $event): void
    {
        if (!setting('pilots.id_range_enabled')) {
            return;
        }

        $floor = max(1, (int) setting('pilots.id_range_start'));
        $ceil = (int) setting('pilots.id_range_end');
        $rangeSize = $ceil - $floor + 1;

        if ($rangeSize <= 0) {
            return;
        }

        $taken = $this->takenPilotIdsInRangeQuery($floor, $ceil)->count();
        $utilization = $taken / $rangeSize * 100;

        $lastNotified = (int) $this->kvpSvc->get(self::KVP_KEY, 0);
        $crossed = 0;
        foreach (self::THRESHOLDS as $threshold) {
            if ($utilization >= $threshold) {
                $crossed = $threshold;
                break;
            }
        }

        if ($crossed === $lastNotified) {
            return;
        }

        $this->kvpSvc->save(self::KVP_KEY, (string) $crossed);

        if ($crossed <= $lastNotified) {
            // Utilization dropped back below a previously notified threshold: re-armed, don't notify.
            return;
        }

        Log::info(sprintf('Cron: Pilot ID range utilization crossed %s%% (%s/%d)', $crossed, $taken, $rangeSize));

        $this->notifySuperAdmins($taken, $rangeSize, $utilization, $floor, $ceil);
    }

    /**
     * Users whose pilot_id counts as "taken" within [$floor, $ceil], mirroring
     * UserService's taken-set semantics: active users always, trashed users too
     * unless `pilots.id_reuse_deleted` is on.
     */
    private function takenPilotIdsInRangeQuery(int $floor, int $ceil)
    {
        $query = User::query();

        if (!setting('pilots.id_reuse_deleted')) {
            $query->withTrashed();
        }

        return $query->whereBetween('pilot_id', [$floor, $ceil]);
    }

    private function notifySuperAdmins(int $taken, int $rangeSize, float $utilization, int $floor, int $ceil): void
    {
        $admins = User::whereHas('roles', function ($query): void {
            $query->where('name', Role::superAdminName());
        })->get();

        if ($admins->isEmpty()) {
            return;
        }

        NotificationFacade::send(
            $admins->filter(fn (User $user): bool => !empty($user->email))->all(),
            new PilotIdRangeUtilization($taken, $rangeSize, $utilization, $floor, $ceil)
        );

        Notification::make()
            ->title('Pilot ID range utilization at '.round($utilization).'%')
            ->body(sprintf('%d of %d pilot IDs are in use (range %d-%d).', $taken, $rangeSize, $floor, $ceil))
            ->warning()
            ->sendToDatabase($admins);
    }
}
