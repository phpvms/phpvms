<?php

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

/**
 * The Award model.
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
     * Get the referring object.
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
}
