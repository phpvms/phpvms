<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Models\FlightBundle;
use Illuminate\Validation\ValidationException;

/**
 * Builds a `CommitInput` from a validated `/commit` request payload.
 *
 * Composes `LintContextFactory` to avoid duplicating airline / event /
 * subfleet / bundle resolution. Owns commit-only concerns:
 *
 * - Resolving the attach-existing bundle in exactly one DB lookup. The
 *   Form Request already validated `exists:flight_bundles,id` (with the
 *   soft-delete filter); the previous controller did a second defensive
 *   lookup to guard against a microsecond TOCTOU window between validation
 *   and resolution. That defensive lookup is dropped — the recovery path
 *   (raising a `ValidationException`) is now anchored here and produces
 *   the same observable 422 if the row was soft-deleted in the gap.
 * - Stamping `created_by` on the unsaved bundle only in create-new mode.
 * - Extracting `fare_multiplier` (string or null) and `subfleet_ids`
 *   (list<int>) from the payload.
 * - Threading the authenticated user's id through to `causerId`.
 */
final readonly class CommitInputFactory
{
    public function __construct(
        private LintContextFactory $lintContextFactory,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public function fromValidatedPayload(array $validated, ?int $causerId): CommitInput
    {
        $bundleData = (array) ($validated['bundle'] ?? []);
        $existingBundle = $this->resolveExistingBundle($bundleData);

        $ctx = $this->lintContextFactory->fromValidatedPayload($validated, $existingBundle);

        $bundle = $ctx->bundle;
        if (!$existingBundle instanceof FlightBundle) {
            $bundle->created_by = $causerId !== null && $causerId > 0 ? $causerId : null;
        }

        /** @var list<int> $subfleetIds */
        $subfleetIds = array_map(
            static fn ($id): int => (int) $id,
            (array) ($validated['subfleet_ids'] ?? []),
        );

        // fare_multiplier is per-batch, not per-bundle (Decision 9): in
        // attach-existing mode the v1 UI hides this input, but if a client
        // submits one we still honor it because it stamps onto
        // flight_fare.price for the new flights only.
        $fareMultiplier = isset($bundleData['fare_multiplier']) && $bundleData['fare_multiplier'] !== ''
            ? (string) $bundleData['fare_multiplier']
            : null;

        // CommitInput stores the original raw row arrays (not LintRow): the
        // persistence path in RouteForgeService::commit() maps array keys
        // directly onto the Flight model. toLintContext() re-hydrates LintRow
        // for the in-transaction lint re-run. Double-hydration is microseconds.
        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = array_values((array) ($validated['rows'] ?? []));

        return new CommitInput(
            bundle: $bundle,
            existingBundle: $existingBundle,
            rows: $rawRows,
            airline: $ctx->airline,
            selectedSubfleets: $ctx->selectedSubfleets,
            event: $ctx->event,
            subfleetIds: $subfleetIds,
            fareMultiplier: $fareMultiplier,
            flightType: $ctx->flightType,
            airlineStats: $ctx->airlineStats,
            causerId: $causerId,
        );
    }

    /**
     * Resolve the attach-existing target if `bundle.existing_bundle_id` is set.
     *
     * Single lookup. Soft-deleted rows surface as a 422 with a validation
     * error keyed at `bundle.existing_bundle_id` — same observable behavior
     * as the Form Request's `exists` rule failing in the first place.
     *
     * @param array<string, mixed> $bundleData
     *
     * @throws ValidationException When existing_bundle_id is set but no longer resolves.
     */
    private function resolveExistingBundle(array $bundleData): ?FlightBundle
    {
        $existingId = $bundleData['existing_bundle_id'] ?? null;
        if ($existingId === null || $existingId === '') {
            return null;
        }

        $bundle = FlightBundle::query()->find((int) $existingId);
        if (!$bundle instanceof FlightBundle) {
            throw ValidationException::withMessages([
                'bundle.existing_bundle_id' => __('filament.routeforge.bundle.existing_missing'),
            ]);
        }

        return $bundle;
    }
}
