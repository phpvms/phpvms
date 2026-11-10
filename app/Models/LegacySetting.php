<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * A row of `settings_backup`: the settings table as it was before the move to
 * typed settings classes (see App\Settings and config/settings.php).
 *
 * Only the historical data migrations in `database/migrations_data/` use it.
 * They still write the old table, so an install upgrading past several of them
 * gets their changes; the last data migration then copies the backup into the
 * new `settings` table. Nothing reads current settings from here.
 *
 * @property string      $id
 * @property int         $offset
 * @property int         $order
 * @property string      $key
 * @property string      $name
 * @property string      $value
 * @property string|null $default
 * @property string|null $group
 * @property string|null $type
 * @property string|null $options
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|LegacySetting byKey(string $key)
 * @method static Builder<static>|LegacySetting newModelQuery()
 * @method static Builder<static>|LegacySetting newQuery()
 * @method static Builder<static>|LegacySetting query()
 *
 * @mixin \Eloquent
 */
#[WithoutIncrementing]
class LegacySetting extends Model
{
    public $table = 'settings_backup';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'key',
        'value',
        'group',
        'type',
        'options',
        'description',
    ];

    public static function formatKey($key): string
    {
        return str_replace('.', '_', strtolower((string) $key));
    }

    /**
     * Force formatting the key
     */
    public function id(): Attribute
    {
        return Attribute::make(
            get: fn ($id, $attrs): string => self::formatKey(strtolower((string) $id))
        );
    }

    /**
     * Set the key to lowercase
     */
    public function key(): Attribute
    {
        return Attribute::make(
            set: fn ($key): string => strtolower((string) $key)
        );
    }

    /**
     * Filter by setting key, applying the same `formatKey` normalization
     * that the id() accessor applies. Equivalent to find($formattedKey).
     */
    #[Scope]
    protected function byKey(Builder $q, string $key): Builder
    {
        return $q->where('id', self::formatKey($key));
    }
}
