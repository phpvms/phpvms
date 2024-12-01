<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Stats;
use App\Models\Enums\PirepState;
use App\Models\Pirep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $where = [];
        $where['user_id'] = Auth::id();
        $where['state'] = PirepState::ACCEPTED;
        $avgStats = ['flight_time', 'landing_rate', 'fuel_used', 'score'];
        $response = [];

        $response['flights'] = Pirep::where($where)->count();
        $response['flight_time'] = Pirep::where($where)->count();

        foreach ($avgStats as $static) {
            $response['average_'.$static] = Pirep::where($where)->avg($static);
        }

        $response['balance'] = Auth::user()->journal->balance->getValue() ?? 0;
        return new Stats((object) $response);
    }
}
