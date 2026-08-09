<?php

use App\Cron\Nightly\CheckPilotIdRange;
use App\Events\CronNightly;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Messages\PilotIdRangeUtilization;
use App\Services\KvpService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

// The KVP store is a JSON file, not reset by RefreshDatabase — clear our
// marker before each test so state can't leak in from a previous test.
beforeEach(function (): void {
    app(KvpService::class)->save('pilots.id_range_last_notified_threshold', '0');
});

/**
 * Creates a super-admin user and $count users with sequential pilot IDs
 * starting at 1, so utilization = $count / $rangeEnd exactly. The admin's
 * own pilot_id is pushed outside the tested range so it doesn't skew that
 * math (the observer would otherwise land it at pilot_id 1, inside range).
 */
function makeSuperAdminAndPilots(int $count, int $rangeEnd): User
{
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole($role);

    setting_save('pilots.id_range_enabled', true);
    setting_save('pilots.id_range_start', 1);
    setting_save('pilots.id_range_end', $rangeEnd);

    $admin->update(['pilot_id' => $rangeEnd + 1000]);

    User::factory()->count($count)->sequence(fn ($sequence): array => ['pilot_id' => $sequence->index + 1])->create();

    return $admin;
}

function runCron(): void
{
    new CheckPilotIdRange(app(KvpService::class))->handle(new CronNightly());
}

// Range is kept small (20) to stay well under UserFactory's unique()
// text(5) pool (~68 values before it throws) across a single test.

test('crossing 80 percent notifies super admins by mail and database', function (): void {
    Notification::fake();

    $admin = makeSuperAdminAndPilots(16, 20);

    runCron();

    Notification::assertSentTo($admin, PilotIdRangeUtilization::class);
    Notification::assertSentTo($admin, DatabaseNotification::class);
});

test('second run at the same utilization sends nothing', function (): void {
    Notification::fake();

    $admin = makeSuperAdminAndPilots(16, 20);

    runCron();
    Notification::assertSentTo($admin, PilotIdRangeUtilization::class);

    Notification::fake();
    runCron();
    Notification::assertNothingSent();
});

test('dropping below a threshold then rising past it again fires again', function (): void {
    Notification::fake();

    $admin = makeSuperAdminAndPilots(16, 20);
    runCron();
    Notification::assertSentTo($admin, PilotIdRangeUtilization::class);

    // Drop back below 80% by removing pilots down to 10 (50%).
    User::whereBetween('pilot_id', [11, 16])->forceDelete();

    Notification::fake();
    runCron();
    Notification::assertNothingSent();

    // Rise back past 80%.
    User::factory()->count(7)->sequence(fn ($sequence): array => ['pilot_id' => 11 + $sequence->index])->create();

    Notification::fake();
    runCron();
    Notification::assertSentTo($admin, PilotIdRangeUtilization::class);
});

test('a full range notifies as exhausted', function (): void {
    Notification::fake();

    $admin = makeSuperAdminAndPilots(20, 20);

    runCron();

    Notification::assertSentTo($admin, PilotIdRangeUtilization::class);
});

test('a disabled range stays silent', function (): void {
    Notification::fake();

    $admin = makeSuperAdminAndPilots(16, 20);
    setting_save('pilots.id_range_enabled', false);

    runCron();

    Notification::assertNothingSent();
});

test('utilization below 80 percent stays silent', function (): void {
    Notification::fake();

    $admin = makeSuperAdminAndPilots(8, 20);

    runCron();

    Notification::assertNothingSent();
});
