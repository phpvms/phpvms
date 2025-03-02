<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Kyslik\ColumnSortable\Sortable;

/**
 * The Award model
 *
 * @property mixed      id
 * @property string     name
 * @property string     description
 * @property string     title
 * @property string     image
 * @property mixed      ref_model
 * @property mixed|null ref_model_params
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
        'ref_model',
        'ref_model_params',
        'active',
    ];

    public static $rules = [
        'name'             => 'required',
        'description'      => 'nullable',
        'image_url'        => 'nullable',
        'ref_model'        => 'required',
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
     * @return null
     */
    public function getReference(?self $award = null, ?User $user = null)
    {
        if (!$this->ref_model) {
            return;
        }

        try {
            return new $this->ref_model($award, $user);
        } catch (\Exception $e) {
            return;
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
        return $this->belongsToMany(User::class, 'user_awards', 'award_id', 'user_id');
    }
}
