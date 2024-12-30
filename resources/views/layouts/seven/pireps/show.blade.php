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
      <div class="d-flex flex-column flex-md-row justify-content-between">
        {{--
            DEPARTURE INFO
        --}}
        <div class="text-left">
          <h4>
            {{$pirep->dpt_airport->location}}
          </h4>
          <p>
            <a href="{{route('frontend.airports.show', $pirep->dpt_airport_id)}}">
              {{ $pirep->dpt_airport->full_name }}</a>
            <br/>
            @if($pirep->block_off_time)
              {{ $pirep->block_off_time->toDayDateTimeString() }}
            @endif
          </p>
        </div>

        {{--
            ARRIVAL INFO
        --}}
        <div class="text-md-end text-left">
          <h4>
            {{$pirep->arr_airport->location}}
          </h4>
          <p>
            <a href="{{route('frontend.airports.show', $pirep->arr_airport_id)}}">
              {{ $pirep->arr_airport->full_name }}</a>
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
              <div class="progress-bar @if($pirep->state === PirepState::IN_PROGRESS) bg-primary progress-bar-striped progress-bar-animated @else bg-success @endif" role="progressbar"
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
      <div class="list-group">
      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('common.state')</span>
        @php
        $stateClass = 'bg-info';
        if ($pirep->state === PirepState::PENDING) {
          $stateClass = 'bg-warning';
        } elseif ($pirep->state === PirepState::ACCEPTED) {
          $stateClass = 'bg-success';
        } elseif ($pirep->state === PirepState::REJECTED) {
          $stateClass = 'bg-danger';
        } elseif ($pirep->state === PirepState::IN_PROGRESS) {
          $stateClass = 'bg-primary';
        }
        @endphp
        <span class="badge {{ $stateClass }}">
        {{ PirepState::label($pirep->state) }}
        </span>
      </div>

      @if ($pirep->state !== PirepState::DRAFT)
      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('common.status')</span>
        @php
        $statusClass = 'bg-info';
        if ($pirep->status === PirepStatus::SCHEDULED) {
          $statusClass = 'bg-secondary';
        } elseif ($pirep->status === PirepStatus::ENROUTE) {
          $statusClass = 'bg-primary';
        } elseif ($pirep->status === PirepStatus::ARRIVED) {
          $statusClass = 'bg-success';
        } elseif ($pirep->status === PirepStatus::CANCELLED) {
          $statusClass = 'bg-danger';
        } elseif ($pirep->status === PirepStatus::DIVERTED) {
          $statusClass = 'bg-warning';
        }
        @endphp
        <span class="badge {{ $statusClass }}">
        {{ PirepStatus::label($pirep->status) }}
        </span>
      </div>
      @endif

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('pireps.source')</span>
        <span>{{ PirepSource::label($pirep->source) }}</span>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('flights.flighttype')</span>
        <span>{{ \App\Models\Enums\FlightType::label($pirep->flight_type) }}</span>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('pireps.filedroute')</span>
        <span>{{ $pirep->route }}</span>
      </div>

      <div class="d-flex justify-content-between list-group-item">
        <span>{{ trans_choice('common.note', 2) }}</span>
        <span>{{ $pirep->notes }}</span>
      </div>

      @if($pirep->score && $pirep->landing_rate)
        <div class="d-flex justify-content-between list-group-item">
        <span>Score</span>
        <span>{{ $pirep->score }}</span>
        </div>
        <div class="d-flex justify-content-between list-group-item">
        <span>Landing Rate</span>
        <span>{{ number_format($pirep->landing_rate) }}</span>
        </div>
      @endif

      <div class="d-flex justify-content-between list-group-item">
        <span>@lang('pireps.filedon')</span>
        <span>{{ show_datetime($pirep->created_at) }}</span>
      </div>
      </div>

      @if(count($pirep->fields) > 0)
      <div class="separator"></div>
      @endif

      @if(count($pirep->fields) > 0)
      <h5>{{ trans_choice('common.field', 2) }}</h5>
      <div class="list-group">
        @foreach($pirep->fields as $field)
        <div class="d-flex justify-content-between list-group-item">
          <span>{{ $field->name }}</span>
          <span>{{ $field->value }}</span>
        </div>
        @endforeach
      </div>
      @endif

      @if(count($pirep->fares) > 0)
      <div class="separator"></div>
      <div class="row">
        <div class="col-12">
        <h5>{{ trans_choice('pireps.fare', 2) }}</h5>
        <div class="list-group">
          @foreach($pirep->fares as $fare)
          <div class="d-flex justify-content-between list-group-item">
            <span>{{ $fare->name }} ({{ $fare->code }})</span>
            <span>{{ $fare->count }}</span>
          </div>
          @endforeach
        </div>
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
