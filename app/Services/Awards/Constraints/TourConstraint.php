<?php

declare(strict_types=1);

namespace App\Services\Awards\Constraints;

use App\Services\Awards\Constraints\Operators\TourCountOperator;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\Support\Icons\Heroicon;

/**
 * Everything an award can say about a user's completed tours (design D-tour).
 *
 * Mirrors `PirepConstraint`'s reasoning (design D3): Filament applies each
 * rule as its own `whereHas` (`QueryBuilder::applyRulesToQuery()`), so two
 * stock dotted rules about "a tour" would compile to two independent
 * subqueries and could match two *different* runs. This constraint therefore
 * carries the whole inner rule tree into a single subquery so every inner
 * condition describes the same tour run.
 *
 * The user correlation and `status = Completed` are added by the operator and
 * are not expressible from a submitted tree.
 *
 * Unlike PIREPs, there is no triggering-record scope here: a tour completes
 * inside `PirepEventsSubscriber::handlePirepFiled()`, which calls
 * `TourService::advance()` before `PirepService::accept()` fires
 * `ProcessAward` -- so a `trigger = pirep` award already evaluates after the
 * tour row has flipped to Completed, with no need to bind "this run" the way
 * the PIREP constraint binds "this PIREP".
 */
class TourConstraint extends Constraint
{
    /** The `User` relationship every operator correlates against. */
    public const string RELATIONSHIP = 'tours';

    public static function getDefaultName(): ?string
    {
        return 'tours';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tours');

        $this->icon(Heroicon::Map);

        $this->operators([
            TourCountOperator::class,
        ]);
    }
}
