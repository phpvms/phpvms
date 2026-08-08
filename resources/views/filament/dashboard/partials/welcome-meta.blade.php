{{--
    Dashboard page subheading: country flag + rank + primary role. Structure
    mirrors filament/pireps/partials/head-metrics.blade.php — the page
    computes the segments, the view only renders them. Renders inside
    .fi-header-subheading, so it inherits the white-mix hero text colour.
--}}
<span class="welcome-meta">
    @foreach ($segments as $segment)
        @if (!$loop->first)
            <span aria-hidden="true"> · </span>
        @endif

        @if (isset($segment['flag']))
            <img src="{{ $segment['flag'] }}" alt="" class="welcome-meta__flag" />
        @else
            {{ $segment['text'] }}
        @endif
    @endforeach
</span>
