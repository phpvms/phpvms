<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\DB;
use App\Contracts\Resource;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;

/**
 * @mixin \App\Models\Stats
 */
class Stats extends Resource
{
    public function toArray($request)
    {
        $avgLanding = DB::table('pireps')
            ->selectRaw('avg(landing_rate) as uresult')
            ->where('user_id', $this->id)
            ->where('source', PirepSource::ACARS)->where('landing_rate', '<', 0)
            ->where('state', PirepState::ACCEPTED)
            ->value('uresult');

        $avgFuel = DB::table('pireps')
            ->selectRaw('avg(fuel_used) as uresult')
            ->where('user_id', $this->id)
            ->where('state', PirepState::ACCEPTED)
            ->value('uresult');

        $avgScore = DB::table('pireps')
            ->selectRaw('avg(score) as uresult')
            ->where('user_id', $this->id)
            ->where('state', PirepState::ACCEPTED)
            ->value('uresult');

        return [
            'balance'       => $this->journal->balance->money->getValue() ?? 0,
            'avgScore'      => number_format($avgScore) ?? 0,
            'avgLanding'    => number_format($avgLanding) ?? 0,
            'avgFuel'       => number_format($avgFuel / 2.20462262185) . ' kg' ?? '',
        ];
    }
}
