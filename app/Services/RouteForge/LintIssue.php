<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Services\RouteForge\Enums\LintSeverity;

/**
 * A single lint finding emitted by a LintRule.
 *
 * Issues are immutable value objects. The shape matches the JSON envelope
 * returned by the /admin/route-forge/api/lint endpoint and consumed by the
 * client-side LintReportDialog. New fields go through `$details` to avoid
 * breaking the wire shape.
 */
final readonly class LintIssue
{
    /**
     * @param string               $ruleId   Rule identifier (e.g. "L1", "L2b").
     * @param LintSeverity         $severity Backed enum; serialized as its `.value` string
     *                                       (`'error'` / `'warning'` / `'info'`) on the wire.
     * @param string               $message  Human-readable, already-translated message.
     * @param int|null             $rowIndex Zero-based index into the submitted rows array,
     *                                       or null for batch-wide issues.
     * @param array<string, mixed> $details  Free-form structured payload (rule-specific
     *                                       data the UI can render — e.g. duplicate row
     *                                       pair indices, incompatible subfleet ids).
     */
    public function __construct(
        public string $ruleId,
        public LintSeverity $severity,
        public string $message,
        public ?int $rowIndex = null,
        public array $details = [],
    ) {}

    /**
     * @return array{rule: string, severity: string, message: string, row_index: int|null, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'rule'      => $this->ruleId,
            'severity'  => $this->severity->value,
            'message'   => $this->message,
            'row_index' => $this->rowIndex,
            'details'   => $this->details,
        ];
    }
}
