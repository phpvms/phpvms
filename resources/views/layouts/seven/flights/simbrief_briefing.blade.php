@extends('app')
@section('title', 'Briefing')

@section('content')
  <div class="row">
    <div class="col-md-6 col-sm-12">
      <h2>{{ $simbrief->xml->general->icao_airline }}{{ $simbrief->xml->general->flight_number }}
        : {{ $simbrief->xml->origin->icao_code }} to {{ $simbrief->xml->destination->icao_code }}</h2>
    </div>
    <div class="col-md-6 col-sm-12">
      <div class="d-grid d-md-flex justify-content-md-end">
        <div class="btn-group" role="group" aria-label="Button group with nested dropdown">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              SimBrief
            </button>
            <ul class="dropdown-menu">
              @if (!empty($simbrief->xml->params->static_id) && $user->id === $simbrief->user_id)
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#OFP_Edit">Edit OFP</a></li>
              @endif
              <li><a class="dropdown-item" href="{{ url(route('frontend.simbrief.generate_new', [$simbrief->id])) }}">Generate New OFP</a></li>
            </ul>
          </div>
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              Fly Now
            </button>
            <ul class="dropdown-menu">
              @if (empty($simbrief->pirep_id))
                <li><a class="dropdown-item" href="{{ url(route('frontend.simbrief.prefile', [$simbrief->id])) }}">Prefile PIREP</a></li>
              @endif
              @if ($acars_plugin)
                @if ($bid)
                  <li><a href="vmsacars:bid/{{$bid->id}}"
                         class="dropdown-item">Load in vmsACARS</a></li>
                @else
                  <li><a href="vmsacars:flight/{{$flight->id}}"
                     class="dropdown-item">Load in vmsACARS</a></li>
                @endif
              @endif
            </ul>
          </div>
        </div>
      </div>
      </div>
      {{--
      @if (empty($simbrief->pirep_id))
        <a class="btn btn-outline-info pull-right btn-sm"
           style="margin-top: -10px; margin-bottom: 5px"
           href="{{ url(route('frontend.simbrief.prefile', [$simbrief->id])) }}">Prefile PIREP</a>
      @endif
    </div>
    @if (!empty($simbrief->xml->params->static_id) && $user->id === $simbrief->user_id)
    <div class="col">
        <a class="btn btn-secondary btn-sm"
           style="margin-top: -10px; margin-bottom: 5px"
           href="#"
           data-toggle="modal" data-target="#OFP_Edit">Edit OFP</a>
    </div>
    @endif
    <div class="col">
      <a class="btn btn-primary btn-sm"
         style="margin-top: -10px; margin-bottom: 5px"
         href="{{ url(route('frontend.simbrief.generate_new', [$simbrief->id])) }}">Generate New OFP</a>
    </div>
    <div class="col">
      @if ($acars_plugin)
        @if ($bid)
          <a href="vmsacars:bid/{{$bid->id}}"
             style="margin-top: -10px; margin-bottom: 5px"
             class="btn btn-info btn-sm">Load in vmsACARS</a>
        @else
          <a href="vmsacars:flight/{{$flight->id}}"
             style="margin-top: -10px; margin-bottom: 5px"
             class="btn btn-info btn-sm">Load in vmsACARS</a>
        @endif
      @endif
    </div>
    --}}
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="row">
        <div class="col-6">
          <div class="d-grid gap-3">
          <div class="card">
            <div class="card-header bg-primary text-white">
              Dispatch Information
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Flight</p>
                    <p class="border rounded p-1 small text-monospace">
                      {{ $simbrief->xml->general->icao_airline }}{{ $simbrief->xml->general->flight_number }}</p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Departure</p>
                    <p class="border rounded p-1 small text-monospace">
                      {{ $simbrief->xml->origin->icao_code }}@if(!empty($simbrief->xml->origin->plan_rwy))
                        /{{ $simbrief->xml->origin->plan_rwy }}
                      @endif
                    </p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Arrival</p>
                    <p class="border rounded p-1 small text-monospace">
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
                    <p class="border rounded p-1 small text-monospace">
                      {{ $simbrief->xml->aircraft->name }}</p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Est. Enroute Time</p>
                    <p class="border rounded p-1 small text-monospace">
                      @minutestotime($simbrief->xml->times->est_time_enroute / 60)</p>
                  </div>
                </div>

                <div class="col-4 text-center">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Cruise Altitude</p>
                    <p class="border rounded p-1 small text-monospace">
                      {{ $simbrief->xml->general->initial_altitude }}</p>
                  </div>
                </div>

              </div>

              <hr/>

              @if (!empty($simbrief->xml->general->dx_rmk))
                <div class="row">
                  <div class="col-12">
                    <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Dispatcher Remarks</p>
                      <p class="border rounded p-1 small text-monospace">
                        {{ $simbrief->xml->general->dx_rmk  }}</p>
                    </div>
                  </div>
                </div>
              @endif

              @if (!empty($simbrief->xml->general->sys_rmk))
                <div class="row">
                  <div class="col-12">
                    <div><p class="small text-uppercase pb-sm-0 mb-sm-1">System Remarks</p>
                      <p class="border rounded p-1 small text-monospace">
                        {{ $simbrief->xml->general->sys_rmk  }}</p>
                    </div>
                  </div>
                </div>
              @endif
            </div>
          </div>

          <div class="card">
            <div class="card-header bg-primary text-white"><i class="fas fa-info-circle"></i>
              &nbsp;Flight Plan
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-12">
                  <p class="border rounded p-1 small text-monospace">
                    {!!  str_replace("\n", "<br>", $simbrief->xml->atc->flightplan_text) !!}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header bg-primary text-white">Weather</div>
            <div class="card-body">
              <div class="row">
                <div class="col-12">
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Departure METAR</p>
                    <p
                      class="border  rounded p-1 small text-monospace">{{ $simbrief->xml->weather->orig_metar }}</p>
                  </div>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Departure TAF</p>
                    <p
                      class="border  rounded p-1 small text-monospace">{{ $simbrief->xml->weather->orig_taf }}</p>
                  </div>
                  <hr/>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Destination METAR</p>
                    <p
                      class="border  rounded p-1 small text-monospace">{{ $simbrief->xml->weather->dest_metar }}</p>
                  </div>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Destination TAF</p>
                    <p
                      class="border  rounded p-1 small text-monospace">{{ $simbrief->xml->weather->dest_taf }}</p>
                  </div>
                  <hr/>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Alternate METAR</p>
                    <p
                      class="border  rounded p-1 small text-monospace">{{ $simbrief->xml->weather->altn_metar }}</p>
                  </div>
                  <div><p class="small text-uppercase pb-sm-0 mb-sm-1">Alternate TAF</p>
                    <p
                      class="border  rounded p-1 small text-monospace">{{ $simbrief->xml->weather->altn_taf }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        </div>

        <div class="col-6">
          <div class="form-container">
            <div class="fs-5">
              Download Flight Plan
            </div>
            <div class="form-container-body">
              <div class="row">
                <div class="col-12 d-flex align-items-center">
                  <select id="download_fms_select" class="form-select me-2">
                  @foreach($simbrief->files as $fms)
                    <option value="{{ $fms['url'] }}">{{ $fms['name'] }}</option>
                  @endforeach
                  </select>
                  <button id="download_fms"
                     type="submit"
                     class="btn btn-success"><i class="bi bi-download"></i></button>
                </div>
              </div>
            </div>
            </div>
            <div class="fs-5 mt-3">Pre-File Online</div>
            <div class="btn-group my-3 w-100" role="group" aria-label="Prefile ATC Flight Plan">
            <a href="{{ $simbrief->xml->prefile->ivao->link }}" target="_blank" style="background: #0D2C99; color: white;" class="btn w-25">IVAO</a>
            <a href="{{ $simbrief->xml->prefile->vatsim->link }}" target="_blank" style="background: #29B473; color: white;" class="btn w-25">VATSIM</a>
            <a href="{{ $simbrief->xml->prefile->poscon->link }}" target="_blank" style="background: #403a60; color: white;" class="btn w-25">POSCON</a>
            <a href="http://skyvector.com/?chart=304&amp;fpl={{ $simbrief->xml->origin->icao_code}} {{ $simbrief->xml->general->route }} {{ $simbrief->xml->destination->icao_code}}" target="_blank" class="btn btn-info w-25">View @ SkyVector</a>
            </div>
          <div class="card">
            <div class="card-header bg-primary text-white"><i class="fas fa-info-circle"></i>
              &nbsp;OFP
            </div>
            <div class="card-body">
              <div class="overflow-auto" style="height: 750px;">
                {!! $simbrief->xml->text->plan_html !!}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-12">
          <div class="card">
            <div class="card-header bg-primary text-white">
              Flight Maps
            </div>
            <div class="card-body">
              @foreach($simbrief->images->chunk(2) as $images)
                <div class="row">
                  @foreach($images as $image)
                    <div class="col-6 text-center mb-4">
                      <img src="{{ $image['url'] }}" alt="{{ $image['name'] }}" class="w-100"/>
                      <small class="text-muted">{{ $image['name'] }}</small>
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
            <button type="button" class="btn btn-danger btn-sm m-1 p-1" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

@section('scripts')
  <script>
    new TomSelect('#download_fms_select', {
      create: false,
      sortField: 'text',
    });
    document.addEventListener('DOMContentLoaded', function () {
      document.getElementById("download_fms").addEventListener('click', function (e) {
      e.preventDefault();
      const button = this;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

      const select = document.getElementById("download_fms_select");
      const link = select.options[select.selectedIndex].value;
      console.log('Downloading FMS: ', link);
      const a = document.createElement('a');
      a.href = link;
      a.download = '';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);

      setTimeout(() => {
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-download"></i>';
      }, 3000);
      });
    });
  </script>
@endsection
