<?php

declare(strict_types=1);

namespace App\Features\Pireps\Livewire\Forms;

use App\Enums\FlightType;
use App\Models\Fare;
use App\Models\Pirep;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The PIREP form's fields, the validation rules for a draft and for a
 * submission, and the payload `SaveManualPirep` takes.
 *
 * `fields` holds the custom field values by slug and `fares` the passenger or
 * cargo counts by fare id.
 */
class PirepFormData extends Form
{
    public ?string $airline_id = null;

    public ?string $flight_number = null;

    public ?string $route_code = null;

    public ?string $route_leg = null;

    public string $flight_type = '';

    public ?string $hours = null;

    public ?string $minutes = null;

    public ?string $level = null;

    public ?string $dpt_airport_id = null;

    public ?string $arr_airport_id = null;

    public ?string $aircraft_id = null;

    public ?string $block_fuel = null;

    public ?string $fuel_used = null;

    public ?string $route = null;

    public ?string $notes = null;

    /** @var array<string, string|null> */
    public array $fields = [];

    /** @var array<int|string, string|null> */
    public array $fares = [];

    /**
     * Fill the form from a PIREP: a saved one when editing, or one built from a
     * flight or SimBrief plan when filing.
     */
    public function fillFromPirep(Pirep $pirep): void
    {
        $this->airline_id = filled($pirep->airline_id) ? (string) $pirep->airline_id : null;
        $this->flight_number = filled($pirep->flight_number) ? (string) $pirep->flight_number : null;
        $this->route_code = $pirep->route_code;
        $this->route_leg = filled($pirep->route_leg) ? (string) $pirep->route_leg : null;
        $flightType = $pirep->getAttribute('flight_type');
        $this->flight_type = ($flightType instanceof FlightType ? $flightType : FlightType::SCHED_PAX)->value;
        $this->level = $pirep->level === null ? null : (string) $pirep->level;
        $this->dpt_airport_id = $pirep->dpt_airport_id;
        $this->arr_airport_id = $pirep->arr_airport_id;
        $this->aircraft_id = $pirep->aircraft_id === null ? null : (string) $pirep->aircraft_id;
        $this->route = $pirep->route;
        $this->notes = $pirep->notes;

        if ($pirep->exists) {
            $this->block_fuel = $pirep->block_fuel === null ? null : (string) $pirep->block_fuel;
            $this->fuel_used = $pirep->fuel_used === null ? null : (string) $pirep->fuel_used;
            $this->hours = (string) intdiv((int) $pirep->flight_time, 60);
            $this->minutes = (string) ((int) $pirep->flight_time % 60);
        }
    }

    /**
     * Put the locked fields back to what the PIREP has saved, and leave the
     * rest as entered.
     */
    public function restoreLocked(Pirep $pirep): void
    {
        $route = $this->route;
        $notes = $this->notes;

        $this->fillFromPirep($pirep);

        $this->route = $route;
        $this->notes = $notes;
    }

    /**
     * Keep the counts already entered for fares that still apply; every other
     * fare starts blank.
     *
     * @param Collection<int, Fare> $fares
     * @param array<int, string>    $seed  counts to use for a fare that has none entered yet
     */
    public function syncFares(Collection $fares, array $seed = []): void
    {
        $this->fares = $fares
            ->mapWithKeys(fn (Fare $fare): array => [$fare->id => $this->fares[$fare->id] ?? $seed[$fare->id] ?? ''])
            ->all();
    }

    /**
     * @param  list<string>         $requiredSlugs the required custom fields that are shown
     * @param  list<string>         $optionalSlugs the other custom fields that are shown
     * @return array<string, mixed>
     */
    public function rulesFor(bool $draft, array $requiredSlugs, array $optionalSlugs): array
    {
        if ($draft) {
            return [
                'airline_id'     => 'required|exists:airlines,id',
                'flight_number'  => 'required|integer|max_digits:4',
                'dpt_airport_id' => 'required',
                'arr_airport_id' => 'required',
                'hours'          => 'nullable|integer|min:0',
                'minutes'        => 'nullable|integer|min:0',
                'level'          => 'nullable|numeric',
                'block_fuel'     => 'nullable|numeric',
                'fuel_used'      => 'nullable|numeric',
                'fares.*'        => 'nullable|integer|min:0',
            ];
        }

        $rules = Pirep::$rules;
        unset($rules['event_id']);

        $rules['hours'] = 'required|integer|min:0';
        $rules['minutes'] = 'required|integer|min:0';
        $rules['flight_type'] = ['required', Rule::enum(FlightType::class)];
        $rules['fares.*'] = 'nullable|integer|min:0';

        foreach ($requiredSlugs as $slug) {
            $rules['fields.'.$slug] = 'required';
        }

        foreach ($optionalSlugs as $slug) {
            $rules['fields.'.$slug] = 'nullable';
        }

        return $rules;
    }

    /**
     * The payload `SaveManualPirep` reads: PIREP columns, `hours`/`minutes`,
     * custom fields under their slug and fares as `fare_{id}`. Blank values are
     * null, as they arrive from an HTTP form.
     *
     * @param  list<string>         $editableSlugs the custom fields the pilot may write
     * @return array<string, mixed>
     */
    public function payload(array $editableSlugs): array
    {
        $payload = [
            'airline_id'     => $this->airline_id,
            'flight_number'  => $this->flight_number,
            'route_code'     => $this->route_code,
            'route_leg'      => $this->route_leg,
            'flight_type'    => $this->flight_type,
            'hours'          => $this->hours,
            'minutes'        => $this->minutes,
            'level'          => $this->level,
            'dpt_airport_id' => $this->dpt_airport_id,
            'arr_airport_id' => $this->arr_airport_id,
            'aircraft_id'    => $this->aircraft_id,
            'block_fuel'     => $this->block_fuel,
            'fuel_used'      => $this->fuel_used,
            'route'          => $this->route,
            'notes'          => $this->notes,
        ];

        foreach ($editableSlugs as $slug) {
            $payload[$slug] = $this->fields[$slug] ?? null;
        }

        foreach ($this->fares as $fareId => $count) {
            $payload['fare_'.$fareId] = $count;
        }

        return array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $payload);
    }
}
