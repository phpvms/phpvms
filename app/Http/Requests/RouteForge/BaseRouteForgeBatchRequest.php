<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteForge;

use App\Contracts\FormRequest;
use App\Enums\FlightType;
use App\Models\FlightBundle;
use Illuminate\Validation\Rule;

/**
 * Shared rule + normalization base for LintRequest and CommitRequest.
 *
 * Both endpoints accept the same batch envelope (airline + event + subfleets
 * + flight_type + bundle + origins + destinations + rows). CommitRequest adds
 * fare_multiplier and on_conflict on top. Keeping the shared rules here
 * means one place to tighten cross-field invariants when the spec evolves.
 *
 * Cross-field constraints enforced here (per the carry-forward decision in
 * the Section 4 banner — server now relies on Form Request + LintRunner +
 * atomic txn, no PHP regenerator):
 *
 *   - rows.*.airline_id MUST equal top-level airline_id.
 *   - rows.*.dpt_airport_id and rows.*.arr_airport_id MUST appear in either
 *     `origins` or `destinations` (the user-picked airport scope).
 *   - subfleet_ids.* MUST belong to the chosen airline.
 *
 * The first two are evaluated against $this->input(...) at rules() call
 * time. The subfleet ownership constraint is expressed declaratively via
 * `Rule::exists('subfleets', 'id')->where('airline_id', ...)` — the database
 * scopes per row, no PHP-side enumeration / pre-load of allowed ids inside
 * `rules()` itself. `rules()` runs zero Eloquent queries.
 *
 * Empty / missing scopes (e.g. malformed payload) fall through harmlessly:
 * Rule::in([]) rejects every value for the airline/airport scopes, and the
 * scoped Rule::exists matches nothing when airline_id is null, so a row
 * pointing at an empty scope fails closed with a clear "field is invalid"
 * error.
 */
