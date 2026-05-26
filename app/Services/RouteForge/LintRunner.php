<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Services\RouteForge\Contracts\LintRule;

/**
 * Orchestrates a single lint pass: runs every registered LintRule against the
 * given LintContext and aggregates the resulting issues into a LintReport.
 *
 * Rules arrive via constructor injection. Production wires the full v1
 * catalog through the `routeforge.lint_rules` container tag (see
 * `AppServiceProvider::register()`); tests are free to construct
 * `new LintRunner([$customRule, ...])` directly for a tailored subset.
 *
 * Not declared `final readonly` — tests may mock the class. `$rules` stays
 * `public` (acknowledged surface) so existing assertions that read the
 * catalog continue to work.
 */
class LintRunner
{
    /**
     * @param array<int, LintRule> $rules
     */
    public function __construct(public array $rules) {}

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
