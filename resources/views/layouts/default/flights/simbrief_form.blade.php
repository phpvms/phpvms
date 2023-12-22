@extends('app')
@section('title', 'SimBrief Flight Planning')

@section('content')

  <form id="sbapiform">
    <div class="row">
      <h2>Create Simbrief Briefing</h2>
      <div class="card">
        <div class="col-md-12">
          <div class="row">
            <div class="col-8">
              <div class="form-container">

                <div class="form-container-body">
                  <h6><i class="fas fa-info-circle"></i>&nbsp;Aircraft Details</h6>
                  <div class="row">
                    <div class="col-sm-4">
                      <label for="type">Type</label>
                      <input type="text" class="form-control" value="{{ $aircraft->icao }}" maxlength="4" disabled>
                      @if(filled($aircraft->simbrief_type))
                        <input type="hidden" name="type" value="{{ $aircraft->simbrief_type }}">
                      @elseif(filled($aircraft->subfleet->simbrief_type))
                        <input type="hidden" name="type" value="{{ $aircraft->subfleet->simbrief_type }}">
                      @else
                        <input type="hidden" name="type" value="{{ $aircraft->icao }}">
                      @endif
                    </div>
                    <div class="col-sm-4">
                      <label for="reg">Registration</label>
                      <input type="text" class="form-control" value="{{ $aircraft->registration }}" maxlength="6" disabled>
                      <input type="hidden" name="reg" value="{{ $aircraft->registration }}">
                    </div>
                  </div>
                  <br>
                </div>

                <div class="form-container-body">
                  <h6><i class="fas fa-info-circle"></i>&nbsp;@lang('pireps.flightinformations') For
                  <b>{{ $flight->airline->code }}{{ $flight->flight_number }} ({{ \App\Models\Enums\FlightType::label($flight->flight_type) }})</b></h6>
                  <div class="row">
                    <div class="col-sm-4">
                      <label for="dorig">Departure Airport</label>
                      <input id="dorig" type="text" class="form-control" maxlength="4" value="{{ $flight->dpt_airport_id }}" disabled>
                      <input name="orig" type="hidden" maxlength="4" value="{{ $flight->dpt_airport_id }}">
                    </div>
                    <div class="col-sm-4">
                      <label for="ddest">Arrival Airport</label>
                      <input id="ddest" type="text" class="form-control" maxlength="4" value="{{ $flight->arr_airport_id }}" disabled>
                      <input name="dest" type="hidden" maxlength="4" value="{{ $flight->arr_airport_id }}">
                    </div>
                    <div class="col-sm-4">
                      <label for="altn">Alternate Airport</label>
                      <input name="altn" type="text" class="form-control" maxlength="4" value="{{ $flight->alt_airport_id ?? 'AUTO' }}">
                    </div>
                  </div>
                  <br>
                  <div class="row">
                    <div class="col-sm-8">
                      <label for="route">Preferred Company Route</label>
                      <input name="route" type="text" class="form-control" value="{{ $flight->route }}">
                    </div>
                    <div class="col-sm-4">
                      <label for="fl">Preferred Flight Level</label>
                      <input id="fl" name="fl" type="text" class="form-control" maxlength="5" value="{{ $flight->level }}">
                    </div>
                  </div>
                  <br>
                  <div class="row">
                    <div class="col-sm-4">
                      @if($flight->dpt_time)
                        <label for="std">Scheduled Departure Time (UTC)</label>
                        <input id="std" type="text" class="form-control" maxlength="4" value="{{ $flight->dpt_time }}" disabled>
                      @endif
                    </div>
                    <div class="col-sm-4">
                      <label for="etd">Estimated Departure Time (UTC)</label>
                      <input id="etd" type="text" class="form-control" maxlength="4" disabled>
                    </div>
                    <div class="col-sm-4">
                      <label for="dof">Date Of Flight (UTC)</label>
                      <input id="dof" type="text" class="form-control" maxlength="4" disabled>
                    </div>
                  </div>
                  <br>
                </div>

                <div class="form-container-body">
                  <h6><i class="fas fa-info-circle"></i>&nbsp;Configuration And Load Information For
                  <b>{{ $aircraft->registration }} ({{ $aircraft->subfleet->name }})</b></h6>
                  <div class="row">
                    @foreach($pax_load_sheet as $fare)
                      <div class="col-sm-3">
                          <label for="LoadFare{{ $fare['id'] }}">{{ $fare['name'] }} [Max: {{ $fare['capacity'] }}]</label>
                          <input id="LoadFare{{ $fare['id'] }}" type="text" class="form-control" value="{{ $fare['count'] }}" disabled>
                      </div>
                    @endforeach
                    {{-- Generate Load Figures For Cargo Fares --}}
                    @foreach($cargo_load_sheet as $fare)
                      <div class="col-sm-3">
                        <label for="LoadFare{{ $fare['id'] }}">{{ $fare['name'] }} [Max: {{ number_format($fare['capacity'] - $tbagload) }} {{ setting('units.weight') }}]</label>
                        <input id="LoadFare{{ $fare['id'] }}" type="text" class="form-control" value="{{ number_format($fare['count']) }}" disabled>
                      </div>
                    @endforeach
                  </div>
                  @if(isset($tpayload) && $tpayload > 0)
                    {{-- Display The Weights Generated --}}
                    <br>
                    <div class="row">
                      @if($tpaxload)
                        <div class="col-sm-3">
                          <label for="tdPaxLoad">Pax Weight</label>
                          <input id="tdPaxLoad" type="text" class="form-control" value="{{ number_format($tpaxload) }} {{ setting('units.weight') }}" disabled>
                        </div>
                        <div class="col-sm-3">
                          <label for="tBagLoad">Baggage Weight</label>
                          <input id="tBagLoad" type="text" class="form-control" value="{{ number_format($tbagload) }} {{ setting('units.weight') }}" disabled>
                        </div>
                      @endif
                      @if($tpaxload && $tcargoload)
                        <div class="col-sm-3">
                          <label for="tCargoload">Cargo Weight</label>
                          <input id="tCargoload" type="text" class="form-control" value="{{ number_format($tcargoload) }} {{ setting('units.weight') }}" disabled>
                        </div>
                      @endif
                      <div class="col-sm-3">
                        <label for="tPayload">Total Payload</label>
                        <input id="tPayload" type="text" class="form-control" value="{{ number_format($tpayload) }} {{ setting('units.weight') }}" disabled>
                      </div>
                    </div>
                  @endif
                </div>
              </div>

              {{-- Prepare Form Fields For SimBrief --}}
                <input type="hidden" name="acdata" value="{'paxwgt':{{ round($pax_weight) }}, 'bagwgt': {{ round($bag_weight) }}}">
                @if($tpaxfig)
                  <input type="hidden" name="pax" value="{{ $tpaxfig }}">
                @elseif(!$tpaxfig && $tcargoload)
                  <input type="hidden" name="pax" value="0">
                @endif
                @if($tcargoload)
                  <input type='hidden' name='cargo' value="{{ number_format(($tcargoload / 1000),1) }}">
                @endif
              @if(isset($tpayload) && $tpayload > 0)
                <input type="hidden" name="manualrmk" value="Load Distribution {{ $loaddist }}">
              @endif
              <input type="hidden" name="airline" value="{{ $flight->airline->icao }}">
              <input type="hidden" name="fltnum" value="{{ $flight->flight_number }}">
              @if(setting('simbrief.name_private', true))
                <input type="hidden" name="cpt" value="{{ $user->name_private }}">
              @endif
              <input type="hidden" id="steh" name="steh" maxlength="2">
              <input type="hidden" id="stem" name="stem" maxlength="2">
              <input type="hidden" id="date" name="date" maxlength="9">
              <input type="hidden" id="deph" name="deph" maxlength="2">
              <input type="hidden" id="depm" name="depm" maxlength="2">
              <input type="hidden" name="selcal" value="{{ $aircraft->selcal ?? 'FK-NS'}}">
              <input type="hidden" name="planformat" value="lido">
              <input type="hidden" name="omit_sids" value="0">
              <input type="hidden" name="omit_stars" value="0">
              <input type="hidden" name="cruise" value="CI">
              <input type="hidden" name="civalue" value="AUTO">
              <input type="hidden" name="static_id" value="{{ $static_id }}">
              {{-- For more info about form fields and their details check SimBrief Forum / API Support --}}
            </div>
            <div class="col-4">
              <div class="form-container">
                <div class="form-container-body">
                  <h6><i class="fas fa-info-circle"></i>&nbsp;Planning Options</h6>
                  <table class="table table-sm table-striped">
                    <tr>
                      <td>ATC Callsign:</td>
                      <td>
                        @if(setting('simbrief.callsign', true))
                          {{ $user->ident }}
                          <input type="hidden" name="callsign" value="{{ $user->ident }}">
                        @else
                          <select name="callsign" class="form-control">
                            @if(filled($flight->callsign))
                              <option value="{{ optional($flight->airline)->icao.$flight->callsign }}" selected>{{ optional($flight->airline)->icao.$flight->callsign }}</option>
                            @endif
                              <option value="{{ optional($flight->airline)->icao.$flight->flight_number }}">{{ optional($flight->airline)->icao.$flight->flight_number }}</option>
                            @if(filled($user->callsign))
                              <option value="{{ optional($flight->airline)->icao.$user->callsign }}">{{ optional($flight->airline)->icao.$user->callsign }}</option>
                            @endif
                              <option value="{{ $user->ident }}">{{ $user->ident }}</option>
                          </select>
                        @endif
                      </td>
                    </tr>
                    <tr>
                      <td>Cont Fuel:</td>
                      <td>
                        <select name="contpct" class="form-control">
                          <option value="0">None</option>
                          <option value="auto">AUTO</option>
                          <option value="easa">EASA</option>
                          <option value="0.03/5">3% or 05 MIN</option>
                          <option value="0.03/10">3% or 10 MIN</option>
                          <option value="0.03/15">3% or 15 MIN</option>
                          <option value="0.05/5" selected>5% or 05 MIN</option>
                          <option value="0.05/10">5% or 10 MIN</option>
                          <option value="0.05/15">5% or 15 MIN</option>
                          <option value="0.03">3%</option>
                          <option value="0.05">5%</option>
                          <option value="0.1">10%</option>
                          <option value="0.15">15%</option>
                          <option value="3">03 MIN</option>
                          <option value="5">05 MIN</option>
                          <option value="10">10 MIN</option>
                          <option value="15">15 MIN</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>Reserve Fuel:</td>
                      <td>
                        <select name="resvrule" class="form-control">
                          <option value="auto">AUTO</option>
                          <option value="0">0 MIN</option>
                          <option value="15">15 MIN</option>
                          <option value="30" selected>30 MIN</option>
                          <option value="45">45 MIN</option>
                          <option value="60">60 MIN</option>
                          <option value="75">75 MIN</option>
                          <option value="90">90 MIN</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>SID/STAR Type:</td>
                      <td>
                        <select name="find_sidstar" class="form-control">
                          <option value="C">Conventional</option>
                          <option value="R" selected>RNAV</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>Plan Stepclimbs:</td>
                      <td>
                        <select id="stepclimbs" name="stepclimbs" class="form-control" onchange="DisableFL()">
                          <option value="0" selected>Disabled</option>
                          <option value="1">Enabled</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>ETOPS Planning:</td>
                      <td>
                        <select name="etops" class="form-control">
                          <option value="0" selected>Disabled</option>
                          <option value="1">Enabled</option>
                        </select>
                      </td>
                    </tr>
                  </table>
                </div>
                <br>
                <div class="form-container-body">
                  <h6><i class="fas fa-info-circle"></i>&nbsp;Briefing Options</h6>
                  <table class="table table-sm table-striped">
                    <tr>
                      <td>Units:</td>
                      <td>
                        <select id="kgslbs" name="units" class="form-control">
                          @if(setting('units.weight') === 'kg')
                            <option value="KGS" selected>KGS</option>
                            <option value="LBS">LBS</option>
                          @else
                            <option value="KGS">KGS</option>
                            <option value="LBS" selected>LBS</option>
                          @endif
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>Detailed Navlog:</td>
                      <td>
                        <select name="navlog" class="form-control">
                          <option value="0">Disabled</option>
                          <option value="1" selected>Enabled</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>Runway Analysis:</td>
                      <td>
                        <select name="tlr" class="form-control">
                          <option value="0">Disabled</option>
                          <option value="1" selected>Enabled</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>Include NOTAMS:</td>
                      <td>
                        <select name="notams" class="form-control">
                          <option value="0">Disabled</option>
                          <option value="1" selected>Enabled</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>FIR NOTAMS:</td>
                      <td>
                        <select name="firnot" class="form-control">
                          <option value="0" selected>Disabled</option>
                          <option value="1">Enabled</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td>Flight Maps:</td>
                      <td>
                        <select name="maps" class="form-control">
                          <option value="detail" selected>Detailed</option>
                          <option value="simple">Simple</option>
                          <option value="none">None</option>
                        </select>
                      </td>
                    </tr>
                  </table>
                </div>
                <br>
                <div class="form-container-body">
                  <div class="float-right">
                    <div class="form-group">
                      <input type="button"
                         onclick="simbriefsubmit('{{ $flight->id }}', '{{ $aircraft->id }}', '{{ url(route('frontend.simbrief.briefing', [''])) }}');"
                         class="btn btn-primary" value="Generate">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection
