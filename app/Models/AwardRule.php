<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AwardRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;

/**
 * An award's ruleset: the conditions tree (which can hold many rules and
 * nested groups AND/OR'd together), one row per award.
 *
 * @property int   $id
 * @property int   $award_id
 * @property array $conditions
 *
 * @method static AwardRuleFactory factory($count = null, $state = [])
 */
class AwardRule extends Model
{
    /** @use HasFactory<AwardRuleFactory> */
    use HasFactory;

    /**
     * Constraint-name prefix marking a rule as a reference to an
     * `award_snippets` row, e.g. `snippet:active-pilot`.
     */
    public const SNIPPET_PREFIX = 'snippet:';

    public $table = 'award_rules';

    protected $fillable = [
        'award_id',
        'conditions',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
        ];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function snippets(): BelongsToMany
    {
        return $this->belongsToMany(AwardSnippet::class, 'award_rule_snippet');
    }

    /**
     * Mirrors the snippets this tree references into the award_rule_snippet
     * pivot, whose foreign key is what blocks deleting a snippet still in
     * use. Call after every write to `conditions`.
     */
    public function syncSnippetsFromConditions(): void
    {
        $names = self::snippetNames($this->conditions ?? []);

        $this->snippets()->sync(
            $names === [] ? [] : AwardSnippet::whereIn('name', $names)->pluck('id')->all()
        );
    }

    /**
     * True when every node in a tree is shaped the way the rule builder needs:
     * an array carrying a string `type`, with nested groups likewise.
     *
     * Filament's `Builder` assumes that shape while hydrating the form and
     * fatals on anything else, which strands the award on a 500 edit page. The
     * criteria the admin builds are always well formed; an imported document is
     * not, so it is checked before it is stored. Same walk as `snippetNames()`,
     * but rejecting what that one steps over.
     *
     * @param array<string, mixed> $rules
     */
    public static function isWellFormedTree(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !is_string($rule['type'] ?? null)) {
                return false;
            }

            $groups = $rule['data']['groups'] ?? [];

            if (!is_array($groups)) {
                return false;
            }

            foreach ($groups as $group) {
                $nested = is_array($group) ? $group['rules'] ?? [] : null;

                if (!is_array($nested) || !self::isWellFormedTree($nested)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Walks a stored query-builder tree collecting the names of the snippets
     * it references. A snippet reference is a rule whose constraint name is
     * `snippet:<name>`; OR blocks nest further rules under their groups.
     *
     * The tree comes out of a json column, so nothing about its shape is
     * guaranteed -- hence the mixed values and the runtime `is_array()`.
     *
     * @param  array<string, mixed> $rules
     * @return list<string>
     */
    private static function snippetNames(array $rules): array
    {
        $names = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $type = $rule['type'] ?? null;

            if (is_string($type) && str_starts_with($type, self::SNIPPET_PREFIX)) {
                $names[] = substr($type, strlen(self::SNIPPET_PREFIX));

                continue;
            }

            foreach ($rule['data']['groups'] ?? [] as $group) {
                $names = [...$names, ...self::snippetNames($group['rules'] ?? [])];
            }
        }

        return array_values(array_unique($names));
    }
}
