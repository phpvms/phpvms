<?php

declare(strict_types=1);

use App\Jobs\ChangeJournalCurrencies;
use App\Settings\BidsSettings;
use App\Settings\LivemapSettings;
use App\Support\Branding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * Side effects of a settings change, whichever path writes it: the activity
 * log, the bid expiry guard, the journal currency move and the branding cache.
 */
beforeEach(function (): void {
    activity()->enableLogging();
    $this->actingAs(createAdminUser());
});

function settingsActivities(): Collection
{
    return Activity::query()->where('log_name', 'settings')->get();
}

it('logs a changed setting with its old and new value and who changed it', function (): void {
    setting_save('pilots.id_length', 4);
    Activity::query()->delete();

    setting_save('pilots.id_length', 5);

    $activity = settingsActivities()->sole();

    expect($activity->properties['old'])->toBe(['pilots.id_length' => 4])
        ->and($activity->properties['attributes'])->toBe(['pilots.id_length' => 5])
        ->and($activity->causer_id)->toBe(auth()->id());
});

it('logs that a secret changed without its value', function (): void {
    Activity::query()->delete();

    setting_save('captcha.secret_key', 'hunter2');

    $activity = settingsActivities()->sole();

    expect($activity->properties['redacted'])->toBe(['captcha.secret_key'])
        ->and(json_encode($activity->properties))->not->toContain('hunter2');
});

it('logs nothing for a save that changes nothing', function (): void {
    setting_save('pilots.id_length', 6);
    Activity::query()->delete();

    setting_save('pilots.id_length', 6);

    expect(settingsActivities())->toHaveCount(0);
});

it('rejects a negative bid expiry from any write path', function (): void {
    setting_save('bids.expire_time', 12);

    expect(fn () => setting_save('bids.expire_time', -1))->toThrow(ValidationException::class)
        ->and(fn () => app(BidsSettings::class)->fill(['expire_time' => -1])->save())->toThrow(ValidationException::class);

    reloadSettings();

    expect(setting('bids.expire_time'))->toBe(12);
});

it('queues the journal currency move once when the currency changes through code', function (): void {
    Queue::fake();

    setting_save('units.currency', 'EUR');

    Queue::assertPushed(ChangeJournalCurrencies::class, 1);
});

it('queues the currency move only after the change commits', function (): void {
    Queue::fake();

    DB::transaction(function (): void {
        setting_save('units.currency', 'EUR');

        Queue::assertNothingPushed();
    });

    Queue::assertPushed(ChangeJournalCurrencies::class, 1);
});

it('queues nothing when the currency keeps its value', function (): void {
    setting_save('units.currency', 'USD');
    Queue::fake();

    setting_save('units.currency', 'USD');
    setting_save('units.weight', 'kg');

    Queue::assertNothingPushed();
});

it('shows a changed site name in the branding', function (): void {
    expect(app(Branding::class)->name())->toBe(config('app.name'));

    setting_save('general.site_name', 'Acme Air');

    expect(app(Branding::class)->name())->toBe('Acme Air');
});

it('saves a group that has no stored rows yet', function (): void {
    DB::table('settings')->where('group', 'livemap')->delete();
    reloadSettings();

    app(LivemapSettings::class)->fill(['default_zoom' => 7])->save();
    reloadSettings();

    expect(setting('livemap.default_zoom'))->toBe(7)
        ->and(setting('livemap.update_interval'))->toBe(60)
        ->and(DB::table('settings')->where('group', 'livemap')->count())->toBe(5);
});
