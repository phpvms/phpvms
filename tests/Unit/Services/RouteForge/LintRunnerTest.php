<?php

declare(strict_types=1);

use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintContext;
use App\Services\RouteForge\LintIssue;
use App\Services\RouteForge\LintReport;
use App\Services\RouteForge\LintRunner;
use App\Services\RouteForge\Rules\L10BatchOver100;
use App\Services\RouteForge\Rules\L3EmptySubfleets;
use App\Services\RouteForge\Rules\L6OriginEqualsDestination;
use Tests\Support\RouteForgeTestHelpers as RF;

/*
 * Covers LintRunner dispatch + LintReport bucket aggregation.
 *
 * Rules themselves are exercised in tests/Unit/Services/RouteForge/Rules/*Test.php;
 * the runner test focuses on: rules iterate in registration order, every
 * issue lands in the right severity bucket, an empty rule set produces an
 * empty report, and the defaults() factory wires all 13 v1 rules.
 */

it('runs zero rules and returns an empty report', function (): void {
    $runner = new LintRunner([]);
    $report = $runner->run(RF::ctx());

    expect($report)->toBeInstanceOf(LintReport::class)
        ->and($report->errors->all())->toBe([])
        ->and($report->warnings->all())->toBe([])
        ->and($report->info->all())->toBe([])
        ->and($report->canProceed())->toBeTrue();
});

it('dispatches every registered rule against the context', function (): void {
    // Custom probe rule to verify dispatch happens once per registration.
    $probe = new class() implements LintRule
    {
        public array $callCount = [];

        public function id(): string
        {
            return 'PROBE';
        }

        public function severity(): string
        {
            return LintIssue::SEVERITY_WARNING;
        }

        public function check(LintContext $ctx): array
        {
            $this->callCount[] = $ctx->rowCount();

            return [];
        }
    };

    $runner = new LintRunner([$probe, $probe, $probe]);
    $runner->run(RF::ctx(rows: [RF::row(), RF::row(['flight_number' => 101])]));

    expect($probe->callCount)->toBe([2, 2, 2]);
});

it('aggregates rule output into severity buckets', function (): void {
    // L3 fires warning (empty subfleets), L6 fires error (origin === dest),
    // L10 stays silent (only 2 rows). Mix gives us 1 warning + 1 error.
    $runner = new LintRunner([
        new L3EmptySubfleets(),
        new L6OriginEqualsDestination(),
        new L10BatchOver100(),
    ]);

    $report = $runner->run(RF::ctx(
        rows: [
            RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KSFO']),
            RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX']),
        ],
    ));

    expect($report->errors->all())->toHaveCount(1)
        ->and($report->errors->first()->ruleId)->toBe('L6')
        ->and($report->warnings->all())->toHaveCount(1)
        ->and($report->warnings->first()->ruleId)->toBe('L3')
        ->and($report->info->all())->toBe([])
        ->and($report->canProceed())->toBeFalse();
});

it('preserves issue order within each bucket as rules emit them', function (): void {
    // Two L6 errors from a 3-row batch where rows 0 and 2 are self-loops.
    $runner = new LintRunner([new L6OriginEqualsDestination()]);

    $report = $runner->run(RF::ctx(
        rows: [
            RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KSFO']),
            RF::row(['dpt_airport_id' => 'KSFO', 'arr_airport_id' => 'KLAX']),
            RF::row(['dpt_airport_id' => 'KJFK', 'arr_airport_id' => 'KJFK']),
        ],
    ));

    expect($report->errors->all())->toHaveCount(2)
        ->and($report->errors[0]->rowIndex)->toBe(0)
        ->and($report->errors[1]->rowIndex)->toBe(2);
});

it('exposes the full v1 catalog via defaults()', function (): void {
    $runner = LintRunner::defaults();

    $ids = array_map(static fn (LintRule $rule): string => $rule->id(), $runner->rules);

    expect($ids)->toEqualCanonicalizing([
        'L1', 'L2', 'L2b', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8', 'L9', 'L10', 'L11', 'L12',
    ])
        ->and($runner->rules)->toHaveCount(13);
});

it('canProceed is true only when no errors are present', function (): void {
    $okReport = LintReport::fromIssues([
        new LintIssue('L1', LintIssue::SEVERITY_WARNING, 'soft'),
        new LintIssue('L7', LintIssue::SEVERITY_INFO, 'info'),
    ]);

    $blockedReport = LintReport::fromIssues([
        new LintIssue('L6', LintIssue::SEVERITY_ERROR, 'hard', 1),
    ]);

    expect($okReport->canProceed())->toBeTrue()
        ->and($blockedReport->canProceed())->toBeFalse();
});
