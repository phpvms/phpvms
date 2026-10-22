<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use App\Models\SimBrief;
use App\Support\Dto\SimBriefOfp\SimBriefOfp;
use App\Support\Dto\SimBriefOfp\SimBriefOfpNavlog;
use App\Support\SimBriefPlanHtml;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SimBriefBriefingData extends Data
{
    /**
     * @param array<string, string>                        $weather
     * @param array<int, array{name: string, url: string}> $downloads
     * @param array<int, array{name: string, url: string}> $images
     * @param list<array{title: string, html: string}>     $textSections
     * @param array<string, string>                        $prefileLinks
     * @param list<MapPlannedFixData>                      $plannedFixes
     */
    public function __construct(
        public string $id,
        public FlightDetailData $flight,
        public ?BidData $bid,
        public EligibleAircraftData $aircraft,
        public string $route,
        public array $plannedFixes,
        public string $atcPlan,
        public array $textSections,
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
            plannedFixes: self::plannedFixes($ofp),
            atcPlan: $ofp?->atc->flightplan_text ?? '',
            textSections: SimBriefPlanHtml::sections($ofp?->text->plan_html ?? ''),
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

    /**
     * Planned route fixes for the briefing map, straight from the live OFP's
     * navlog — no `buildNavlog()`/archive dependency, so per-fix altitude is
     * always present here (map-api spec, "Planned fixes carry altitude").
     * Reuses `MapPlannedFixData`, the same shape the PIREP detail payload
     * uses: one fix type across both consumers rather than two near-identical
     * ones (D10).
     *
     * @return list<MapPlannedFixData>
     */
    private static function plannedFixes(?SimBriefOfp $ofp): array
    {
        if (!$ofp instanceof SimBriefOfp) {
            return [];
        }

        return array_map(
            static fn (SimBriefOfpNavlog $fix): MapPlannedFixData => new MapPlannedFixData(
                ident: $fix->ident,
                lat: $fix->pos_lat,
                lon: $fix->pos_long,
                altitudeFt: $fix->altitude_feet,
                viaAirway: $fix->via_airway,
                isSidStar: $fix->is_sid_star,
            ),
            $ofp->navlog,
        );
    }
}
