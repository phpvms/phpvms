<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int         $id
 * @property string      $theme_name
 * @property int         $schema_version
 * @property array       $document
 * @property string|null $custom_css
 * @property string      $revision
 * @property Carbon      $published_at
 */
class PublishedThemeRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'theme_name',
        'schema_version',
        'document',
        'custom_css',
        'revision',
        'published_at',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'document'     => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function activePublication(): HasOne
    {
        return $this->hasOne(ActiveThemePublication::class);
    }
}
