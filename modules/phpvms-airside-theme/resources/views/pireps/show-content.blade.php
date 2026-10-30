{{-- PIREP main column: notes, custom fields, fares, ACARS log. Each panel shows only with content (PirepContent). Variable: $pirep (PirepData). --}}
<main class="pv-pirep-content grid min-w-0 content-start gap-3">
    @if ($pirep->notes)
        <div class="rounded-md border border-line bg-panel px-3.5 py-3">
            <p class="pv-eyebrow">NOTES</p>
            <p class="mt-1.5 mb-0 text-[13px] whitespace-pre-wrap text-ink">{{ $pirep->notes }}</p>
        </div>
    @endif

    @if (count($pirep->fields) > 0)
        <div class="rounded-md border border-line bg-panel px-3.5 py-3">
            <p class="pv-eyebrow">CUSTOM FIELDS</p>
            <dl class="m-0 mt-2 grid grid-cols-[auto_1fr] gap-x-3.5 gap-y-1.5 text-xs">
                @foreach ($pirep->fields as $field)
                    <dt class="whitespace-nowrap text-ink-faint">{{ $field->name }}</dt>
                    <dd class="m-0 text-right text-ink">{{ $field->value ?: '—' }}</dd>
                @endforeach
            </dl>
        </div>
    @endif

    @if (count($pirep->fares) > 0)
        <div class="rounded-md border border-line bg-panel px-3.5 py-3">
            <p class="pv-eyebrow">FARES</p>
            <table class="mt-2 w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="border-b border-line px-1.5 py-1 text-left text-[11px] font-normal text-ink-dim uppercase">Class</th>
                        <th class="border-b border-line px-1.5 py-1 text-left text-[11px] font-normal text-ink-dim uppercase">Code</th>
                        <th class="border-b border-line px-1.5 py-1 text-right text-[11px] font-normal text-ink-dim uppercase">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pirep->fares as $fare)
                        <tr class="[&:not(:last-child)>td]:border-b [&>td]:border-line">
                            <td class="p-1.5">{{ $fare->name }}</td>
                            <td class="p-1.5 font-mono">{{ $fare->code ?? '—' }}</td>
                            <td class="p-1.5 text-right tnum">{{ $fare->count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (count($pirep->logs) > 0)
        <div class="rounded-md border border-line bg-panel px-3.5 py-3">
            <p class="pv-eyebrow">ACARS LOG</p>
            <ul class="m-0 mt-2 grid max-h-80 list-none gap-[3px] overflow-auto p-0">
                @foreach ($pirep->logs as $entry)
                    <li class="flex gap-2.5 font-mono text-[11px]">
                        <span class="whitespace-nowrap text-ink-faint">{{ $entry->time ? \Illuminate\Support\Carbon::parse($entry->time)->format('d M Y, H:i') : '—' }}</span>
                        <span class="text-ink-dim">{{ $entry->message }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</main>
