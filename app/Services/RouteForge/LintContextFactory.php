<?php

declare(strict_types=1);

namespace App\Services\RouteForge;

use App\Enums\FlightType;
use App\Models\Airline;
use App\Models\Event;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Illuminate\Support\Collection;

/**
 * Builds a `LintContext` from a validated `/lint` (or `/commit`) request
 * payload.
 *
 * Owns the previously controller-private logic for resolving the airline /
 * event / subfleet models, hydrating an unsaved `FlightBundle` from the
 * `bundle` array (with the dual-mode mirror for attach-existing), and
 * embedding the airline stats snapshot. Returned `LintContext` is
 * immutable.
 *
 * Lookup discipline:
 *
 * - When `$existingBundle` is passed in (commit path, already resolved by
 *   `CommitInputFactory`), the factory does NOT re-query `flight_bundles`.
 * - When `$existingBundle` is null but the validated payload carries
 *   `bundle.existing_bundle_id` (lint path), the factory issues a single
 *   `FlightBundle::find()` so date-dependent rules (L8) still read the
 *   existing window. If the find misses (soft-deleted between validation
 *   and lookup), the factory falls back to the request-body bundle data —
 *   linting is non-destructive, so a stale id surfaces as L8 emitting
 *   against the request's own date columns rather than blowing up.
 */
final readonly class LintContextFactory
{
    public function __construct(
        private AirlineStatsService $statsService,
    ) {}

    /**
     * @param array<string, mixed> $validated
     */
    public function fromValidatedPayload(array $validated, ?FlightBundle $existingBundle = null): LintContext
    {
        $airline = Airline::query()->findOrFail((int) $validated['airline_id']);

        $eventId = $validated['event_id'] ?? null;
        $event = $eventId !== null ? Event::query()->find((int) $eventId) : null;

        /** @var list<int> $subfleetIds */
        $subfleetIds = array_map(
            static fn ($id): int => (int) $id,
            (array) ($validated['subfleet_ids'] ?? []),
        );
        $selectedSubfleets = $subfleetIds === []
            ? new Collection()
            : Subfleet::query()
                ->whereIn('id', $subfleetIds)
                ->with(['aircraft', 'fares'])
                ->get();

        $flightType = isset($validated['flight_type'])
            ? FlightType::tryFrom((string) $validated['flight_type'])
            : null;

        $bundle = $this->hydrateUnsavedBundle((array) ($validated['bundle'] ?? []), $existingBundle);

        /** @var list<array<string, mixed>> $rawRows */
        $rawRows = array_values((array) ($validated['rows'] ?? []));
        $rows = [];
        foreach ($rawRows as $index => $rawRow) {
            $rows[] = LintRow::fromArray($index, $rawRow);
        }

        return new LintContext(
            bundle: $bundle,
            rows: $rows,
            selectedSubfleets: $selectedSubfleets,
            airline: $airline,
            event: $event,
            airlineStats: $this->statsService->buildFor($airline),
            flightType: $flightType,
        );
    }

    /**
     * Build an unsaved FlightBundle from the validated `bundle` payload.
     *
     * Dual-mode:
     *
     * - When `$existingBundle` is supplied (caller already resolved it),
     *   mirror its values into a fresh unsaved instance. No DB lookup.
     * - When `$existingBundle` is null but the payload carries
     *   `bundle.existing_bundle_id`, do exactly one `FlightBundle::find()`
     *   to recover the date window for L8. Miss → fall through to the
     *   request-body bundle data (non-destructive: lint doesn't persist).
     * - Otherwise, build an unsaved bundle from the request-body fields.
     *
     * @param array<string, mixed> $bundleData
     */
    private function hydrateUnsavedBundle(array $bundleData, ?FlightBundle $existingBundle): FlightBundle
    {
        if ($existingBundle instanceof FlightBundle) {
            return $this->mirrorBundle($existingBundle);
        }

        $existingId = $bundleData['existing_bundle_id'] ?? null;
        if ($existingId !== null && $existingId !== '') {
            $resolved = FlightBundle::query()->find((int) $existingId);
            if ($resolved instanceof FlightBundle) {
                return $this->mirrorBundle($resolved);
            }
        }

        return new FlightBundle([
            'name'        => (string) ($bundleData['name'] ?? ''),
            'description' => $bundleData['description'] ?? null,
            'enabled'     => (bool) ($bundleData['enabled'] ?? false),
            'start_date'  => $bundleData['start_date'] ?? null,
            'end_date'    => $bundleData['end_date'] ?? null,
        ]);
    }

    /**
     * Clone an existing bundle's lint-relevant columns into a fresh unsaved
     * instance. Keeps the request-body bundle clean of persistence state.
     */
    private function mirrorBundle(FlightBundle $existing): FlightBundle
    {
        return new FlightBundle([
            'name'        => $existing->name,
            'description' => $existing->description,
            'enabled'     => $existing->enabled,
            'start_date'  => $existing->start_date,
            'end_date'    => $existing->end_date,
        ]);
    }
}
