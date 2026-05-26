<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\Rules\L10BatchOver100;
use App\Services\RouteForge\Rules\L11AirportTimezoneMissing;
use App\Services\RouteForge\Rules\L12ExistingDuplicateCrossBundle;
use App\Services\RouteForge\Rules\L1AircraftCapacity;
use App\Services\RouteForge\Rules\L2bTypeMismatch;
use App\Services\RouteForge\Rules\L2RangeMismatch;
use App\Services\RouteForge\Rules\L3EmptySubfleets;
use App\Services\RouteForge\Rules\L4DuplicateFlightNumbersInBatch;
use App\Services\RouteForge\Rules\L5ExistingDuplicate;
use App\Services\RouteForge\Rules\L6OriginEqualsDestination;
use App\Services\RouteForge\Rules\L7SubfleetsHaveNoFares;
use App\Services\RouteForge\Rules\L8EventDatesOutsideWindow;
use App\Services\RouteForge\Rules\L9BatchOver50;

/**
 * Orchestrates a single lint pass: runs every registered LintRule against the
 * given LintContext and aggregates the resulting issues into a LintReport.
 *
 * Rules are passed in via constructor for explicit DI control. The static
 * `defaults()` factory wires the full v1 catalog and is the production path
 * used by the controller and commit pipeline; tests are free to construct a
 * runner with a tailored subset of rules.
 */
final readonly class LintRunner
{
    /**
     * @param list<LintRule> $rules
     */
    public function __construct(public array $rules) {}

    /**
     * Build a runner with the full v1 lint catalog.
     *
     * Order is documentation-only — rules do not depend on each other and
     * issue ordering inside the report is not part of the public contract.
     */
    public static function defaults(): self
    {
        return new self([
            new L1AircraftCapacity(),
            new L2RangeMismatch(),
            new L2bTypeMismatch(),
            new L3EmptySubfleets(),
            new L4DuplicateFlightNumbersInBatch(),
            new L5ExistingDuplicate(),
            new L6OriginEqualsDestination(),
            new L7SubfleetsHaveNoFares(),
            new L8EventDatesOutsideWindow(),
            new L9BatchOver50(),
            new L10BatchOver100(),
            new L11AirportTimezoneMissing(),
            new L12ExistingDuplicateCrossBundle(),
        ]);
    }

    public function run(LintContext $ctx): LintReport
    {
        $issues = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->check($ctx) as $issue) {
                $issues[] = $issue;
            }
        }

        return LintReport::fromIssues($issues);
    }
}
