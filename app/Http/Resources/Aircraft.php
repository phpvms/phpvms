<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

/**
 * @mixin \App\Models\Aircraft
 */
class Aircraft extends Resource
{
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['ident'] = $this->ident;

        // Set these to the response units
        $res['dow'] = $this->dow->getResponseUnits();
        $res['zfw'] = $this->zfw->getResponseUnits();
        $res['mtow'] = $this->mtow->getResponseUnits();
        $res['mlw'] = $this->mlw->getResponseUnits();
        $res['fuel_onboard'] = $this->fuel_onboard->getResponseUnits();

        return $res;
    }
}
