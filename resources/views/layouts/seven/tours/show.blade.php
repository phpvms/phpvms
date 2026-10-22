@extends('app')
@section('title', $tour->name)

{{-- $tour is a TourListItemData (same DTO the SPA page reads). Read-only:
     bidding happens from the tours list. --}}
@section('content')
  <div class="row">
    @include('flash::message')
    <div class="col-12">
      <h2>{{ $tour->name }}</h2>
      @if ($tour->startDate || $tour->endDate)
        <div class="text-muted small mb-2">
          {{ $tour->startDate ?? '…' }} &ndash; {{ $tour->endDate ?? '…' }}
        </div>
      @endif

      <div class="card mb-3">
        @if ($tour->image)
          <img src="{{ $tour->image }}" alt="" class="card-img-top" style="max-height: 280px; object-fit: cover;">
        @endif
        <div class="card-body">
          @if ($tour->description)
            <p class="text-muted">{{ $tour->description }}</p>
          @endif

          @if ($tour->status === 'in_progress')
            <span class="badge bg-primary">
              @lang('tours.in_progress', ['completed' => $tour->legsCompleted, 'total' => count($tour->legs)])
            </span>
          @elseif ($tour->status === 'completed')
            <span class="badge bg-success">@lang('tours.completed')</span>
          @endif

          <table class="table table-sm mt-3 mb-0">
            <tbody>
              @foreach ($tour->legs as $leg)
                <tr @class(['text-muted' => $leg->flown])>
                  <td style="width: 1%">{{ $leg->routeLeg ?? '—' }}</td>
                  <td>{{ $leg->ident }}</td>
                  <td>{{ $leg->dpt }} &rarr; {{ $leg->arr }}</td>
                  <td class="text-end">
                    @if ($leg->flown)
                      <span class="badge bg-success">@lang('tours.flown')</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <a href="{{ route('frontend.tours.index') }}">&larr; {{ __('ui.nav_tours') }}</a>
    </div>
  </div>
@endsection
