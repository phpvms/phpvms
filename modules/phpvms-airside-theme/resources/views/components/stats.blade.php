@props(['items' => [], 'columns' => 3])

{{--
  Label-over-value grid (FlightStats). Props: items (list of ['label' => string, 'value' => scalar|null];
  null or '' renders "—"), columns (default 3). No slots.
--}}
<dl {{ $attributes->class('pv-stats m-0 grid gap-2 text-center') }} style="grid-template-columns: repeat({{ (int) $columns }}, minmax(0, 1fr))">
    @foreach ($items as $item)
        <div>
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-dim">{{ $item['label'] }}</dt>
            <dd class="mt-0.5 font-mono text-[13px] text-ink tnum">{{ ($item['value'] ?? null) === null || $item['value'] === '' ? '—' : $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
