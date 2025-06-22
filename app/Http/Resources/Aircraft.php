<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Support\Units\Fuel;
use App\Support\Units\Mass;

/**
 * @mixin \App\Models\Aircraft
 */
class Aircraft extends Resource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        $res = parent::toArray($request);
        $res['ident'] = $this->ident;

        // Dry Operating Weight
        if (!array_key_exists('dow', $res)) {
            $res['dow'] = 0;
        }
        $dow = Mass::make($res['dow'], config('phpvms.internal_units.mass'));
        $res['dow'] = $dow->getResponseUnits();

        // Maximum Zero Fuel Weight
        if (!array_key_exists('zfw', $res)) {
            $res['zfw'] = 0;
        }
        $zfw = Mass::make($res['zfw'], config('phpvms.internal_units.mass'));
        $res['zfw'] = $zfw->getResponseUnits();

        // Maximum TakeOff Weight
        if (!array_key_exists('mtow', $res)) {
            $res['mtow'] = 0;
        }
        $mtow = Mass::make($res['mtow'], config('phpvms.internal_units.mass'));
        $res['mtow'] = $mtow->getResponseUnits();

        // Maximum Landing Weight
        if (!array_key_exists('mlw', $res)) {
            $res['mlw'] = 0;
        }
        $mlw = Mass::make($res['mlw'], config('phpvms.internal_units.mass'));
        $res['mlw'] = $mlw->getResponseUnits();

        // Fuel On Board
        if (!array_key_exists('fuel_onboard', $res)) {
            $res['fuel_onboard'] = 0;
        }
        $fuel_onboard = Fuel::make($res['fuel_onboard'], config('phpvms.internal_units.fuel'));
        $res['fuel_onboard'] = $fuel_onboard->getResponseUnits();

        return $res;
    }
}
