@php
    /** @var \App\Models\Pirep $record */
    /** @var \Illuminate\Support\Collection $logEntries */
    $logEntries = $this->logEntries;
    $phases = $this->performance['phases'] ?? [];
    $logSort = $this->logSort;

    $fmtTime = fn (?\Illuminate\Support\Carbon $dt): string => $dt?->format('H:i:s') ?? '—';
    $fmtAlt = fn (?float $v): string => $v !== null ? number_format((int) $v) . ' ft' : '—';
    $fmtGs = fn (?int $v): string => $v !== null ? $v . ' kts' : '—';

    // Strip redundant ACARS client metadata from log messages.
    // The left column already shows time; header shows aircraft.
    $cleanLog = fn (?string $log): string => $log === null
        ? '—'
        : preg_replace('/\s*at\s+\d{4}-\d{2}-\d{2}T[\d:]+Z.*$/i', '', $log);

    // Map a phase code to a stable phase bucket name. Bucket name drives the
    // CSS class — colors (light + dark) live in theme.css so dark mode works
    // without inline overrides.
    $phaseBucket = fn (string $code): string => match ($code) {
        'BRD', 'PBT', 'TXI' => 'ground',
        'TKO', 'ICL'        => 'climb',
        'ENR'               => 'cruise',
        'APR', 'FIN'        => 'descent',
        'LDG', 'ONB'        => 'land',
        default             => 'neutral',
    };

    $resolvePhase = function (\App\Models\Acars $entry, array $phases): string {
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

<div class="fi-pirep-detail-v2-flight-log">
    @if ($logEntries->isEmpty())
        <div class="fi-pirep-detail-v2-perf-empty">
            <div class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            </div>
            <h4>No flight log data for this PIREP</h4>
            <p>Flight log entries appear here once the ACARS client uploads log events during the flight.</p>
        </div>
    @else
        {{-- Header: count + sort toggle --}}
        <div class="fi-pirep-flight-log-header">
            <span class="count">{{ $logEntries->count() }} entries</span>
            <button
                type="button"
                class="fi-pirep-flight-log-sort-btn"
                wire:click="toggleLogSort"
            >
                @if ($logSort === 'asc')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14"/>
                        <path d="M19 12l-7 7-7-7"/>
                    </svg>
                    Earliest first
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 19V5"/>
                        <path d="M5 12l7-7 7 7"/>
                    </svg>
                    Latest first
                @endif
            </button>
        </div>

        {{-- Table header --}}
        <div class="fi-pirep-flight-log-grid fi-pirep-flight-log-grid-head">
            <div class="fi-pirep-flight-log-time">Time / Alt / Speed</div>
            <div class="fi-pirep-flight-log-event">Event</div>
        </div>

        {{-- Rows --}}
        <div class="fi-pirep-flight-log-grid fi-pirep-flight-log-grid-body">
            @foreach ($logEntries as $i => $entry)
                @php
                    $phase = $resolvePhase($entry, $phases);
                    $bucket = $phaseBucket($phase);
                    $rowClasses = 'fi-pirep-flight-log-row fi-pirep-flight-log-row--phase-' . $bucket;
                    if ($i % 2 === 1) {
                        $rowClasses .= ' fi-pirep-flight-log-row--zebra';
                    }
                @endphp
                <div class="{{ $rowClasses }}">
                    <div class="fi-pirep-flight-log-time">
                        <div class="time">{{ $fmtTime($entry->created_at) }}</div>
                        <div class="data">{{ $fmtAlt($entry->altitude_msl) }} · {{ $fmtGs($entry->gs) }}</div>
                    </div>
                    <div class="fi-pirep-flight-log-event">
                        <span class="msg">{{ $cleanLog($entry->log) }}</span>
                        <span class="fi-pirep-flight-log-badge fi-pirep-flight-log-badge--phase-{{ $bucket }}">
                            {{ $phase }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
