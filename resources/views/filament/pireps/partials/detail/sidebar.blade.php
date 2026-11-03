@php
    use App\Filament\Resources\Users\UserResource;
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    $pilot = $record->user;

    $userUrl = $pilot ? UserResource::getUrl('edit', ['record' => $pilot]) : null;

    // Filament badge colors -> console chip variants.
    $chipVariant = fn (?string $color): string => match ($color) {
        'success' => 'chip--ok',
        'warning' => 'chip--warn',
        'danger'  => 'chip--bad',
        'info'    => 'chip--info',
        default   => 'chip--mute',
    };

    $sourceLabel = filled($record->source_name)
        ? $record->source?->getLabel().' · '.$record->source_name
        : $record->source?->getLabel();

    $fmtHM = fn (?int $minutes): ?string => $minutes !== null
        ? sprintf('%d:%02d', ...array_values(Time::minutesToTimeParts($minutes)))
        : null;
@endphp

<aside class="rec-rail" aria-label="Report properties">
    @if ($this->acceptAction->isVisible() || $this->rejectAction->isVisible())
        <div class="rec-acts">
            {{ $this->acceptAction }}
            {{ $this->rejectAction }}
        </div>
    @endif

    <section class="rec-rail__sec">
        <h3 class="rec-rail__head">Properties</h3>
        <dl class="rec-props">
            <div>
                <dt>Status</dt>
                <dd><span class="chip {{ $chipVariant($record->state->getColor()) }}">{{ $record->state->getLabel() }}</span></dd>
            </div>
            @if ($pilot)
                <div>
                    <dt>Pilot</dt>
                    <dd>
                        <img class="rec-avatar rec-avatar--sm" src="{{ $pilot->resolveAvatarUrl() }}" alt="" />
                        <span class="rec-props__stack">
                            @if ($userUrl)<a href="{{ $userUrl }}">{{ $pilot->name }}</a>@else<span>{{ $pilot->name }}</span>@endif
                            <small class="id">{{ $pilot->ident }}</small>
                        </span>
                    </dd>
                </div>
            @endif
            @if ($record->aircraft)
                <div>
                    <dt>Aircraft</dt>
                    <dd>
                        <span class="rec-props__stack">
                            <span class="id">{{ $record->aircraft->registration }} · {{ $record->aircraft->icao }}</span>
                            @if ($record->aircraft->name)<small>{{ $record->aircraft->name }}</small>@endif
                        </span>
                    </dd>
                </div>
            @endif
            <div><dt>Flight type</dt><dd>{{ $record->flight_type->getLabel() }}</dd></div>
            @if (filled($sourceLabel))
                <div><dt>Source</dt><dd>{{ $sourceLabel }}</dd></div>
            @endif
            @if (filled($record->route_code))
                <div><dt>Route code</dt><dd><span class="id">{{ $record->route_code }}</span></dd></div>
            @endif
            @if ($fmtHM($record->planned_flight_time))
                <div><dt>Planned time</dt><dd><span class="id">{{ $fmtHM($record->planned_flight_time) }}</span></dd></div>
            @endif
            @if ($record->planned_distance)
                <div><dt>Planned dist</dt><dd><span class="id">{{ number_format((float) $record->planned_distance->local()) }}</span> {{ setting('units.distance') }}</dd></div>
            @endif
            @if ($record->block_fuel)
                <div><dt>Block fuel</dt><dd><span class="id">{{ number_format((float) $record->block_fuel->local()) }}</span> {{ setting('units.fuel') }}</dd></div>
            @endif
            @if ($record->submitted_at)
                <div><dt>Submitted</dt><dd><span class="id">{{ $record->submitted_at->format('j M H:i') }}Z</span></dd></div>
            @endif
            <div><dt>Report ID</dt><dd><span class="id">{{ \Illuminate\Support\Str::limit($record->id, 12, '…') }}</span></dd></div>
        </dl>
    </section>

    @if ($record->fields && $record->fields->isNotEmpty())
        <section class="rec-rail__sec">
            <h3 class="rec-rail__head">{{ __('pireps.fields') }}</h3>
            <dl class="rec-props">
                @foreach ($record->fields as $field)
                    <div>
                        <dt>{{ $field->name }}</dt>
                        <dd>{{ filled($field->value) ? $field->value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if ($pilot)
        <section class="rec-rail__sec">
            <h3 class="rec-rail__head">Pilot</h3>
            <div class="rec-who">
                <img class="rec-avatar" src="{{ $pilot->resolveAvatarUrl() }}" alt="" />
                <div>
                    <b>
                        @if ($userUrl)<a href="{{ $userUrl }}">{{ $pilot->name }}</a>@else{{ $pilot->name }}@endif
                    </b>
                    <span><span class="id">{{ $pilot->ident }}</span>@if ($pilot->rank) · {{ $pilot->rank->name }}@endif</span>
                </div>
            </div>
            <div class="rec-mini">
                <div><span>Career hours</span><b class="id">{{ number_format((float) ($pilot->flight_time / 60), 1) }}</b></div>
                @if ($pilot->home_airport_id)
                    <div><span>Home base</span><b class="id">{{ $pilot->home_airport_id }}</b></div>
                @endif
                @if ($pilot->state)
                    <div><span>Status</span><b><span class="chip {{ $chipVariant($pilot->state->getColor()) }} chip--plain">{{ $pilot->state->getLabel() }}</span></b></div>
                @endif
            </div>
        </section>
    @endif
</aside>
