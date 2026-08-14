<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints;

use App\Services\Awards\Constraints\Operators\PirepAggregateOperator;
use App\Services\Awards\Constraints\Operators\PirepCountOperator;
use App\Services\Awards\Constraints\Operators\PirepOperator;
use Closure;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\Support\Icons\Heroicon;

/**
 * Everything an award can say about a user's accepted PIREPs (design D4).
 *
 * This is the only bespoke constraint in the set, and it exists for
 * correctness rather than expressiveness. Filament applies each rule as its
 * own `whereHas` (`QueryBuilder::applyRulesToQuery()`), so two stock dotted
 * rules about "a PIREP" compile to two independent subqueries and can match
 * two *different* PIREPs -- "flew to KJFK once, landed smoothly once" dressed
 * up as "landed smoothly at KJFK". Design D3 therefore registers no dotted
 * constraints at all, and this constraint carries the whole inner rule tree
 * into a single subquery so every inner condition describes the same record.
 *
 * The user correlation and `state = ACCEPTED` are added by the operators and
 * are not expressible from a submitted tree.
 */
class PirepConstraint extends Constraint
{
    /** The `User` relationship every operator correlates against. */
    public const string RELATIONSHIP = 'pireps';

    protected ?string $triggeringPirepId = null;

    protected bool|Closure $canScopeToTriggeringPirep = false;

    public static function getDefaultName(): ?string
    {
        return 'pireps';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('PIREPs');

        $this->icon(Heroicon::PaperAirplane);

        $this->operators([
            PirepCountOperator::class,
            PirepAggregateOperator::class,
        ]);
    }

    /**
     * Bind the PIREP whose acceptance is driving this evaluation (design D6).
     *
     * Operators whose rule opts into the triggering-PIREP scope narrow their
     * subquery to this record. With nothing bound -- a nightly `user` run --
     * such a rule applies nothing at all rather than silently widening to
     * every PIREP the user has ever filed.
     */
    public function triggeringPirep(?string $id): static
    {
        $this->triggeringPirepId = $id;

        return $this;
    }

    public function getTriggeringPirepId(): ?string
    {
        return $this->triggeringPirepId;
    }

    /**
     * Offer the triggering-PIREP scope in the builder. Awards triggered
     * nightly have no PIREP to bind, so they leave this off.
     */
    public function allowTriggeringPirepScope(bool|Closure $condition = true): static
    {
        $this->canScopeToTriggeringPirep = $condition;

        return $this;
    }

    public function canScopeToTriggeringPirep(): bool
    {
        return (bool) $this->evaluate($this->canScopeToTriggeringPirep);
    }

    /**
     * Does `$tree` use the triggering-PIREP scope anywhere, including inside
     * OR groups? Save-time validation uses this to refuse such a tree under
     * `trigger = user`.
     *
     * @param array<string, mixed> $tree
     */
    public static function treeUsesTriggeringPirepScope(array $tree): bool
    {
        foreach ($tree as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (($rule['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME) {
                foreach ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? [] as $group) {
                    if (self::treeUsesTriggeringPirepScope($group['rules'] ?? [])) {
                        return true;
                    }
                }

                continue;
            }

            if (($rule['data']['settings'][PirepOperator::TRIGGERING_PIREP_SCOPE_NAME] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
