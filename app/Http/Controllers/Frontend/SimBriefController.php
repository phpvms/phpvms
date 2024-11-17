<?php

namespace App\Http\Controllers\Frontend;

use App\Exceptions\AssetNotFound;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\Enums\FareType;
use App\Models\Enums\FlightType;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\SimBriefLayout;
use App\Models\User;
use App\Repositories\FlightRepository;
use App\Services\FareService;
use App\Services\ModuleService;
use App\Services\SimBriefService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SimBriefController
{
    public function __construct(
        private readonly FareService $fareSvc,
        private readonly FlightRepository $flightRepo,
        private readonly ModuleService $moduleSvc,
        private readonly SimBriefService $simBriefSvc,
        private readonly UserService $userSvc
    ) {
    }

    /**
     * Show the main OFP form
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return RedirectResponse|View
     */
    public function generate(Request $request): RedirectResponse|View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $flight_id = $request->input('flight_id');
        $aircraft_id = $request->input('aircraft_id');

        /** @var Flight $flight */
        $flight = $this->flightRepo->with(['airline', 'arr_airport', 'dpt_airport', 'fares', 'subfleets'])->find($flight_id);

        if (!$flight) {
            flash()->error('Unknown flight');
            return redirect(route('frontend.flights.index'));
        }

        $apiKey = setting('simbrief.api_key');
        if (empty($apiKey)) {
            flash()->error('Invalid SimBrief API key!');
            return redirect(route('frontend.flights.index'));
        }

        // Generate SimBrief Static ID
        $static_id = $user->ident.'_'.$flight->id;

        // No aircraft selected, try to find one from a bid
        if (!$aircraft_id) {
            $bid = Bid::where(['user_id' => $user->id, 'flight_id' => $flight_id])->first();
            if ($bid) {
                $aircraft_id = $bid->aircraft_id;
            }
        }

        // No aircraft selected, show selection form
        if (!$aircraft_id) {
            // Get user's allowed subfleets and intersect it with flight subfleets
            // so we will have a proper list which the user is allowed to fly
            $user_subfleets = $this->userSvc->getAllowableSubfleets($user)->pluck('id')->toArray();
            $flight_subfleets = $flight->subfleets->pluck('id')->toArray();

            $subfleet_ids = filled($flight_subfleets) ? array_intersect($user_subfleets, $flight_subfleets) : $user_subfleets;

            // Prepare variables for single aircraft query
            $where = [];
            $where['state'] = AircraftState::PARKED;
            $where['status'] = AircraftStatus::ACTIVE;

            if (setting('pireps.only_aircraft_at_dpt_airport')) {
                $where['airport_id'] = $flight->dpt_airport_id;
            }

            $withCount = ['simbriefs' => function ($query) {
                $query->whereNull('pirep_id');
            }];

            // Build proper aircraft collection considering all possible settings
            // Flight subfleets, user subfleet restrictions, pirep restrictions, simbrief blocking etc
            $aircraft = Aircraft::withCount($withCount)->with(['sbaircraft', 'sbairframes'])->where($where)
                ->when(setting('simbrief.block_aircraft'), function ($query) {
                    return $query->having('simbriefs_count', 0);
                })->whereIn('subfleet_id', $subfleet_ids)
                ->orderby('icao')->orderby('registration')
                ->get();

            return view('flights.simbrief_aircraft', [
                'flight'    => $flight,
                'aircrafts' => $aircraft,
            ]);
        }

        // Check if a Simbrief profile already exists
        $simbrief = SimBrief::select('id')->where([
            'flight_id' => $flight_id,
            'user_id'   => $user->id,
        ])->first();

        if ($simbrief) {
            return redirect(route('frontend.simbrief.briefing', [$simbrief->id]));
        }

        // SimBrief profile does not exists and everything else is ok
        // Select aircraft which will be used for calculations and details
        /** @var Aircraft $aircraft */
        $aircraft = Aircraft::with(['airline', 'sbaircraft', 'sbairframes'])->where('id', $aircraft_id)->first();

        // Figure out the proper fares to use for this flight/aircraft
        $all_fares = $this->fareSvc->getFareWithOverrides($aircraft->subfleet->fares, $flight->fares);

        // TODO: Reconcile the fares for this aircraft w/ proper for the flight/subfleet

        // Get passenger and baggage weights with failsafe defaults
        if ($flight->flight_type === FlightType::CHARTER_PAX_ONLY) {
            $pax_weight = setting('simbrief.charter_pax_weight', 168);
            $bag_weight = setting('simbrief.charter_baggage_weight', 28);
        } else {
            $pax_weight = setting('simbrief.noncharter_pax_weight', 185);
            $bag_weight = setting('simbrief.noncharter_baggage_weight', 35);
        }

        // Get the load factors with failsafe for loadmax if nothing is defined
        $lfactor = $flight->load_factor ?? setting('flights.default_load_factor');
        $lfactorv = $flight->load_factor_variance ?? setting('flights.load_factor_variance');

        $loadmin = $lfactor - $lfactorv;
        $loadmin = max($loadmin, 0);

        $loadmax = $lfactor + $lfactorv;
        $loadmax = min($loadmax, 100);

        if ($loadmax === 0) {
            $loadmax = 100;
        }

        if (setting('flights.use_cargo_load_factor ', false)) {
            $cgolfactor = $flight->load_factor ?? setting('flights.default_cargo_load_factor');
            $cgolfactorv = $flight->load_factor_variance ?? setting('flights.cargo_load_factor_variance');

            $cgoloadmin = $cgolfactor - $cgolfactorv;
            $cgoloadmin = max($cgoloadmin, 0);

            $cgoloadmax = $cgolfactor + $cgolfactorv;
            $cgoloadmax = min($cgoloadmax, 100);

            if ($cgoloadmax === 0) {
                $cgoloadmax = 100;
            }
        } else {
            $cgoloadmin = $loadmin;
            $cgoloadmax = $loadmax;
        }

        // Load fares for passengers

        $loaddist = []; // The load distribution string

        $pax_load_sheet = [];
        $tpaxfig = 0;
        $acd_maxpax = 0;

        /** @var Fare $fare */
        foreach ($all_fares as $fare) {
            if ($fare->type !== FareType::PASSENGER || empty($fare->capacity)) {
                continue;
            }

            $acd_maxpax = $acd_maxpax + $fare->capacity;
            $count = floor(($fare->capacity * rand($loadmin, $loadmax)) / 100);
            $tpaxfig += $count;
            $pax_load_sheet[] = [
                'id'       => $fare->id,
                'code'     => $fare->code,
                'name'     => $fare->name,
                'type'     => $fare->type,
                'capacity' => (int) $fare->capacity,
                'count'    => $count,
            ];

            $loaddist[] = $fare->code.' '.$count;
        }

        // Calculate and convert weights according to SimBrief requirements
        if (setting('units.weight') === 'kg') {
            $tpaxload = round(($pax_weight * $tpaxfig) / 2.205);
            $tbagload = round(($bag_weight * $tpaxfig) / 2.205);
        } else {
            $tpaxload = round($pax_weight * $tpaxfig);
            $tbagload = round($bag_weight * $tpaxfig);
        }

        // Load up fares for cargo
        $tcargoload = 0;
        $cargo_load_sheet = [];
        foreach ($all_fares as $fare) {
            if ($fare->type !== FareType::CARGO || empty($fare->capacity)) {
                continue;
            }

            $count = ceil((($fare->capacity - $tbagload) * rand($cgoloadmin, $cgoloadmax)) / 100);
            $tcargoload += $count;
            $cargo_load_sheet[] = [
                'id'       => $fare->id,
                'code'     => $fare->code,
                'name'     => $fare->name,
                'type'     => $fare->type,
                'capacity' => $fare->capacity,
                'count'    => $count,
            ];

            $loaddist[] = $fare->code.' '.$count;
        }

        $tpayload = $tpaxload + $tbagload + $tcargoload;

        $request->session()->put('simbrief_fares', array_merge($pax_load_sheet, $cargo_load_sheet));

        // Prepare SimBrief layouts, acdata json and actype code
        $layouts = SimBriefLayout::get();

        $acdata = [
            // Passenger and Baggage Weights needs to pounds, integer like 185
            'paxwgt' => $pax_weight,
            'bagwgt' => $bag_weight,
            // Airframe Weights needs to be thousands of pounds, with 3 digit precision like 85.715
            'mzfw'    => (filled($aircraft->zfw) && $aircraft->zfw->internal(0) > 0) ? round($aircraft->zfw->internal(0) / 1000, 3) : null,
            'mtow'    => (filled($aircraft->mtow) && $aircraft->mtow->internal(0) > 0) ? round($aircraft->mtow->internal(0) / 1000, 3) : null,
            'mlw'     => (filled($aircraft->mlw) && $aircraft->mlw->internal(0) > 0) ? round($aircraft->mlw->internal(0) / 1000, 3) : null,
            'hexcode' => filled($aircraft->hex_code) ? $aircraft->hex_code : null,
            'maxpax'  => $acd_maxpax,
        ];

        $actype = (filled($aircraft->simbrief_type)) ? $aircraft->simbrief_type : ((filled(optional($aircraft->subfleet)->simbrief_type)) ? $aircraft->subfleet->simbrief_type : $aircraft->icao);
        $sbaircraft = filled($aircraft->sbaircraft) ? collect(json_decode($aircraft->sbaircraft->details)) : null;
        $sbairframes = (setting('simbrief.use_custom_airframes', false)) ? $aircraft->sbairframes->where('source', 'Custom') : $aircraft->sbairframes;

        // Show the main simbrief form
        return view('flights.simbrief_form', [
            'user'             => $user,
            'flight'           => $flight,
            'aircraft'         => $aircraft,
            'pax_weight'       => $pax_weight,
            'bag_weight'       => $bag_weight,
            'loadmin'          => $loadmin,
            'loadmax'          => $loadmax,
            'pax_load_sheet'   => $pax_load_sheet,
            'cargo_load_sheet' => $cargo_load_sheet,
            'tpaxfig'          => $tpaxfig,
            'tpaxload'         => $tpaxload,
            'tbagload'         => $tbagload,
            'tpayload'         => $tpayload,
            'tcargoload'       => $tcargoload,
            'loaddist'         => implode(' ', $loaddist).' BAG '.$tbagload,
            'static_id'        => $static_id,
            'sbaircraft'       => $sbaircraft,
            'sbairframes'      => filled($sbairframes) ? $sbairframes : null,
            'acdata'           => json_encode($acdata),
            'actype'           => $actype,
            'layouts'          => $layouts,
        ]);
    }

    /**
     * Show the briefing
     *
     * @param string $id The OFP ID
     *
     * @return RedirectResponse|View
     */
    public function briefing(string $id): RedirectResponse|View
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var SimBrief $simbrief */
        $simbrief = SimBrief::with(['flight.airline', 'pirep.airline'])->find($id);
        if (!$simbrief) {
            flash()->error('SimBrief briefing not found');
            return redirect(route('frontend.flights.index'));
        }

        $str = $simbrief->xml->aircraft->equip;
        $wc = stripos($str, '-');
        $tr = stripos($str, '/');
        $wakecat = substr($str, 0, $wc);
        $equipment = substr($str, $wc + 1, $tr - 2);
        $transponder = substr($str, $tr + 1);

        // See if a bid exists for this flight
        $bid = Bid::where(['user_id' => $user->id, 'flight_id' => $simbrief->flight_id])->first();

        return view('flights.simbrief_briefing', [
            'user'         => $user,
            'simbrief'     => $simbrief,
            'wakecat'      => $wakecat,
            'equipment'    => $equipment,
            'transponder'  => $transponder,
            'bid'          => $bid,
            'flight'       => $simbrief->flight,
            'acars_plugin' => $this->moduleSvc->isModuleActive('VMSAcars'),
        ]);
    }

    /**
     * Remove the flight_id from the SimBrief Briefing (to a create a new one)
     * or if no pirep_id is attached to the briefing delete it completely
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     *
     * @return RedirectResponse
     */
    public function generate_new(Request $request): RedirectResponse
    {
        $simbrief = SimBrief::find($request->id);

        // Invalid Simbrief ID/profile, go back to the main flight index
        if (!$simbrief) {
            return redirect(route('frontend.flights.index'));
        }

        // Cleanup the current Simbrief entry and redirect to the new generation form
        // If there isn't a PIREP ID, then delete the entry, otherwise, remove the flight
        $flight_id = $simbrief->flight_id;
        if (!$simbrief->pirep_id) {
            $simbrief->delete();
        } else {
            $simbrief->flight_id = null;
            $simbrief->save();
        }

        return redirect(route('frontend.simbrief.generate').'?flight_id='.$flight_id);
    }

    /**
     * Create a prefile of this PIREP with a given OFP. Then redirect the
     * user to the newly prefiled PIREP
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return RedirectResponse
     */
    public function prefile(Request $request): RedirectResponse
    {
        $sb = SimBrief::find($request->id);
        if (!$sb) {
            return redirect(route('frontend.flights.index'));
        }

        // Redirect to the prefile page, with the flight_id and a simbrief_id
        $rd = route('frontend.pireps.create').'?sb_id='.$sb->id;
        return redirect($rd);
    }

    /**
     * Cancel the SimBrief request
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return RedirectResponse
     */
    public function cancel(Request $request): RedirectResponse
    {
        $sb = SimBrief::find($request->id);
        if (!$sb) {
            $sb->delete();
        }

        return redirect(route('frontend.simbrief.prefile', ['id' => $request->id]));
    }

    /**
     * Check whether the OFP was generated. Pass in two items, the flight_id and ofp_id
     * This does the actual "attachment" of the Simbrief to the flight
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return JsonResponse
     */
    public function check_ofp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $ofp_id = $request->input('ofp_id');
        $flight_id = $request->input('flight_id');
        $aircraft_id = $request->input('aircraft_id');
        $fares = $request->session()->get('simbrief_fares', []);

        $simbrief = $this->simBriefSvc->downloadOfp($user->id, $ofp_id, $flight_id, $aircraft_id, $fares);
        if ($simbrief === null) {
            $error = new AssetNotFound(new Exception('Simbrief OFP not found'));
            return $error->getResponse();
        }

        return response()->json([
            'id' => $simbrief->id,
        ]);
    }

    /**
     * Get the latest generated OFP. Pass in two additional items, the Simbrief userid and static_id
     * This will get the latest edited/regenerated of from Simbrief and update our records
     * We do not need to send the fares again, so used an empty array
     *
     * @param Request $request
     *
     * @return RedirectResponse|JsonResponse
     */
    public function update_ofp(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $ofp_id = $request->input('ofp_id');
        $flight_id = $request->input('flight_id');
        $aircraft_id = $request->input('aircraft_id');
        $sb_userid = $request->input('sb_userid');
        $sb_static_id = $request->input('sb_static_id');
        $fares = [];

        $simbrief = $this->simBriefSvc->downloadOfp($user->id, $ofp_id, $flight_id, $aircraft_id, $fares, $sb_userid, $sb_static_id);
        if ($simbrief === null) {
            $error = new AssetNotFound(new Exception('Simbrief OFP not found'));
            return $error->getResponse();
        }

        return redirect(route('frontend.simbrief.briefing', [$ofp_id]));
    }

    /**
     * Generate the API code
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     *
     * @return RedirectResponse|JsonResponse
     */
    public function api_code(Request $request): RedirectResponse|JsonResponse
    {
        $apiKey = setting('simbrief.api_key', null);
        if (empty($apiKey)) {
            flash()->error('Invalid SimBrief API key!');
            return redirect(route('frontend.flights.index'));
        }

        $api_code = md5($apiKey.$request->input('api_req'));

        return response()->json([
            'api_code' => $api_code,
        ]);
    }
}
