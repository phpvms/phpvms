<?php

namespace App\Models;

use App\Contracts\Model;
use App\Models\Enums\PirepFieldSource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string pirep_id
 * @property string name
 * @property string slug
 * @property string value
 * @property string source
 * @property Pirep  pirep
 *
 * @method static updateOrCreate(array $array, array $array1)
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

    public static $rules = [
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
                'slug' => \Illuminate\Support\Str::slug($name),
            ]
        );
    }

    /**
     * If it was filled in from ACARS, then it's read only
     *
     * @return bool
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
