@php
    /** @var \App\Models\Pirep $record */
    $pilot = $record->user;
@endphp

<aside class="fi-pirep-detail-v2-sidebar">
    {{-- Pilot --}}
    @if ($pilot)
        <div class="fi-pirep-detail-v2-card">
            <div class="fi-pirep-detail-v2-card-head">
                <h3>Pilot</h3>
            </div>
            <div class="fi-pirep-detail-v2-card-body flush">
                <div class="fi-pirep-detail-v2-facts stacked">
                    @if ($pilot->rank)
                        <div class="fact"><span class="k">Rank</span><span class="v">{{ $pilot->rank->name }}</span></div>
                    @endif
                    <div class="fact"><span class="k">Total Hours</span><span class="v">{{ number_format((float) ($pilot->flight_time / 60), 1) }}</span></div>
                    @if ($pilot->home_airport_id)
                        <div class="fact"><span class="k">Home Airport</span><span class="v mono">{{ $pilot->home_airport_id }}</span></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Flight metadata --}}
    <div class="fi-pirep-detail-v2-card">
        <div class="fi-pirep-detail-v2-card-head">
            <h3>Flight</h3>
        </div>
        <div class="fi-pirep-detail-v2-card-body flush">
            <div class="fi-pirep-detail-v2-facts stacked">
                @if ($record->flight_type)
                    <div class="fact"><span class="k">Flight Type</span><span class="v">{{ $record->flight_type->getLabel() }}</span></div>
                @endif
                @if (filled($record->route_code))
                    <div class="fact"><span class="k">Route Code</span><span class="v">{{ $record->route_code }}</span></div>
                @endif
                @if ($record->block_fuel)
                    <div class="fact"><span class="k">Block Fuel</span><span class="v">{{ number_format((float) $record->block_fuel->local()) }} {{ setting('units.fuel') }}</span></div>
                @endif
                @if ($record->planned_flight_time)
                    <div class="fact"><span class="k">Planned Time</span><span class="v">{{ \App\Support\Units\Time::minutesToTimeString((int) $record->planned_flight_time) }}</span></div>
                @endif
                @if ($record->planned_distance)
                    <div class="fact"><span class="k">Planned Dist</span><span class="v">{{ number_format((float) $record->planned_distance->local()) }} {{ setting('units.distance') }}</span></div>
                @endif
                @if ($record->submitted_at)
                    <div class="fact"><span class="k">Submitted</span><span class="v mono">{{ $record->submitted_at->format('d M H:i') }}Z</span></div>
                @endif
                <div class="fact"><span class="k">ID</span><span class="v mono">{{ \Illuminate\Support\Str::limit($record->id, 12, '…') }}</span></div>
            </div>
        </div>
    </div>

    {{-- PIREP custom fields --}}
    @if ($record->fields && $record->fields->isNotEmpty())
        <div class="fi-pirep-detail-v2-card">
            <div class="fi-pirep-detail-v2-card-head">
                <h3>{{ __('pireps.fields') }}</h3>
            </div>
            <div class="fi-pirep-detail-v2-card-body flush">
                <div class="fi-pirep-detail-v2-facts stacked">
                    @foreach ($record->fields as $field)
                        <div class="fact">
                            <span class="k">{{ $field->name }}</span>
                            <span class="v">{{ filled($field->value) ? $field->value : '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</aside>