abstract class BaseRouteForgeBatchRequest extends FormRequest
{
    /**
     * Subclasses MAY override to layer additional rules onto the base set.
     */
    #[\Override]
    public function rules(): array
    {
        $maxRows = (int) config('phpvms.routeforge.mesh_max_count', 100);
        $airlineId = $this->input('airline_id');
        $allowedAirlineIds = $airlineId !== null ? [$airlineId] : [];
        $allowedIcaos = $this->buildIcaoScope();

        return [
            'airline_id'  => ['required', 'integer', 'exists:airlines,id'],
            'event_id'    => ['nullable', 'integer', 'exists:events,id'],
            'flight_type' => ['nullable', Rule::enum(FlightType::class)],
            // 'present' (not 'required') so an empty array passes — L3 lint
            // catches "no subfleets selected" downstream as a warning rather
            // than blocking the payload at the validation layer.
            'subfleet_ids' => ['present', 'array'],
            // Scoped exists: the DB does the airline-ownership check per row.
            // When airline_id is null/invalid the query becomes
            // `WHERE airline_id IS NULL` and matches no real subfleets —
            // fail-closed, parallel to the prior `Rule::in([])` behavior.
            'subfleet_ids.*' => [
                'integer',
                Rule::exists('subfleets', 'id')->where(
                    fn ($q) => $q->where('airline_id', $this->input('airline_id'))
                ),
            ],

            'origins'        => ['required', 'array', 'min:1'],
            'origins.*'      => ['string', 'size:4', 'alpha', 'exists:airports,id'],
            'destinations'   => ['required', 'array', 'min:1'],
            'destinations.*' => ['string', 'size:4', 'alpha', 'exists:airports,id'],

            'bundle'                    => ['required', 'array'],
            'bundle.existing_bundle_id' => [
                'nullable',
                'integer',
                Rule::exists('flight_bundles', 'id')->whereNull('deleted_at'),
            ],
            // Name + enabled are required ONLY in create-new mode (when no
            // existing bundle is selected). When existing_bundle_id is set
            // the server ignores these fields and reads from the existing
            // bundle row.
            'bundle.name'        => ['required_without:bundle.existing_bundle_id', 'nullable', 'string', 'max:255'],
            'bundle.description' => ['nullable', 'string'],
            'bundle.enabled'     => ['required_without:bundle.existing_bundle_id', 'nullable', 'boolean'],
            'bundle.start_date'  => ['nullable', 'date'],
            'bundle.end_date'    => ['nullable', 'date', 'after_or_equal:bundle.start_date'],

            'rows'                  => ['required', 'array', 'min:1', 'max:'.$maxRows],
            'rows.*.airline_id'     => ['required', 'integer', Rule::in($allowedAirlineIds)],
            'rows.*.flight_number'  => ['required', 'integer', 'min:1', 'max:9999'],
            'rows.*.callsign'       => ['nullable', 'string', 'max:32'],
            'rows.*.route_code'     => ['nullable', 'string', 'max:5'],
            'rows.*.route_leg'      => ['nullable', 'integer', 'min:0', 'max:255'],
            'rows.*.dpt_airport_id' => ['required', 'string', 'size:4', 'alpha', Rule::in($allowedIcaos)],
            'rows.*.arr_airport_id' => ['required', 'string', 'size:4', 'alpha', Rule::in($allowedIcaos)],
            'rows.*.departure_time' => ['nullable', 'string', 'date_format:H:i'],
            'rows.*.arrival_time'   => ['nullable', 'string', 'date_format:H:i'],
            'rows.*.distance_nm'    => ['nullable', 'numeric', 'min:0'],
            'rows.*.flight_time'    => ['nullable', 'integer', 'min:0'],
            'rows.*.flight_type'    => ['nullable', Rule::enum(FlightType::class)],
            'rows.*.days'           => ['nullable', 'integer', 'min:0', 'max:127'],
            'rows.*.notes'          => ['nullable', 'string', 'max:1000'],
            'rows.*.level'          => ['nullable', 'integer', 'min:0', 'max:60000'],
            'rows.*.start_date'     => ['nullable', 'date'],
            'rows.*.end_date'       => ['nullable', 'date', 'after_or_equal:rows.*.start_date'],
        ];
    }

    /**
     * Custom error messages for the shared batch rules.
     *
     * Centralized so /lint and /commit emit consistent wording for the
     * nested row + bundle scopes that fan out across many keys via
     * wildcards. Subclasses MAY override to layer on additional keys.
     *
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return [
            'airline_id.exists'                => __('validation.exists', ['attribute' => 'airline']),
            'subfleet_ids.*.exists'            => __('filament.routeforge.validation.subfleet_not_in_airline'),
            'origins.required'                 => __('filament.routeforge.validation.origins_required'),
            'origins.*.exists'                 => __('filament.routeforge.validation.airport_unknown'),
            'destinations.required'            => __('filament.routeforge.validation.destinations_required'),
            'destinations.*.exists'            => __('filament.routeforge.validation.airport_unknown'),
            'bundle.existing_bundle_id.exists' => __('filament.routeforge.bundle.existing_missing'),
            'bundle.name.required_without'     => __('filament.routeforge.validation.bundle_name_required'),
            'bundle.enabled.required_without'  => __('filament.routeforge.validation.bundle_enabled_required'),
            'bundle.end_date.after_or_equal'   => __('filament.routeforge.validation.bundle_dates_inverted'),
            'rows.required'                    => __('filament.routeforge.validation.rows_required'),
            'rows.max'                         => __('filament.routeforge.validation.rows_too_many'),
            'rows.*.airline_id.in'             => __('filament.routeforge.validation.row_airline_mismatch'),
            'rows.*.flight_number.min'         => __('filament.routeforge.validation.flight_number_min'),
            'rows.*.flight_number.max'         => __('filament.routeforge.validation.flight_number_max'),
            'rows.*.dpt_airport_id.in'         => __('filament.routeforge.validation.row_airport_out_of_scope'),
            'rows.*.arr_airport_id.in'         => __('filament.routeforge.validation.row_airport_out_of_scope'),
            'rows.*.end_date.after_or_equal'   => __('filament.routeforge.validation.row_dates_inverted'),
        ];
    }

    /**
     * Attribute-bag key under which the pre-resolved attach-existing
     * `FlightBundle` is stashed by `passedValidation()` below. Downstream
     * factories (`LintContextFactory`, `CommitInputFactory`) read from here
     * to avoid a second DB lookup of a bundle the Form Request already
     * `exists`-validated and resolved.
     */
    public const string RESOLVED_BUNDLE_ATTRIBUTE = 'routeforge.existing_bundle';

