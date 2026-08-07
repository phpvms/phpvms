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

<aside class="panel context" aria-label="Report context">
    @if ($pilot)
        <div class="context__head">
            <div>
                <div class="context__eyebrow">Pilot</div>
                <div class="context__title">
                    @if ($userUrl)
                        <a href="{{ $userUrl }}">{{ $pilot->name }}</a>
                    @else
                        {{ $pilot->name }}
                    @endif
                </div>
                <div class="context__sub">
                    <span class="id">{{ $pilot->ident }}</span>
                    @if ($pilot->rank) · {{ $pilot->rank->name }}@endif
                </div>
            </div>
            @if ($pilot->state)
                <span class="chip {{ $chipVariant($pilot->state->getColor()) }} chip--plain">{{ $pilot->state->getLabel() }}</span>
            @endif
        </div>

        <dl class="dl">
            <div>
                <dt>Career hours</dt>
                <dd><span class="id">{{ number_format((float) ($pilot->flight_time / 60), 1) }}</span></dd>
            </div>
            @if ($pilot->home_airport_id)
                <div>
                    <dt>Home base</dt>
                    <dd><span class="id">{{ $pilot->home_airport_id }}</span></dd>
                </div>
            @endif
        </dl>
    @endif

    <div class="panel__head border-t border-line rounded-none">
        <h3 class="panel__title">Report</h3>
    </div>
    <dl class="dl">
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

    @if ($record->fields && $record->fields->isNotEmpty())
        <div class="panel__head border-t border-line rounded-none">
            <h3 class="panel__title">{{ __('pireps.fields') }}</h3>
        </div>
        <dl class="dl">
            @foreach ($record->fields as $field)
                <div>
                    <dt>{{ $field->name }}</dt>
                    <dd>{{ filled($field->value) ? $field->value : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</aside>
