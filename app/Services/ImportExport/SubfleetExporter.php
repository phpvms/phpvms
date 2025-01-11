<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Flight;
use App\Models\Subfleet;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class SubfleetExporter extends ImportExport
{
    public $assetType = 'subfleet';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(SubfleetImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     *
     * @param Subfleet $subfleet
     */
    public function export($subfleet): array
    {
        $ret = [];
        foreach (self::$columns as $column) {
            $ret[$column] = $subfleet->{$column};
        }

        // Modify special fields
        $ret['airline'] = $subfleet->airline->icao;
        $ret['fares'] = $this->getFares($subfleet);
        $ret['ranks'] = $this->getRanks($subfleet);

        return $ret;
    }

    /**
     * Return any custom fares that have been made to this flight
     */
    protected function getFares(Subfleet &$subfleet): string
    {
        $fares = [];
        foreach ($subfleet->fares as $fare) {
            $fare_export = [];
            if ($fare->pivot->price) {
                $fare_export['price'] = $fare->pivot->price;
            }

            if ($fare->pivot->cost) {
                $fare_export['cost'] = $fare->pivot->cost;
            }

            if ($fare->pivot->capacity) {
                $fare_export['capacity'] = $fare->pivot->capacity;
            }

            $fares[$fare->code] = $fare_export;
        }

        return $this->objectToMultiString($fares);
    }

    /**
     * Return any ranks that have been linked to this subfleet
     */
    protected function getRanks(Subfleet &$subfleet): string
    {
        $ranks = [];
        foreach ($subfleet->ranks as $rank) {
            $rank_export = [];
            if ($rank->pivot->acars_pay) {
                $rank_export['acars_pay'] = $rank->pivot->acars_pay;
            }

            if ($rank->pivot->manual_pay) {
                $rank_export['manual_pay'] = $rank->pivot->manual_pay;
            }

            $ranks[$rank->id] = $rank_export;
        }

        return $this->objectToMultiString($ranks);
    }

    /**
     * Parse all of the subfields
     */
    protected function getFields(Flight &$flight): string
    {
        $ret = [];
        foreach ($flight->field_values as $field) {
            $ret[$field->name] = $field->value;
        }

        return $this->objectToMultiString($ret);
    }

    /**
     * Create the list of subfleets that are associated here
     */
    protected function getSubfleets(Flight &$flight): string
    {
        $subfleets = [];
        foreach ($flight->subfleets as $subfleet) {
            $subfleets[] = $subfleet->type;
        }

        return $this->objectToMultiString($subfleets);
    }
}
