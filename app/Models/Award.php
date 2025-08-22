<?php

namespace App\Models;

use App\Contracts\Model;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Kyslik\ColumnSortable\Sortable;

/**
 * The Award model
 *
 * @property int                             $id
 * @property string                          $name
 * @property string|null                     $description
 * @property string|null                     $image_url
 * @property string|null                     $ref_model_type
 * @property string|null                     $ref_model_params
 * @property int|null                        $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read mixed $image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\AwardFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award sortable($defaultParameters = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereRefModelParams($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereRefModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Award withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Award extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Sortable;

    public $table = 'awards';

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'ref_model_type',
        'ref_model_params',
        'active',
    ];

    public static array $rules = [
        'name'             => 'required',
        'description'      => 'nullable',
        'image_url'        => 'nullable',
        'ref_model_type'   => 'required',
        'ref_model_params' => 'nullable',
        'active'           => 'nullable',
    ];

    public $sortable = [
        'id',
        'name',
        'description',
        'active',
        'created_at',
    ];

    /**
     * Get the referring object
     *
     *
     * @return ?object
     */
    public function getReference(?self $award = null, ?User $user = null)
    {
        if (!$this->ref_model_type) {
            return null;
        }

        try {
            return new $this->ref_model_type($award, $user);
        } catch (Exception $e) {
            return null;
        }
    }

    public function image(): Attribute
    {
        return Attribute::make(
            get: function ($_, $attrs) {
                if (array_key_exists('image_url', $attrs)) {
                    if (str_starts_with($attrs['image_url'], 'awards/')) {
                        return Storage::disk(config('filesystems.public_files'))->url($attrs['image_url']);
                    }

                    return $attrs['image_url'];
                }

                return null;
            }
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_awards', 'award_id', 'user_id')
            ->withTimestamps()
            ->withTrashed();
    }
}
