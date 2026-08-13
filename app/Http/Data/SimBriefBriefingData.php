<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use App\Models\SimBrief;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SimBriefBriefingData extends Data
{
    /**
     * @param array<string, string>                        $weather
     * @param array<int, array{name: string, url: string}> $downloads
     * @param array<int, array{name: string, url: string}> $images
     * @param array<string, string>                        $prefileLinks
     */
    public function __construct(
        public string $id,
        public FlightDetailData $flight,
        public ?BidData $bid,
        public EligibleAircraftData $aircraft,
        public string $route,
        public string $atcPlan,
        public string $textOfp,
        public array $weather,
        public array $downloads,
        public array $images,
        public array $prefileLinks,
        public ?string $editorUrl,
        public bool $canCancel,
        public bool $canRegenerate,
    ) {}

    public static function fromModel(SimBrief $briefing, ?Bid $bid): self
    {
        $briefing->loadMissing(['aircraft.airport', 'aircraft.subfleet', 'flight.airline', 'flight.arr_airport', 'flight.dpt_airport', 'flight.alt_airport']);
        $ofp = $briefing->ofp;

        return new self(
            id: $briefing->id,
            flight: FlightDetailData::fromModel($briefing->flight, $bid ? [$briefing->flight_id => $bid->id] : []),
            bid: $bid ? BidData::fromModel($bid) : null,
            aircraft: EligibleAircraftData::fromModel($briefing->aircraft),
            route: $ofp?->general->route ?? '',
            atcPlan: $ofp?->atc->flightplan_text ?? '',
            textOfp: $ofp?->text->plan_html ?? '',
            weather: [
                'departureMetar' => $ofp?->weather->orig_metar ?? '',
                'departureTaf'   => $ofp?->weather->orig_taf ?? '',
                'arrivalMetar'   => $ofp?->weather->dest_metar ?? '',
                'arrivalTaf'     => $ofp?->weather->dest_taf ?? '',
            ],
            downloads: $briefing->files->values()->all(),
            images: $briefing->images->values()->all(),
            prefileLinks: [
                'ivao'      => $ofp?->prefile->ivao->link ?? '',
                'pilotEdge' => $ofp?->prefile->pilotedge->link ?? '',
                'poscon'    => $ofp?->prefile->poscon->link ?? '',
                'vatsim'    => $ofp?->prefile->vatsim->link ?? '',
            ],
            editorUrl: filled($briefing->static_id)
                ? 'https://www.simbrief.com/system/dispatch.php?editflight=last&static_id='.$briefing->static_id
                : null,
            canCancel: $briefing->pirep_id === null,
            canRegenerate: true,
        );
    }
}
