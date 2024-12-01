<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Stats;
use App\Models\Enums\PirepState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $table = 'pireps';
        $where = [];
        $where['user_id'] = Auth::user()->id;
        $where['state'] = PirepState::ACCEPTED;
        $avgStats = ['flight_time', 'landing_rate', 'fuel_used', 'score'];
        $response = [];

        $response['flights'] = DB::table($table)->selectRaw('count(id) as count')->where($where)->value('count');
        $response['flight_time'] = DB::table($table)->selectRaw('sum(flight_time) as count')->where($where)->value('count');

        foreach ($avgStats as $static) {
            $response['average_'.$static] = DB::table($table)->selectRaw('avg('.$static.') as static')
                ->where($where)
                ->value('static');
        }

        $response['balance'] = Auth::user()->journal->balance->getValue() ?? 0;
        return new Stats((object) $response);
    }
}
