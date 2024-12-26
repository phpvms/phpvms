@extends('app')
@section('title', trans_choice('common.pirep', 1).' '.$pirep->ident)

@section('content')
  <div class="row">
    <div class="col-sm-8">
      <h2>{{ $pirep->ident }} : {{ $pirep->dpt_airport_id }} to {{ $pirep->arr_airport_id }}</h2>
    </div>

    <div class="col-sm-4">
      {{-- Show the link to edit if it can be edited --}}
      @if (!empty($pirep->simbrief))
        <a href="{{ url(route('frontend.simbrief.briefing', [$pirep->simbrief->id])) }}"
           class="btn btn-outline-info">View SimBrief</a>
      @endif

      @if(!$pirep->read_only && $user && $pirep->user_id === $user->id)
        <div class="float-right" style="margin-bottom: 10px;">
          <form method="get"
                action="{{ route('frontend.pireps.edit', $pirep->id) }}"
                style="display: inline">
            @csrf
            <button class="btn btn-outline-info">@lang('common.edit')</button>
          </form>
          &nbsp;
          <form method="post"
                action="{{ route('frontend.pireps.submit', $pirep->id) }}"
                style="display: inline">
            @csrf
            <button class="btn btn-outline-success">@lang('common.submit')</button>
          </form>
        </div>
      @endif
    </div>
  </div>

  <div class="row">
    <div class="col-8">
      <div class="row">
        {{--
            DEPARTURE INFO
        --}}
        <div class="col-6 text-left">
          <h4>
            {{$pirep->dpt_airport->location}}
          </h4>
          <p>
            <a href="{{route('frontend.airports.show', $pirep->dpt_airport_id)}}">
              {{ $pirep->dpt_airport->full_name }} ({{  $pirep->dpt_airport_id }})</a>
            <br/>
            @if($pirep->block_off_time)
              {{ $pirep->block_off_time->toDayDateTimeString() }}
            @endif
          </p>
        </div>

        {{--
            ARRIVAL INFO
        --}}
        <div class="col-6 text-right">
          <h4>
            {{$pirep->arr_airport->location}}
          </h4>
          <p>
            <a href="{{route('frontend.airports.show', $pirep->arr_airport_id)}}">
              {{ $pirep->arr_airport->full_name }} ({{  $pirep->arr_airport_id }})</a>
            <br/>
            @if($pirep->block_on_time)
              {{ $pirep->block_on_time->toDayDateTimeString() }}
            @endif
          </p>
        </div>
      </div>

      @if(!empty($pirep->distance))
        <div class="row">
          <div class="col-12">
            <div class="progress" style="margin: 20px 0;">
              <div class="progress-bar progress-bar-success" role="progressbar"
                  aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"
                  style="width: {{$pirep->progress_percent}}%;">
              </div>
            </div>
          </div>
        </div>
      @endif

      <div class="row">
        <div class="col-12">
          @include('pireps.map')
        </div>
      </div>
    </div>

    {{--

    RIGHT SIDEBAR

    --}}

    <div class="col-4">
      <table class="table table-striped">
        <tr>
          <td width="30%">@lang('common.state')</td>
          <td>
            <div class="badge badge-info">
              {{ PirepState::label($pirep->state) }}
            </div>
          </td>
        </tr>

        @if ($pirep->state !== PirepState::DRAFT)
        <tr>
          <td width="30%">@lang('common.status')</td>
          <td>
            <div class="badge badge-info">
              {{ PirepStatus::label($pirep->status) }}
            </div>
          </td>
        </tr>
        @endif

        <tr>
          <td>@lang('pireps.source')</td>
          <td>{{ PirepSource::label($pirep->source) }}</td>
        </tr>

        <tr>
          <td>@lang('flights.flighttype')</td>
          <td>{{ \App\Models\Enums\FlightType::label($pirep->flight_type) }}</td>
        </tr>

        <tr>
          <td>@lang('pireps.filedroute')</td>
          <td>{{ $pirep->route }}</td>
        </tr>

        <tr>
          <td>{{ trans_choice('common.note', 2) }}</td>
          <td>{{ $pirep->notes }}</td>
        </tr>

        @if($pirep->score && $pirep->landing_rate)
          <tr>
            <td>Score</td>
            <td>{{ $pirep->score }}</td>
          </tr>
          <tr>
            <td>Landing Rate</td>
            <td>{{ number_format($pirep->landing_rate) }}</td>
          </tr>
        @endif

        <tr>
          <td>@lang('pireps.filedon')</td>
          <td>{{ show_datetime($pirep->created_at) }}</td>
        </tr>

      </table>

      @if(count($pirep->fields) > 0)
        <div class="separator"></div>
      @endif

      @if(count($pirep->fields) > 0)
        <h5>{{ trans_choice('common.field', 2) }}</h5>
        <table class="table table-hover table-condensed">
          <thead>
          <th>@lang('common.name')</th>
          <th>{{ trans_choice('common.value', 1) }}</th>
          </thead>
          <tbody>
          @foreach($pirep->fields as $field)
            <tr>
              <td>{{ $field->name }}</td>
              <td>{{ $field->value }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      @endif

      {{--
          Show the fares that have been entered
      --}}
      @if(count($pirep->fares) > 0)
        <div class="separator"></div>
        <div class="row">
          <div class="col-12">
            <h5>{{ trans_choice('pireps.fare', 2) }}</h5>
            <table class="table table-hover table-condensed">
              <thead>
              <th>@lang('pireps.class')</th>
              <th>@lang('pireps.count')</th>
              </thead>
              <tbody>
              @foreach($pirep->fares as $fare)
                <tr>
                  <td>{{ $fare->name }} ({{ $fare->code }})</td>
                  <td>{{ $fare->count }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>
  </div>

  @if(count($pirep->acars_logs) > 0)
    <div class="separator"></div>
    <div class="row">
      <div class="col-12">
        <h5>@lang('pireps.flightlog')</h5>
      </div>
      <div class="col-12">
        <table class="table table-hover table-condensed" id="users-table">
          <tbody>
          @foreach($pirep->acars_logs->sortBy('created_at') as $log)
            <tr>
              <td nowrap="true">{{ show_datetime($log->created_at) }}</td>
              <td>{{ $log->log }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if(!empty($pirep->simbrief))
    <div class="separator"></div>
    <div class="row mt-5">
      <div class="col-12">
        <div class="form-container">
          <h6><i class="fas fa-info-circle"></i>
            &nbsp;OFP
          </h6>
          <div class="form-container-body border border-dark">
            <div class="overflow-auto" style="height: 600px;">
              {!! $pirep->simbrief->xml->text->plan_html !!}
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection
