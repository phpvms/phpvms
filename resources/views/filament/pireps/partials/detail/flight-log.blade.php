@php
    /** @var \App\Models\Pirep $record */
    /** @var \Illuminate\Support\Collection<int, \App\Models\PirepEvent> $logEntries */
    $phases = $this->performance['phases'] ?? [];
    $logSort = $this->logSort;

    $fmtTime = fn (?\Illuminate\Support\Carbon $dt): string => $dt?->format('H:i:s') ?? '—';
    $fmtAlt = fn (?float $v): string => $v !== null ? number_format((int) $v) : '—';

    // Strip redundant ACARS client metadata from log messages.
    $cleanLog = fn (?string $log): string => $log === null
        ? '—'
        : preg_replace('/\s*at\s+\d{4}-\d{2}-\d{2}T[\d:]+Z.*$/i', '', $log);

    // Map a phase code to a stable phase bucket name, then to one of the
    // four allowed phase-tag chip variants (mockup pirep.html:865-1033).
    $phaseBucket = fn (string $code): string => match ($code) {
        'BRD', 'PBT', 'TXI' => 'ground',
        'TKO', 'ICL'        => 'climb',
        'ENR'               => 'cruise',
        'APR', 'FIN'        => 'descent',
        'LDG', 'ONB'        => 'land',
        default             => 'neutral',
    };
    $bucketChip = fn (string $bucket): string => match ($bucket) {
        'ground'  => 'chip--mute',
        'climb'   => 'chip--info',
        'cruise'  => 'chip--ok',
        'descent', 'land' => 'chip--warn',
        default   => 'chip--mute',
    };

    $resolvePhase = function (\App\Models\PirepEvent $entry, array $phases): string {
        if ($entry->created_at === null || $phases === []) {
            return 'SCH';
        }

        $ts = $entry->created_at->getTimestamp();

        foreach ($phases as $phase) {
            if ($ts >= $phase['start'] && $ts <= $phase['end']) {
                return $phase['code'];
            }
        }

        return 'SCH';
    };
@endphp

@if ($logEntries->isEmpty())
    <div class="panel__body panel__body--centred">
        <p class="text-ink-3 text-sm">No flight log data for this PIREP. Flight log entries appear here once the
            ACARS client uploads log events during the flight.</p>
    </div>
@else
    <div class="filters">
        <span class="result-count"><b>{{ $logEntries->count() }}</b> entries</span>
        <span class="filters__spacer"></span>
        <div class="segmented" role="group" aria-label="Sort order">
            <button type="button" wire:click="toggleLogSort" aria-pressed="{{ $logSort === 'asc' ? 'true' : 'false' }}">Earliest first</button>
            <button type="button" wire:click="toggleLogSort" aria-pressed="{{ $logSort === 'desc' ? 'true' : 'false' }}">Latest first</button>
        </div>
    </div>

    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th scope="col">Sim time</th>
                    <th scope="col">Phase</th>
                    <th scope="col" class="r">Alt</th>
                    <th scope="col">Event</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logEntries as $entry)
                    @php
                        $phase = $resolvePhase($entry, $phases);
                        $bucket = $phaseBucket($phase);
                    @endphp
                    <tr class="log-row">
                        <td><span class="id">{{ $fmtTime($entry->created_at) }}</span></td>
                        <td><span class="phase-tag {{ $bucketChip($bucket) }}">{{ $phase }}</span></td>
                        <td class="r"><span class="id">{{ $fmtAlt($entry->altitude_msl) }}</span></td>
                        <td>{{ $cleanLog($entry->log) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
