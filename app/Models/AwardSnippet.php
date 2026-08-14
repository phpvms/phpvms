<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Model;
use Database\Factories\AwardSnippetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * A named, reusable fragment of an award conditions tree. Snippets take no
 * parameters: a referencing ruleset expands the stored conditions unchanged.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $label
 * @property string|null $description
 * @property array       $conditions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static AwardSnippetFactory factory($count = null, $state = [])
 */
class AwardSnippet extends Model
{
    /** @use HasFactory<AwardSnippetFactory> */
    use HasFactory;

    public $table = 'award_snippets';

    protected $fillable = [
        'name',
        'label',
        'description',
        'conditions',
    ];

    public static array $rules = [
        'name'        => 'required',
        'label'       => 'required',
        'description' => 'nullable',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
        ];
    }

    /**
     * The rulesets referencing this snippet. Non-empty means deletion is
     * refused by the `award_rule_snippet` foreign key.
     */
    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(AwardRule::class, 'award_rule_snippet');
    }

    /**
     * The awards whose criteria still reference this snippet, by name.
     *
     * The `award_rule_snippet` foreign key already refuses the delete, but
     * only as a `QueryException`. The admin UI checks this first so it can
     * refuse with a message naming what is in the way.
     *
     * @return list<string>
     */
    public function referencingAwardNames(): array
    {
        return $this->rules()
            ->with('award')
            ->get()
            ->pluck('award.name')
            ->filter()
            ->values()
            ->all();
    }
}
