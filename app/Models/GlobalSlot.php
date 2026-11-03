<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use App\Enums\GlobalSlotPosition;
use App\Enums\GlobalSlotType;
use App\Features\Themes\ThemeOutput;
use Database\Factories\GlobalSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Override;

/**
 * One ordered, switchable row of CSS or HTML for the head or footer of every
 * theme. Rows are grouped by `key`, the owner scope (see {@see self::SITE_KEY}).
 *
 * A row whose `slot` is not a {@see GlobalSlotPosition} value is refused on save
 * with a validation error on `slot`. `slot` is therefore a plain string and not
 * an enum cast, which would throw a `ValueError` on assignment. Any save or delete bumps the cache counter that
 * versions the compiled output (see {@see ThemeOutput}).
 *
 * @property int            $id
 * @property string         $key
 * @property string         $slot
 * @property GlobalSlotType $type
 * @property string         $name
 * @property string|null    $value
 * @property int            $order
 * @property bool           $enabled
 * @property Carbon|null    $created_at
 * @property Carbon|null    $updated_at
 *
 * @method static GlobalSlotFactory factory($count = null, $state = [])
 */
class GlobalSlot extends Model
{
    /** @use HasFactory<GlobalSlotFactory> */
    use HasFactory;

    /**
     * The key of the rows that apply to every page of every theme.
     */
    public const string SITE_KEY = 'global';

    public $table = 'global_slots';

    protected $fillable = [
        'key',
        'slot',
        'type',
        'name',
        'value',
        'order',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type'    => GlobalSlotType::class,
            'order'   => 'integer',
            'enabled' => 'boolean',
        ];
    }

    #[Override]
    protected static function booted(): void
    {
        static::saving(function (self $slot): void {
            $raw = $slot->getAttributes()['slot'] ?? null;

            if ($raw instanceof GlobalSlotPosition) {
                $raw = $raw->value;
                $slot->setAttribute('slot', $raw);
            }

            if (!is_string($raw) || GlobalSlotPosition::tryFrom($raw) === null) {
                throw ValidationException::withMessages([
                    'slot' => __('filament.global_slots.unknown_slot'),
                ]);
            }
        });

        static::saved(fn () => ThemeOutput::bump());
        static::deleted(fn () => ThemeOutput::bump());
    }
}
