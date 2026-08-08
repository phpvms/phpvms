<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Enums\AwardTrigger;
use App\Events\ProcessAward;
use App\Models\Award;
use App\Models\User;
use App\Services\Awards\AwardGrantService;
use App\Services\Awards\FactResolver;
use App\Services\Awards\RuleEvaluator;

/**
 * Look for and run any of the award classes. Don't modify this.
 * See the documentation on creating awards:
 *
 * @url http://docs.phpvms.net/customizing/awards
 *
 * Also evaluates rules-based (trigger=pirep) awards for the event's user
 * (openspec/changes/rules-based-awards, D6). Legacy class-based awards keep
 * their existing path untouched.
 */
class AwardListener extends Listener // implements ShouldQueue
{
    // use Queueable;

    public function __construct(
        private readonly RuleEvaluator $evaluator,
        private readonly AwardGrantService $grantSvc,
    ) {}

    /**
     * Check for any awards to be run and test them against the user
     */
    public function handle(ProcessAward $event): void
    {
        /** @var Award[] $awards */
        $awards = Award::where('active', 1)->whereNull('conditions')->get();
        foreach ($awards as $award) {
            /** @var ?\App\Contracts\Award $klass */
            $klass = $award->getReference($award, $event->user);
            $klass?->handle();
        }

        $this->evaluateRulesBasedAwards($event->user);
    }

    /**
     * Evaluate every active, trigger=pirep rules-based award for this user.
     * One FactResolver is shared across all of them so a fact referenced
     * by multiple awards is resolved at most once (D4).
     */
    private function evaluateRulesBasedAwards(User $user): void
    {
        $awards = Award::where('active', 1)
            ->whereNotNull('conditions')
            ->where('trigger', AwardTrigger::Pirep)
            ->get();

        if ($awards->isEmpty()) {
            return;
        }

        $resolver = new FactResolver();
        $pirep = $user->last_pirep;

        foreach ($awards as $award) {
            $facts = $resolver->resolveFor($user, $this->evaluator->referencedFacts($award->conditions), $pirep);

            if ($this->evaluator->evaluate($award->conditions, $facts)) {
                $this->grantSvc->grant($award, $user);
            }
        }
    }
}
