<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string                 $theme_name
 * @property int                    $published_theme_revision_id
 * @property PublishedThemeRevision $revision
 */
class ActiveThemePublication extends Model
{
    protected $primaryKey = 'theme_name';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'theme_name',
        'published_theme_revision_id',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(PublishedThemeRevision::class, 'published_theme_revision_id');
    }
}