@section('scripts')
<script src="{{public_asset('/assets/global/js/simbrief.apiv1.js')}}"></script>
<script type="text/javascript">
  // ******
  // Disable Submitting a fixed flight level for Stepclimb option to work
  // Script is related to Plan Step Climbs selection
  function DisableFL() {
    let climb = document.getElementById("stepclimbs").value;
    if (climb === "0") {
      document.getElementById("fl").disabled = false
    }

    if (climb === "1") {
      document.getElementById("fl").disabled = true
    }
  }
</script>
<script type="text/javascript">
  // ******
  // Get current UTC time, add 45 minutes to it and format according to Simbrief API
  // Script also rounds the minutes to nearest 5 to avoid a Departure time like 1538 ;)
  // If you need to reduce the margin of 45 mins, change value below
  let d = new Date();
  const months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
  d.setMinutes(d.getMinutes() + 45); // Change the value here
  let deph = ("0" + d.getUTCHours(d)).slice(-2);
  let depm = d.getUTCMinutes(d);
  if (depm < 55) {
    depm = Math.ceil(depm / 5) * 5;
  }

  if (depm > 55) {
    depm = Math.floor(depm / 5) * 5;
  }

  depm = ("0" + depm).slice(-2);
  dept = deph + ":" + depm;
  let dof = ("0" + d.getUTCDate()).slice(-2) + months[d.getUTCMonth()] + d.getUTCFullYear();

  document.getElementById("dof").setAttribute('value', dof);
  document.getElementById("etd").setAttribute('value', dept);
  document.getElementById("date").setAttribute('value', dof); // Sent to Simbrief
  document.getElementById("deph").setAttribute('value', deph); // Sent to SimBrief
  document.getElementById("depm").setAttribute('value', depm); // Sent to SimBrief
</script>
<script type="text/javascript">
  // ******
  // Calculate the Scheduled Enroute Time for Simbrief API
  // Your PHPVMS flight_time value must be from BLOCK to BLOCK
  // Including departure and arrival taxi times
  // If this value is not correctly calculated and configured
  // Simbrief CI (Cost Index) calculation will not provide realistic results
  let num = {{ $flight->flight_time }};
  let hours = (num / 60);
  let rhours = Math.floor(hours);
  let minutes = (hours - rhours) * 60;
  let rminutes = Math.round(minutes);
  document.getElementById("steh").setAttribute('value', rhours.toString()); // Sent to Simbrief
  document.getElementById("stem").setAttribute('value', rminutes.toString()); // Sent to Simbrief
</script>
@endsection
