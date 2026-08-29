<?php

namespace App\Models;

use App\Contracts\Model;
use App\Enums\AwardTrigger;
use App\Http\Resources\AwardResource;
use App\Traits\HasAssets;
use Database\Factories\AwardFactory;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Kyslik\ColumnSortable\Sortable;
use Override;

/**
 * The Award model
 *
 * @property int               $id
 * @property string            $name
 * @property string|null       $description
 * @property string|null       $image_url
 * @property string|null       $icon
 * @property string|null       $category
 * @property string|null       $ref_model_type
 * @property string|null       $ref_model_params
 * @property AwardTrigger|null $trigger
 * @property-read AwardRule|null $rule
 * @property int|null    $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read mixed $image
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static AwardFactory          factory($count = null, $state = [])
 * @method static Builder<static>|Award newModelQuery()
 * @method static Builder<static>|Award newQuery()
 * @method static Builder<static>|Award onlyTrashed()
 * @method static Builder<static>|Award query()
 * @method static Builder<static>|Award sortable($defaultParameters = null)
 * @method static Builder<static>|Award whereActive($value)
 * @method static Builder<static>|Award whereCategory($value)
 * @method static Builder<static>|Award whereCreatedAt($value)
 * @method static Builder<static>|Award whereDeletedAt($value)
 * @method static Builder<static>|Award whereDescription($value)
 * @method static Builder<static>|Award whereIcon($value)
 * @method static Builder<static>|Award whereId($value)
 * @method static Builder<static>|Award whereImageUrl($value)
 * @method static Builder<static>|Award whereName($value)
 * @method static Builder<static>|Award whereRefModelParams($value)
 * @method static Builder<static>|Award whereRefModelType($value)
 * @method static Builder<static>|Award whereUpdatedAt($value)
 * @method static Builder<static>|Award withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Award withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Award extends Model
{
    use HasAssets;

    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    use SoftDeletes;
    use Sortable;

    public $table = 'awards';

    /**
     * Starting categories offered in the admin picker. Not a closed set — the
     * `category` column takes any string, and anything already saved is offered
     * alongside these. See categoryOptions().
     */
    public const array CATEGORIES = [
        'MILESTONE',
        'DISTANCE',
        'SKILL',
        'ROUTE',
        'SPECIAL',
    ];

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'icon',
        'category',
        'ref_model_type',
        'ref_model_params',
        'trigger',
        'active',
    ];

    public static array $rules = [
        'name'             => 'required',
        'description'      => 'nullable',
        'image_url'        => 'nullable',
        'icon'             => 'nullable',
        'category'         => 'nullable',
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

    #[Override]
    protected function casts(): array
    {
        return [
            'trigger' => AwardTrigger::class,
        ];
    }

    /** @return HasOne<AwardRule, $this> */
    public function rule(): HasOne
    {
        return $this->hasOne(AwardRule::class);
    }

    /**
     * True when this award's criteria are a rules-based condition tree,
     * rather than the legacy ref_model_type class path.
     */
    public function isRulesBased(): bool
    {
        return $this->rule !== null;
    }

    /**
     * Upserts or removes this award's ruleset row. A null tree — legacy
     * award, or criteria cleared — deletes the row.
     */
    public function saveConditionsTree(?array $tree): void
    {
        if ($tree === null) {
            $this->rule()->delete();
            $this->unsetRelation('rule');

            return;
        }

        $rule = $this->rule()->updateOrCreate([], ['conditions' => $tree]);
        $rule->syncSnippetsFromConditions();
        $this->setRelation('rule', $rule);
    }

    /**
     * Get the referring object
     */
    public function getReference(?self $award = null, ?User $user = null): ?object
    {
        if (!$this->ref_model_type) {
            return null;
        }

        try {
            return new $this->ref_model_type($award, $user);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * The categories offered in a picker: the presets, plus every category
     * anyone has already invented, so a one-off from last month is a choice
     * this month rather than something to retype exactly.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return self::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->merge(self::CATEGORIES)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $category): array => [$category => $category])
            ->all();
    }

    /**
     * Descriptions are plain text. Normalising on write rather than on render
     * means every path stores the same thing — the admin form, an imported
     * document (`AwardExport::fromJson()`), a seeder — and no consumer has to
     * decide whether what it holds is markup.
     *
     * The column held HTML while the field was a `RichEditor`; existing rows
     * are converted by the `award_descriptions_to_plain_text` data migration,
     * and this keeps any that arrive later from an old export in line.
     */
    public function description(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::toPlainText($value),
        );
    }

    /**
     * Flatten HTML to plain text, preserving line structure.
     *
     * Block boundaries become newlines *before* the tags come off — a bare
     * `strip_tags()` on `<p>one</p><p>two</p>` yields "onetwo", running two
     * sentences together. Entities are decoded after, so `&amp;` reads as `&`
     * rather than surviving as an escape in text nobody will unescape again.
     * Plain text in already passes through unchanged.
     */
    public static function toPlainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = preg_replace('#<(?:br\s*/?|/p|/div|/li|/h[1-6])\s*>#i', "\n", $value) ?? $value;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Non-breaking spaces come back from entity decoding as U+00A0, which
        // is not whitespace to trim(); a description of "&nbsp;" is empty.
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** The award badge lives in the `award` slot, keyed on the award id. */
    public function assetSlot(): string
    {
        return Asset::SLOT_AWARD;
    }

    /**
     * The badge's URL: the award's asset when it has one, falling back to the
     * legacy `image_url` column — mirroring {@see Rank::imageUrl()}.
     *
     * The column is only reached on an install whose data migration could not
     * move the value — a file that had already been deleted, or a value that
     * was neither a hosted path nor an absolute URL. Rendering the old value
     * there is better than rendering nothing. A hosted path (the `awards/`
     * prefix the upload field always wrote) still resolves through the public
     * disk, exactly as the retired accessor did; anything else is returned
     * verbatim.
     */
    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                $url = $this->assetUrl();

                if ($url !== null) {
                    return $url;
                }

                if (!filled($value)) {
                    return null;
                }

                if (str_starts_with($value, 'awards/')) {
                    return Storage::disk(config('filesystems.public_files'))->url($value);
                }

                return $value;
            },
        );
    }

    /**
     * Alias kept for the consumers that already read `image` rather than
     * `image_url` ({@see AwardResource}, the awards relation manager's table
     * column) — same resolution, just a second name.
     */
    public function image(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->image_url,
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_awards', 'award_id', 'user_id')
            ->withTimestamps()
            ->withTrashed();
    }
}
