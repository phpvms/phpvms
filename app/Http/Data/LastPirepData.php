<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Pirep;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The pilot's last PIREP for the dashboard last-flight widget. Property names are
 * snake_case to match the retired DashboardPresenter output (the widget reads
 * `flight_number`, `dpt_airport`, etc.).
 */
#[TypeScript]
final class LastPirepData extends Data
{
    /**
     * @param list<PirepCommentData> $comments
     */
    public function __construct(
        public string $id,
        public string $ident,
        public ?string $flight_number,
        public ?int $airline_id,
        public PirepStateData $state,
        public ?int $flight_time,
        public ?string $submitted_at,
        public ?string $created_at,
        public ?AirportPointData $dpt_airport,
        public ?AirportPointData $arr_airport,
        public ?AircraftRefData $aircraft,
        public array $comments,
    ) {}

    public static function fromModel(Pirep $p): self
    {
        return new self(
            id: $p->id,
            ident: $p->ident,
            flight_number: $p->flight_number,
            airline_id: $p->airline_id,
            state: new PirepStateData(
                value: $p->state->value,
                label: $p->state->getLabel(),
                color: self::colorToken($p->state->getColor()),
            ),
            flight_time: $p->flight_time,
            submitted_at: $p->submitted_at?->toIso8601String(),
            created_at: $p->created_at?->toIso8601String(),
            dpt_airport: self::airport($p->dpt_airport),
            arr_airport: self::airport($p->arr_airport),
            aircraft: $p->aircraft ? new AircraftRefData(
                id: $p->aircraft->id,
                registration: $p->aircraft->registration,
                name: $p->aircraft->name,
            ) : null,
            comments: $p->comments
                ->map(fn ($c): PirepCommentData => new PirepCommentData(
                    id: $c->id,
                    comment: $c->comment,
                    created_at: $c->created_at?->toIso8601String(),
                ))
                ->values()
                ->all(),
        );
    }

    private static function airport(?object $a): ?AirportPointData
    {
        return $a ? new AirportPointData(
            id: $a->id,
            icao: $a->icao,
            name: $a->name,
            lat: $a->lat !== null ? (float) $a->lat : null,
            lon: $a->lon !== null ? (float) $a->lon : null,
        ) : null;
    }

    private static function colorToken(mixed $color): string
    {
        if (is_string($color)) {
            return $color;
        }

        return is_array($color) ? (string) (array_key_first($color) ?? 'gray') : 'gray';
    }
}
