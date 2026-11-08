@props(['items' => [], 'columns' => 3])

{{--
  Label-over-value grid. Props: items (list of ['label' => string, 'value' => scalar|null]; null or ''
  renders "—"), columns (default 3). No slots.
--}}
<dl {{ $attributes->class('m-0 grid gap-2 text-center') }} style="grid-template-columns: repeat({{ (int) $columns }}, minmax(0, 1fr))">
    @foreach ($items as $item)
        <div>
            <dt class="text-[11px] font-semibold tracking-wide text-dark-500 uppercase dark:text-dark-400">{{ $item['label'] }}</dt>
            <dd class="mt-0.5 font-mono text-sm tabular-nums">{{ ($item['value'] ?? null) === null || $item['value'] === '' ? '—' : $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
