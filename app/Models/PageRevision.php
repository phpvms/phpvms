<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Database\Factories\PageRevisionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * A snapshot of what a page looked like at one publish.
 *
 * Written by publishing, never by editing: the draft is already a safe place to
 * work, so versioning keystrokes would only add noise to the list an author has
 * to read when recovering.
 *
 * @property int         $id
 * @property int         $page_id
 * @property string|null $body
 * @property Carbon|null $published_at
 * @property int|null    $published_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Page   $page
 * @property-read ?User  $author
 *
 * @method static Builder<static>|PageRevision newModelQuery()
 * @method static Builder<static>|PageRevision newQuery()
 * @method static Builder<static>|PageRevision query()
 *
 * @mixin \Eloquent
 */
class PageRevision extends Model
{
    /** @use HasFactory<PageRevisionFactory> */
    use HasFactory;

    public $table = 'page_revisions';

    protected $fillable = [
        'page_id',
        'body',
        'published_at',
        'published_by',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
