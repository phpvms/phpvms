@extends('app')
@section('title', 'Briefing')

@section('content')
  <div class="row">
    <div class="col-sm-6">
      <h2>{{ $simbrief->xml->general->icao_airline }}{{ $simbrief->xml->general->flight_number }}
        : {{ $simbrief->xml->origin->icao_code }} to {{ $simbrief->xml->destination->icao_code }}</h2>
    </div>
    <div class="col">
      @if (empty($simbrief->pirep_id))
        <a class="btn btn-outline-info pull-right btn-lg"
           style="margin-top: -10px; margin-bottom: 5px"
           href="{{ url(route('frontend.simbrief.prefile', [$simbrief->id])) }}">Prefile PIREP</a>
      @endif
    </div>
    @if (!empty($simbrief->xml->params->static_id) && $user->id === $simbrief->user_id)
    <div class="col">
        <a class="btn btn-secondary btn-lg"
           style="margin-top: -10px; margin-bottom: 5px"
           href="#"
           data-toggle="modal" data-target="#OFP_Edit">Edit OFP</a>
    </div>
    @endif
    <div class="col">
      <a class="btn btn-primary btn-lg"
         style="margin-top: -10px; margin-bottom: 5px"
         href="{{ url(route('frontend.simbrief.generate_new', [$simbrief->id])) }}">Generate New OFP</a>
    </div>
    <div class="col">
      @if ($acars_plugin)
        @if ($bid)
          <a href="vmsacars:bid/{{$bid->id}}"
             style="margin-top: -10px; margin-bottom: 5px"
             class="btn btn-info btn-lg">Load in vmsACARS</a>
        @else
          <a href="vmsacars:flight/{{$flight->id}}"
             style="margin-top: -10px; margin-bottom: 5px"
             class="btn btn-info btn-lg">Load in vmsACARS</a>
        @endif
      @endif
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">

      <div class="row">
        <div class="col-6">
          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>
              &nbsp;Dispatch Information
            </h6>
            <div class="form-container-body">
              <div class="row">
                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Flight</p>
                    <p class="border border-dark rounded p-1 small text-monospace">
                      {{ $simbrief->xml->general->icao_airline }}{{ $simbrief->xml->general->flight_number }}</p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Departure</p>
                    <p class="border border-dark rounded p-1 small text-monospace">
                      {{ $simbrief->xml->origin->icao_code }}@if(!empty($simbrief->xml->origin->plan_rwy))
                        /{{ $simbrief->xml->origin->plan_rwy }}
                      @endif
                    </p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Arrival</p>
                    <p class="border border-dark rounded p-1 small text-monospace">
                      {{ $simbrief->xml->destination->icao_code }}@if(!empty($simbrief->xml->destination->plan_rwy))
                        /{{ $simbrief->xml->destination->plan_rwy }}
                      @endif
                    </p>
                  </div>
                </div>
              </div>

              <hr/>

              <div class="row">

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Aircraft</p>
                    <p class="border border-dark rounded p-1 small text-monospace">
                      {{ $simbrief->xml->aircraft->name }}</p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Est. Enroute Time</p>
                    <p class="border border-dark rounded p-1 small text-monospace">
                      @minutestotime($simbrief->xml->times->est_time_enroute / 60)</p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Cruise Altitude</p>
                    <p class="border border-dark rounded p-1 small text-monospace">
                      {{ $simbrief->xml->general->initial_altitude }}</p>
                  </div>
                </div>

              </div>

              <hr/>

              @if (!empty($simbrief->xml->general->dx_rmk))
                <div class="row">
                  <div class="col-12">
                    <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Dispatcher Remarks</p>
                      <p class="border border-dark rounded p-1 small text-monospace">
                        {{ $simbrief->xml->general->dx_rmk  }}</p>
                    </div>
                  </div>
                </div>
              @endif

              @if (!empty($simbrief->xml->general->sys_rmk))
                <div class="row">
                  <div class="col-12">
                    <div><p class="small text-uppercase pb-sm-0 mb-sm-1">System Remarks</p>
                      <p class="border border-dark rounded p-1 small text-monospace">
                        {{ $simbrief->xml->general->sys_rmk  }}</p>
                    </div>
                  </div>
                </div>
              @endif
            </div>
          </div>

          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>
              &nbsp;Flight Plan
            </h6>
            <div class="form-container-body">
              <div class="row">
                <div class="col-12">
                  <p class="border border-dark rounded p-1 small text-monospace">
                    {!!  str_replace("\n", "<br>", $simbrief->xml->atc->flightplan_text) !!}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>&nbsp;Weather</h6>
            <div class="form-container-body">
              <div class="row">
                <div class="col-12">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Departure METAR</p>
                    <p
                      class="border border-dark rounded p-1 small text-monospace">{{ $simbrief->xml->weather->orig_metar }}</p>
                  </div>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Departure TAF</p>
                    <p
                      class="border border-dark rounded p-1 small text-monospace">{{ $simbrief->xml->weather->orig_taf }}</p>
                  </div>
                  <hr/>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Destination METAR</p>
                    <p
                      class="border border-dark rounded p-1 small text-monospace">{{ $simbrief->xml->weather->dest_metar }}</p>
                  </div>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Destination TAF</p>
                    <p
                      class="border border-dark rounded p-1 small text-monospace">{{ $simbrief->xml->weather->dest_taf }}</p>
                  </div>
                  <hr/>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Alternate METAR</p>
                    <p
                      class="border border-dark rounded p-1 small text-monospace">{{ $simbrief->xml->weather->altn_metar }}</p>
                  </div>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Alternate TAF</p>
                    <p
                      class="border border-dark rounded p-1 small text-monospace">{{ $simbrief->xml->weather->altn_taf }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-6">
          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>
              &nbsp;Download Flight Plan
            </h6>
            <div class="form-container-body">
              <div class="row">
                <div class="col-12">
                  <select id="download_fms_select" class="select2 custom-select">
                    @foreach($simbrief->files as $fms)
                      <option value="{{ $fms['url'] }}">{{ $fms['name'] }}</option>
                    @endforeach
                  </select>
                  <br/>
                  <input id="download_fms"
                         type="submit"
                         class="btn btn-outline-primary pull-right"
                         value="Download"/>
                </div>
              </div>
            </div>
          </div>

          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>&nbsp;Prefile ATC Flight Plan</h6>
            <div class="form-container-body">
              <div class="row">
                <div class="col-3" align="center">
                  <a href="{{ $simbrief->xml->prefile->ivao->link }}" target="_blank" class="btn btn-info">File > IVAO</a>
                </div>
                <div class="col-3" align="center">
                  <a href="{{ $simbrief->xml->prefile->vatsim->link }}" target="_blank" class="btn btn-info">File > VATSIM</a>
                </div>
                <div class="col-3" align="center">
                  <a href="{{ $simbrief->xml->prefile->poscon->link }}" target="_blank" class="btn btn-info">File > POSCON</a>
                </div>
                <div class="col-3" align="center">
                  <a
                    href="http://skyvector.com/?chart=304&amp;fpl={{ $simbrief->xml->origin->icao_code}} {{ $simbrief->xml->general->route }} {{ $simbrief->xml->destination->icao_code}}"
                    target="_blank" class="btn btn-info">View Route At SkyVector</a>
                </div>
              </div>
            </div>
          </div>
          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>
              &nbsp;OFP
            </h6>
            <div class="form-container-body border border-dark">
              <div class="overflow-auto" style="height: 750px;">
                {!! $simbrief->xml->text->plan_html !!}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="form-container">
            <h6><i class="fas fa-info-circle"></i>
              &nbsp;Flight Maps
            </h6>
            <div class="form-container-body">
              @foreach($simbrief->images->chunk(2) as $images)
                <div class="row">
                  @foreach($images as $image)
                    <div class="col-6 text-center">
                      <p>
                        <img src="{{ $image['url'] }}" alt="{{ $image['name'] }}"/>
                        <small class="text-muted">{{ $image['name'] }}</small>
                      </p>
                    </div>
                  @endforeach
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <div class="row">
    <div class="col-12">
      @if (empty($simbrief->pirep_id))
        <a class="btn btn-outline-info pull-right"
           style="margin-top: -10px;margin-bottom: 5px"
           href="{{ url(route('frontend.simbrief.prefile', [$simbrief->id])) }}">Prefile PIREP</a>
      @endif
    </div>
  </div>

  {{-- SimBrief Edit Modal --}}
  @if(!empty($simbrief->xml->params->static_id))
    <div class="modal fade" id="OFP_Edit" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog" style="max-width: 1020px;">
        <div class="modal-content p-0" style="border-radius: 5px;">
          <div class="modal-header p-1">
            <h5 class="modal-title m-1 p-0">SimBrief</h5>
            <span class="close"><i class="fas fa-times-circle" data-dismiss="modal" aria-label="Close" aria-hidden="true"></i></span>
          </div>
          <div class="modal-body p-0">
            <iframe src="https://www.simbrief.com/system/dispatch.php?editflight=last&static_id={{ $simbrief->xml->params->static_id }}" style="width: 100%; height: 80vh;" frameBorder="0" title="SimBrief"></iframe>
          </div>
          <div class="modal-footer text-right p-1">
            <a
              class="btn btn-success btn-sm m-1 p-1"
              href="{{ route('frontend.simbrief.update_ofp') }}?ofp_id={{ $simbrief->id }}&flight_id={{ $simbrief->flight_id }}&aircraft_id={{ $simbrief->aircraft_id }}&sb_userid={{ $simbrief->xml->params->user_id }}&sb_static_id={{ $simbrief->xml->params->static_id }}">
              Download Updated OFP & Close
            </a>
            <button type="button" class="btn btn-danger btn-sm m-1 p-1" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

@section('scripts')
  <script>
    $(document).ready(function () {
      $("#download_fms").click(e => {
        e.preventDefault();
        const select = document.getElementById("download_fms_select");
        const link = select.options[select.selectedIndex].value;
        console.log('Downloading FMS: ', link);
        window.open(link, '_blank');
      });
    });
  </script>
@endsection
