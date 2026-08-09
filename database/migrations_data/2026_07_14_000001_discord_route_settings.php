<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Rename the Discord webhook settings to route settings, carrying their
     * values across so an install that already has Discord working keeps
     * working. A route now accepts a webhook URL *or* a channel id — the
     * notifier resolves the transport from the value's shape — so the "webhook"
     * in the old key names no longer describes what they hold.
     *
     * @var list<array{old: string, new: string, name: string, description: string}>
     */
    private array $renames = [
        [
            'old'         => 'notifications.discord_public_webhook_url',
            'new'         => 'notifications.discord_public_route',
            'name'        => 'Discord Public Route',
            'description' => 'Where public notifications are sent: either a Discord Webhook URL, or a channel ID to post through your bot (a channel ID requires DISCORD_BOT_TOKEN to be set)',
        ],
        [
            'old'         => 'notifications.discord_private_webhook_url',
            'new'         => 'notifications.discord_private_route',
            'name'        => 'Discord Private Route',
            'description' => 'Where private (staff) notifications are sent: either a Discord Webhook URL, or a channel ID to post through your bot (a channel ID requires DISCORD_BOT_TOKEN to be set)',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->renames as $rename) {
            $this->move(
                from: $rename['old'],
                to: $rename['new'],
                name: $rename['name'],
                description: $rename['description'],
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->renames as $rename) {
            $this->move(
                from: $rename['new'],
                to: $rename['old'],
                name: str_replace('Route', 'Webhook URL', $rename['name']),
                description: 'The Discord Webhook URL for '.(str_contains($rename['old'], 'public') ? 'public' : 'private').' notifications',
            );
        }
    }

    /**
     * Recreate a setting under a new key, preserving the admin's value and the
     * row's position within its group, then drop the old key.
     *
     * The target usually exists already: Updater runs SeederService::syncAllSeeds()
     * before the data migrations, so the seeder has just inserted the new key with
     * an empty value. Carry the old value onto it rather than dropping it, which
     * would silently unconfigure Discord on every install that had it working.
     *
     * Idempotent, and never clobbers a value an admin has since set on the new key.
     */
    private function move(string $from, string $to, string $name, string $description): void
    {
        $target = Setting::where('id', Setting::formatKey($to))->first();
        $source = Setting::where('id', Setting::formatKey($from))->first();

        if ($target instanceof Setting) {
            if (blank($target->value) && $source instanceof Setting && filled($source->value)) {
                $target->value = $source->value;
                $target->save();
            }

            $source?->delete();

            return;
        }

        // id, offset and order are not fillable / auto-derived, so set them
        // explicitly the same way SettingsSeeder does.
        $model = new Setting([
            'key'         => $to,
            'name'        => $name,
            'value'       => $source->value ?? '',
            'group'       => 'notifications',
            'type'        => 'text',
            'options'     => '',
            'description' => $description,
        ]);
        $model->id = Setting::formatKey($to);
        $model->default = '';
        $model->offset = $source->offset ?? 0;
        $model->order = $source->order ?? 0;
        $model->save();

        $source?->delete();
    }
};
