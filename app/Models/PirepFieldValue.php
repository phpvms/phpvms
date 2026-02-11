<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Enums\PirepFieldSource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int                             $id
 * @property string                          $pirep_id
 * @property string                          $name
 * @property string|null                     $slug
 * @property string|null                     $value
 * @property int                             $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Pirep|null $pirep
 * @property-read mixed $read_only
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue wherePirepId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PirepFieldValue whereValue($value)
 *
 * @mixin \Eloquent
 */
class PirepFieldValue extends Model
{
    public $table = 'pirep_field_values';

    protected $fillable = [
        'pirep_id',
        'name',
        'slug',
        'value',
        'source',
    ];

    public static array $rules = [
        'name' => 'required',
    ];

    /**
     * When setting the name attribute, also set the slug
     */
    public function name(): Attribute
    {
        return Attribute::make(
            set: fn ($name) => [
                'name' => $name,
                'slug' => Str::slug($name),
            ]
        );
    }

    /**
     * If it was filled in from ACARS, then it's read only
     */
    public function readOnly(): Attribute
    {
        return Attribute::make(
            get: fn ($_, $attrs) => $this->source === PirepFieldSource::ACARS
        );
    }

    /**
     * Relationships
     */
    public function pirep(): BelongsTo
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }

    protected function casts(): array
    {
        return [
            'source' => 'integer',
        ];
    }
}