    /**
     * Resolve the attach-existing `FlightBundle` exactly once (after the
     * `bundle.existing_bundle_id` `exists` rule has passed) and stash it on
     * the request attribute bag. Both the lint controller and commit
     * controller pull from here and pass the resolved instance down into
     * their respective factories.
     *
     * The previous wiring did this lookup in two places — once in
     * `LintContextFactory::hydrateUnsavedBundle` (lint path) and once in
     * `CommitInputFactory::resolveExistingBundle` (commit path) — duplicating
     * work on every commit and adding an extra round-trip per /lint POST
     * (which the SPA's auto-lint effect fires on every keystroke).
     *
     * If a microsecond TOCTOU window between validation and lookup
     * soft-deletes the row, the stash stays null and the factories'
     * existing recovery paths handle the miss (commit raises 422 via
     * `CommitInputFactory::resolveExistingBundle`; lint falls through to
     * request-body bundle data).
     */
    #[\Override]
    protected function passedValidation(): void
    {
        $existingId = $this->input('bundle.existing_bundle_id');
        if ($existingId === null || $existingId === '') {
            return;
        }

        $bundle = FlightBundle::query()->find((int) $existingId);
        if ($bundle instanceof FlightBundle) {
            $this->attributes->set(self::RESOLVED_BUNDLE_ATTRIBUTE, $bundle);
        }
    }

    /**
     * Pre-resolved attach-existing bundle, or null if the batch is
     * creating a new bundle (or the soft-delete TOCTOU path landed).
     */
    public function resolvedExistingBundle(): ?FlightBundle
    {
        $bundle = $this->attributes->get(self::RESOLVED_BUNDLE_ATTRIBUTE);

        return $bundle instanceof FlightBundle ? $bundle : null;
    }

    /**
     * Normalize all ICAO-bearing fields to uppercase before validation runs.
     * Mirrors the at-rest convention enforced by the `Flight` model's
     * `dptAirportId` / `arrAirportId` mutators so `exists` + Rule::in
     * checks succeed regardless of casing on the wire.
     */
    #[\Override]
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['origins', 'destinations'] as $field) {
            $values = $this->input($field);
            if (is_array($values)) {
                $merge[$field] = array_map(
                    fn ($v): mixed => is_string($v) ? strtoupper(trim($v)) : $v,
                    $values,
                );
            }
        }

        $rows = $this->input('rows');
        if (is_array($rows)) {
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach (['dpt_airport_id', 'arr_airport_id'] as $col) {
                    if (isset($row[$col]) && is_string($row[$col])) {
                        $rows[$index][$col] = strtoupper(trim($row[$col]));
                    }
                }
            }

            $merge['rows'] = $rows;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Merge origins and destinations into a deduplicated list of allowed
     * ICAOs. Empty / non-array inputs collapse to an empty list (which
     * Rule::in then rejects everything against — fail-closed by default).
     *
     * @return list<string>
     */
    private function buildIcaoScope(): array
    {
        $origins = $this->input('origins', []);
        $destinations = $this->input('destinations', []);

        $merged = array_merge(
            is_array($origins) ? $origins : [],
            is_array($destinations) ? $destinations : [],
        );

        $strings = array_filter($merged, is_string(...));

        return array_values(array_unique($strings));
    }
}
