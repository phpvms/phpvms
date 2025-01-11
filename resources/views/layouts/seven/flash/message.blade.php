@foreach (collect(session('flash_notification', collect()))->toArray() as $message)
  @if (is_string($message))
    <div class="alert alert-error">{!! $message !!}</div>
  @else
    @if ($message['overlay'])
      @include('flash::modal', [
          'modalClass' => 'flash-modal',
          'title'      => $message['title'],
          'body'       => $message['message']
      ])
    @else
      @php
        $icon = match ($message['level']) {
          'danger', 'warning' => 'exclamation-triangle-fill',
          'success' => 'check-circle-fill',
          default => 'info-circle-fill'
        }
      @endphp

      <div class="alert alert-{{ $message['level'] }} d-flex align-items-center {{ $message['important'] ? 'alert-dismissible' : '' }}" role="alert">
        <i class="bi bi-{{ $icon }} flex-shrink-0 me-2" role="img" aria-label="Info: "></i>

        {!! $message['message'] !!}

        @if ($message['important'])
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        @endif
      </div>
    @endif
  @endif
@endforeach

{{ session()->forget('flash_notification') }}
