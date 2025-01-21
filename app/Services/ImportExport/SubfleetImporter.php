<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Fare;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Services\FareService;
use App\Services\FleetService;

/**
 * Import subfleets.
 */
class SubfleetImporter extends ImportExport
{
    public $assetType = 'subfleet';

    /**
     * All of the columns that are in the CSV import
     * Should match the database fields, for the most part.
     */
    public static $columns = [
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
    ];

    private FareService $fareSvc;

    private FleetService $fleetSvc;

    /**
     * FlightImportExporter constructor.
     */
    public function __construct()
    {
        $this->fareSvc = app(FareService::class);
        $this->fleetSvc = app(FleetService::class);
    }

    /**
     * Import a flight, parse out the different rows.
     *
     * @param int $index
     */
    public function import(array $row, $index): bool
    {
        $airline = $this->getAirline($row['airline']);
        $row['airline_id'] = $airline->id;

        try {
            $subfleet = Subfleet::updateOrCreate([
                'type' => $row['type'],
            ], $row);
        } catch (\Exception $e) {
            $this->errorLog('Error in row '.($index + 1).': '.$e->getMessage());

            return false;
        }

        $this->processFares($subfleet, $row['fares']);
        $this->processRanks($subfleet, $row['ranks']);

        $this->log('Imported '.$row['type']);

        return true;
    }

    /**
     * Parse all of the fares in the multi-format.
     */
    protected function processFares(Subfleet &$subfleet, $col): void
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
     * Parse all of the rakns in the multi-format.
     */
    protected function processRanks(Subfleet &$subfleet, $col): void
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
}
