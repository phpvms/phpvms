@extends('app')
@section('title', trans_choice('common.flight', 2))

@section('content')
  <div class="row">
    @include('flash::message')
    <div class="col-xl-9 col-md-12">
      <h2>{{ trans_choice('common.flight', 2) }}</h2>
      <div class="d-xl-none mb-3">
        <div class="accordion" id="searchAccordion">
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
                @lang('flights.search')
              </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#searchAccordion">
              <div class="accordion-body">
                @include('flights.search')
              </div>
            </div>
          </div>
        </div>
      </div>
      @include('flights.table')
    </div>
    <div class="col-xl-3 d-none d-xl-block">
      <div class="card">
        <div class="card-header">@lang('flights.search')</div>
        <div class="card-body">
          @include('flights.search')
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-12 text-center">
      {{ $flights->withQueryString()->links('pagination.bootstrap-5') }}
    </div>
  </div>
  @if (setting('bids.block_aircraft', false))
    @include('flights.bids_aircraft')
  @endif
@endsection

@include('flights.scripts')

