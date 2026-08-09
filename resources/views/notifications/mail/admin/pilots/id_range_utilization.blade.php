@component('mail::message')
  # Pilot ID Range Utilization

  The configured pilot ID range ({{ $range_start }}-{{ $range_end }}) is at {{ round($utilization) }}% capacity.

  {{ $taken }} of {{ $total }} pilot IDs are in use.

  {{ config('app.name') }}
@endcomponent
