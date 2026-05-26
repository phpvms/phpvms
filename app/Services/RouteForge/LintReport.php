<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Services\RouteForge\Enums\LintSeverity;
use Illuminate\Support\Collection;

/**
 * Aggregated result of running every LintRule against a LintContext.
 *
 * Splits issues by severity for fast consumer access (the API endpoint serializes
 * warnings/errors separately, the UI badges them differently, and the commit
 * gate only inspects errors). canProceed() encodes the single commit-gate rule:
 * any error blocks commit; warnings do not.
 */
final readonly class LintReport
{
    /**
     * @param Collection<int, LintIssue> $errors
     * @param Collection<int, LintIssue> $warnings
     * @param Collection<int, LintIssue> $info
     */
    public function __construct(
        public Collection $errors,
        public Collection $warnings,
        public Collection $info,
    ) {}

    /**
     * Build a report from a flat issue list, bucketing by severity.
     *
     * @param iterable<LintIssue> $issues
     */
    public static function fromIssues(iterable $issues): self
    {
        $errors = new Collection();
        $warnings = new Collection();
        $info = new Collection();

        foreach ($issues as $issue) {
            match ($issue->severity) {
                LintSeverity::Error   => $errors->push($issue),
                LintSeverity::Warning => $warnings->push($issue),
                LintSeverity::Info    => $info->push($issue),
            };
        }

        return new self($errors, $warnings, $info);
    }

    /**
     * Commit-gate predicate: true iff no errors are present.
     *
     * Server-side commit re-runs lint inside the transaction and aborts the
     * request with HTTP 422 when this returns false.
     */
    public function canProceed(): bool
    {
        return $this->errors->isEmpty();
    }

    /**
     * Flattened issue list across all severities, in (errors, warnings, info)
     * order. Used by serializers that want a single stream.
     *
     * @return Collection<int, LintIssue>
     */
    public function all(): Collection
    {
        return $this->errors
            ->concat($this->warnings)
            ->concat($this->info)
            ->values();
    }

    /**
     * @return array{errors: list<array<string, mixed>>, warnings: list<array<string, mixed>>, info: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'errors'   => $this->errors->map(fn (LintIssue $i): array => $i->toArray())->values()->all(),
            'warnings' => $this->warnings->map(fn (LintIssue $i): array => $i->toArray())->values()->all(),
            'info'     => $this->info->map(fn (LintIssue $i): array => $i->toArray())->values()->all(),
        ];
    }
}
