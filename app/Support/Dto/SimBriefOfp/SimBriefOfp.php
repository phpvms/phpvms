<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfp extends Dto
{
    /**
     * @param SimBriefOfpAlternate[]                   $alternate
     * @param list<SimBriefOfpNavlog[]>                $alternate_navlog
     * @param SimBriefOfpAirport[]                     $takeoff_altn
     * @param SimBriefOfpAirport[]                     $enroute_altn
     * @param SimBriefOfpNavlog[]                      $navlog
     * @param array<string, SimBriefOfpImpact|array{}> $impacts
     * @param SimBriefOfpFirNotam[]                    $notams
     * @param SimBriefOfpSigmet[]                      $sigmets
     * @param array<string, string>                    $links
     */
    public function __construct(
        public SimBriefOfpFetch $fetch,
        public SimBriefOfpParams $params,
        public SimBriefOfpGeneral $general,
        public SimBriefOfpAirport $origin,
        public SimBriefOfpAirport $destination,
        public array $alternate,
        public array $alternate_navlog,
        public array $takeoff_altn,
        public array $enroute_altn,
        public array $enroute_station,
        public array $navlog,
        public SimBriefOfpEtops|array $etops,
        public SimBriefOfpTlr $tlr,
        public SimBriefOfpAtc $atc,
        public SimBriefOfpAircraft $aircraft,
        public SimBriefOfpFuel $fuel,
        public SimBriefOfpFuelExtra $fuel_extra,
        public SimBriefOfpTimes $times,
        public SimBriefOfpWeights $weights,
        public array $impacts,
        public SimBriefOfpCrew $crew,
        public array $notams,
        public SimBriefOfpWeather $weather,
        public array $sigmets,
        public SimBriefOfpText $text,
        public SimBriefOfpTracks|array $tracks,
        public SimBriefOfpDatabaseUpdates $database_updates,
        public SimBriefOfpFiles $files,
        public SimBriefOfpFmsDownloads $fms_downloads,
        public SimBriefOfpImages $images,
        public array $links,
        public SimBriefOfpPrefile $prefile,
        public string $vatsim_prefile,
        public string $ivao_prefile,
        public string $pilotedge_prefile,
        public string $poscon_prefile,
        public string $map_data,
        public SimBriefOfpApiParams $api_params
    ) {}
}
