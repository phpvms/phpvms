<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\ChangeJournalCurrencies;
use App\Settings\BidsSettings;
use App\Settings\UnitsSettings;
use App\Support\Branding;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsRegistry;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\CauserResolver;
use Spatie\LaravelSettings\Events\SavingSettings;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\Settings;
use WeakMap;

/**
 * Side effects of a settings change, on every write path: the admin pages, the
 * helpers, and code saving a settings class itself.
 *
 * Before the write: reject invalid values. After it: log what changed, and
 * (once the change has committed) flush the branding cache and queue the move
 * of the journals to a new currency.
 *
 * `Settings::save()` writes every property of its class, so the changes are
 * worked out from the values the instance was loaded with, not from the save.
 */
final class SettingsChangeSubscriber
{
    /**
     * Changes found while saving, waiting for the save to finish. Keyed weakly
     * by the instance and consumed by the saved event, so nothing outlives the
     * save, even in an Octane worker.
     *
     * @var WeakMap<Settings, array<string, array{SettingDefinition, mixed, mixed}>>|null
     */
    private static ?WeakMap $pending = null;

    /**
     * @throws ValidationException
     */
    public function handleSavingSettings(SavingSettings $event): void
    {
        if ($event->settings instanceof BidsSettings && $event->properties->get('expire_time') < 0) {
            throw ValidationException::withMessages([
                'bids.expire_time' => 'Bid expiry cannot be negative.',
            ]);
        }

        $changes = [];

        foreach (SettingsRegistry::definitionsFor($event->settings::class) as $definition) {
            $new = $event->properties->get($definition->name);
            $old = $event->originalValues?->get($definition->name);

            if (!$event->originalValues instanceof Collection || !$this->same($old, $new)) {
                $changes[$definition->name] = [$definition, $old, $new];
            }
        }

        self::$pending ??= new WeakMap();
        self::$pending[$event->settings] = $changes;

        // A property with no row yet was loaded from its PHP default. The
        // package refuses to save such properties (it expects a settings
        // migration to have created every row), but its repository upserts, so
        // saving creates the missing rows. phpVMS relies on that: settings need
        // no seeding or migration, a row appears the first time a group is
        // saved.
        $event->settings->settingsConfig()->resetDefaultValueLoadedProperties();
    }

    public function handleSettingsSaved(SettingsSaved $event): void
    {
        $changes = self::$pending[$event->settings] ?? [];
        unset(self::$pending[$event->settings]);

        if ($changes === []) {
            return;
        }

        $this->logChanges($changes);

        $currencyChanged = $event->settings instanceof UnitsSettings && isset($changes['currency']);
        $brandingChanged = array_any(
            $changes,
            static fn (array $change): bool => in_array($change[0]->group.'.'.$change[0]->name, Branding::SETTING_KEYS, true),
        );
        // The activity log's causer: the signed-in user, if any, resolved the
        // way the package resolves it for the entry above.
        $userId = app(CauserResolver::class)->resolve()?->getKey();

        DB::afterCommit(static function () use ($currencyChanged, $brandingChanged, $userId): void {
            if ($brandingChanged) {
                Branding::flush();
            }

            if ($currencyChanged) {
                ChangeJournalCurrencies::dispatch($userId);
            }
        });
    }

    /**
     * One activity entry per saved group, in the shape the activity log uses
     * for model updates. Encrypted values are named, never logged.
     *
     * @param array<string, array{SettingDefinition, mixed, mixed}> $changes
     */
    private function logChanges(array $changes): void
    {
        $attributes = [];
        $old = [];
        $redacted = [];

        foreach ($changes as [$definition, $before, $after]) {
            $key = $definition->group.'.'.$definition->name;

            if ($definition->encrypted) {
                $redacted[] = $key;

                continue;
            }

            $attributes[$key] = $this->loggable($after);
            $old[$key] = $this->loggable($before);
        }

        // The causer defaults to the signed-in user (CauserResolver).
        activity('settings')
            ->event('updated')
            ->withProperties(array_filter([
                'attributes' => $attributes,
                'old'        => $old,
                'redacted'   => $redacted,
            ]))
            ->log('updated');
    }

    private function same(mixed $old, mixed $new): bool
    {
        if ($old instanceof DateTimeInterface && $new instanceof DateTimeInterface) {
            return $old == $new;
        }

        return $old === $new;
    }

    private function loggable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum        => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default                             => $value,
        };
    }
}
