<span data-probe>
@isset($flight) F:{{ $flight->summary->callsign ?? $flight->callsign }} @endisset
@isset($bid) B:{{ $bid->id }} @endisset
@isset($selection) S:{{ $selection->bid->id }} @endisset
@isset($policy) P @endisset
</span>
