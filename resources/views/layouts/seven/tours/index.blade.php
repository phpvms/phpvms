@extends('app')
@section('title', __('ui.nav_tours'))

{{-- $tours is TourListItemData[] (same DTOs the SPA page reads). Read-only
     listing: bidding a tour happens from the flights page (bid leg 1). --}}
@section('content')
  <div class="row">
    @include('flash::message')
    <div class="col-12">
      <h2>{{ __('ui.nav_tours') }}</h2>

      @if (count($tours) === 0)
        <div class="card">
          <div class="card-body text-center text-muted">
            @lang('tours.none_published')
          </div>
        </div>
      @endif

      @foreach ($tours as $tour)
        <div class="card mb-3">
          @if ($tour->image)
            <img src="{{ $tour->image }}" alt="" class="card-img-top" style="max-height: 240px; object-fit: cover;">
          @endif
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <h4 class="card-title mb-1">{{ $tour->name }}</h4>
                @if ($tour->startDate || $tour->endDate)
                  <div class="text-muted small">
                    {{ $tour->startDate ?? '…' }} &ndash; {{ $tour->endDate ?? '…' }}
                  </div>
                @endif
              </div>
              @if ($tour->status === 'in_progress')
                <span class="badge bg-primary">
                  @lang('tours.in_progress', ['completed' => $tour->legsCompleted, 'total' => count($tour->legs)])
                </span>
              @elseif ($tour->status === 'completed')
                <span class="badge bg-success">@lang('tours.completed')</span>
              @endif
            </div>

            @if ($tour->description)
              <p class="text-muted mb-2">{{ $tour->description }}</p>
            @endif

            <table class="table table-sm mb-0">
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
      @endforeach
    </div>
  </div>
@endsection
