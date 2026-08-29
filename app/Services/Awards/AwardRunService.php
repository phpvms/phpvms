<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Enums\AwardTrigger;
use App\Enums\UserState;
use App\Models\Award;
use App\Models\User;
use App\Models\UserAward;
use App\Services\Awards\Constraints\PirepConstraint;
use App\Services\Awards\Constraints\TourConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Illuminate\Support\Collection;

/**
 * Runs one rules-based award's criteria and, optionally, grants it.
 *
 * Every trigger goes through here: the admin's "Run test" (dry run) and "Run
 * now" (grant), the PIREP listener (narrowed to the accepting user) and the
 * nightly sweep (every active user). One compiled query answers all of them,
 * so the qualifying set is never assembled a user at a time.
 *
 * Two ways an award grants nothing, both deliberate and both explicit:
 *
 *  - No criteria at all. An award with no ruleset row, or one whose tree is
 *    empty, describes nobody. Compiling nothing would leave a bare `users`
 *    query, which describes *everybody* -- so the empty tree is caught here
 *    and never reaches the compiler.
 *  - Criteria that cannot be compiled exactly. `CriteriaCompiler` throws
 *    rather than dropping a rule (design D5a), and that exception is left to
 *    propagate: each caller reports it and moves on. Catching it here would
 *    make an unrunnable award indistinguishable from one nobody qualifies for.
 */
class AwardRunService
{
    public function __construct(
        protected CriteriaCompiler $compiler,
        protected AwardGrantService $grants,
    ) {}

    /**
     * Users the award newly affects -- qualifying, and not already holding it.
     *
     * @param  bool                  $grant             insert the `user_awards` rows, rather than only reporting them
     * @param  ?User                 $user              evaluate only this user (the PIREP trigger); null sweeps every active user
     * @param  ?string               $triggeringPirepId the PIREP whose acceptance drove this run, for rules scoped to it
     * @return Collection<int, User>
     *
     * @throws CriteriaCompilationFailed when the stored tree cannot be compiled faithfully
     */
    public function run(Award $award, bool $grant = false, ?User $user = null, ?string $triggeringPirepId = null): Collection
    {
        $tree = $award->rule->conditions ?? [];

        if ($tree === []) {
            return new Collection();
        }

        $query = User::query()->whereNotIn(
            'id',
            UserAward::query()->where('award_id', $award->id)->select('user_id')
        );

        if ($user instanceof User) {
            $query->whereKey($user->getKey());
        } else {
            $query->where('state', UserState::ACTIVE);
        }

        $users = $this->compiler
            ->compile($query, $tree, self::constraints($award, $triggeringPirepId), User::class)
            ->get();

        if ($grant) {
            foreach ($users as $qualifier) {
                $this->grants->grant($award, $qualifier);
            }
        }

        return $users;
    }

    /**
     * The award vocabulary: every `users` column, every saved snippet, the
     * PIREP constraint, and the tour constraint.
     *
     * The triggering-PIREP scope is offered only to `pirep`-triggered awards,
     * and applies nothing unless an id is bound -- a nightly run has no PIREP
     * to point at, so such a rule fails closed rather than widening to every
     * PIREP the user ever filed.
     *
     * @return array<int, Constraint>
     */
    public static function constraints(Award $award, ?string $triggeringPirepId = null): array
    {
        return [
            ...UserConstraints::make(),
            ...SnippetConstraints::make(),
            PirepConstraint::make()
                ->allowTriggeringPirepScope($award->trigger === AwardTrigger::Pirep)
                ->triggeringPirep($triggeringPirepId),
            TourConstraint::make(),
        ];
    }

    /**
     * Awards this trigger should evaluate: active, rules-based, in id order.
     *
     * @return Collection<int, Award>
     */
    public static function awardsFor(AwardTrigger $trigger): Collection
    {
        return Award::where('active', 1)
            ->where('trigger', $trigger)
            ->whereHas('rule')
            ->with('rule')
            ->orderBy('id')
            ->get();
    }
}
