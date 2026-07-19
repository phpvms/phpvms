<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Fare;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\Typerating;
use App\Services\FareService;
use App\Services\FleetService;
use Exception;

/**
 * Import subfleets
 */
class SubfleetImporter extends ImportExport
{
    public string $assetType = 'subfleet';

    /**
     * All of the columns that are in the CSV import
     * Should match the database fields, for the most part
     */
    public static array $columns = [
        'airline'                    => 'required',
        'hub_id'                     => 'nullable',
        'type'                       => 'required',
        'simbrief_type'              => 'nullable',
        'name'                       => 'required',
        'fuel_type'                  => 'nullable',
        'cost_block_hour'            => 'nullable',
        'cost_delay_minute'          => 'nullable',
        'ground_handling_multiplier' => 'nullable',
        'fares'                      => 'nullable',
        'ranks'                      => 'nullable',
        'type_ratings'               => 'nullable',
    ];

    private readonly FareService $fareSvc;

    private readonly FleetService $fleetSvc;

    /**
     * FlightImportExporter constructor.
     */
    public function __construct()
    {
        $this->fareSvc = app(FareService::class);
        $this->fleetSvc = app(FleetService::class);
    }

    /**
     * Import a flight, parse out the different rows
     */
    public function import(array $row, int $index): bool
    {
        $airline = $this->getAirline($row['airline']);
        $row['airline_id'] = $airline->id;

        $row['fuel_type'] = $row['fuel_type'] ? (int) $row['fuel_type'] : null;

        try {
            $subfleet = Subfleet::updateOrCreate([
                'type' => $row['type'],
            ], $row);
        } catch (Exception $exception) {
            $this->errorLog('Error in row '.($index + 1).': '.$exception->getMessage());

            return false;
        }

        $this->processFares($subfleet, $row['fares'] ?? '');
        $this->processRanks($subfleet, $row['ranks'] ?? '');
        $this->processTypeRatings($subfleet, $row['type_ratings'] ?? '');

        $this->log('Imported '.$row['type']);

        return true;
    }

    /**
     * Parse all of the fares in the multi-format
     */
    protected function processFares(Subfleet $subfleet, string $col): void
    {
        $fares = $this->parseMultiColumnValues($col);
        foreach ($fares as $fare_code => $fare_attributes) {
            if (\is_int($fare_code)) {
                $fare_code = $fare_attributes;
                $fare_attributes = [];
            }

            $fare = Fare::firstOrCreate(['code' => $fare_code], ['name' => $fare_code]);
            $this->fareSvc->setForSubfleet($subfleet, $fare, $fare_attributes);
            $fare->save();
        }
    }

    /**
     * Parse all of the rakns in the multi-format
     */
    protected function processRanks(Subfleet $subfleet, string $col): void
    {
        $ranks = $this->parseMultiColumnValues($col);
        foreach ($ranks as $rank_id => $rank_attributes) {
            if (!\is_array($rank_attributes)) {
                $rank_id = $rank_attributes;
                $rank_attributes = [];
            }

            $rank = Rank::firstOrCreate(['id' => $rank_id], ['name' => 'Imported rank '.$rank_id]);
            $this->fleetSvc->addSubfleetToRank($subfleet, $rank, $rank_attributes);
            $rank->save();
        }
    }

    /**
     * Parse all of the type ratings in the multi-format
     *
     * The typerating_subfleet pivot has no extra columns, so the value is a
     * simple ;-delimited list of type rating IDs.
     */
    protected function processTypeRatings(Subfleet $subfleet, string $col): void
    {
        $type_ratings = $this->parseMultiColumnValues($col);
        foreach ($type_ratings as $typerating_id => $typerating_attributes) {
            if (!\is_array($typerating_attributes)) {
                $typerating_id = $typerating_attributes;
            }

            $typerating = Typerating::find($typerating_id);
            if ($typerating === null) {
                $typerating = new Typerating([
                    'name' => 'Imported type rating '.$typerating_id,
                    'type' => 'Imported type rating '.$typerating_id,
                ]);
                $typerating->id = (int) $typerating_id;
                $typerating->save();
            }

            $this->fleetSvc->addSubfleetToTypeRating($subfleet, $typerating);
        }
    }
}
