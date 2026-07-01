<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single setting belonging to one addon.
 *
 * Mirrors the typed columns of {@see Setting} but scoped per addon via
 * `addon_id`. Settings are declared by an addon's service provider (see
 * App\Contracts\Addons\HasSettings) and synced into this table on boot.
 *
 * @property int         $id
 * @property int         $addon_id
 * @property string|null $alias
 * @property int         $order
 * @property string      $key
 * @property string      $name
 * @property string|null $value
 * @property string|null $default
 * @property string|null $group
 * @property string|null $type
 * @property string|null $options
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Addon $addon
 *
 * @method static Builder<static>|AddonSetting newModelQuery()
 * @method static Builder<static>|AddonSetting newQuery()
 * @method static Builder<static>|AddonSetting query()
 *
 * @mixin \Eloquent
 */
class AddonSetting extends Model
{
    use HasFactory;

    public $table = 'addon_settings';

    protected $fillable = [
        'addon_id',
        'alias',
        'order',
        'key',
        'name',
        'value',
        'default',
        'group',
        'type',
        'options',
        'description',
    ];

    /**
     * Normalize a setting key the same way the core Setting model does:
     * lower-cased with dots collapsed to underscores.
     */
    public static function formatKey(string $key): string
    {
        return str_replace('.', '_', strtolower($key));
    }

    /**
     * The addon that owns this setting.
     *
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}
