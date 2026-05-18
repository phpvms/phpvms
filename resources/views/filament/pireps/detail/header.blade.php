@php
    use App\Enums\PirepState;
    use App\Filament\Resources\Users\UserResource;
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    $pilotName = $record->user?->name ?? '—';
    $initials = collect(explode(' ', trim((string) $pilotName)))
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    if ($initials === '') {
        $initials = '?';
    }
    $hue = abs(crc32((string) ($record->user_id ?? $record->id))) % 360;

    $stateColor = match ($record->state) {
        PirepState::PENDING  => 'warning',
        PirepState::ACCEPTED => 'success',
        PirepState::REJECTED => 'danger',
        default              => 'gray',
    };

    $userUrl = $record->user
        ? UserResource::getUrl('edit', ['record' => $record->user])
        : null;

    $sourceLabel = filled($record->source_name)
        ? $record->source?->getLabel().' · '.$record->source_name
        : $record->source?->getLabel();

    $unitDistance = setting('units.distance');
    $unitFuel = setting('units.fuel');

    // Score color band
    $scoreClass = match (true) {
        $record->score === null => '',
        $record->score >= 90    => 'good',
        $record->score >= 70    => 'warn',
        default                 => 'bad',
    };

    // Landing color band
    $landing = (float) ($record->landing_rate ?? 0);
    $landingClass = match (true) {
        $landing === 0.0                          => '',
        $landing > 0                              => 'bad',
        $landing >= -500 && $landing <= -50       => 'good',
        $landing < -1000                          => 'bad',
        default                                   => 'warn',
    };

    // Deltas
    $timeDelta = ($record->planned_flight_time && $record->flight_time)
        ? (int) $record->flight_time - (int) $record->planned_flight_time
        : null;
    $distancePlanned = $record->planned_distance?->local();
    $fuelRemaining = ($record->block_fuel && $record->fuel_used)
        ? (float) $record->block_fuel->local() - (float) $record->fuel_used->local()
        : null;

    $cruiseFt = $record->level ?? 0;
    $cruiseFL = $cruiseFt > 0 ? 'FL'.str_pad((string) (int) ($cruiseFt / 100), 3, '0', STR_PAD_LEFT) : '—';
@endphp

<div class="fi-pirep-detail-v2-header">
    <div class="fi-pirep-detail-v2-hero">
        <div class="fi-pirep-detail-v2-hero-main">
            <div class="fi-pirep-detail-v2-hero-left">
                <div class="fi-pirep-detail-v2-avatar"
                     style="background: linear-gradient(135deg, hsl({{ $hue }}, 80%, 55%), hsl({{ ($hue + 20) % 360 }}, 80%, 45%));">
                    {{ $initials }}
                </div>
                <div style="min-width:0;">
                    <div class="fi-pirep-detail-v2-hero-title">
                        <span class="ident">{{ $record->ident }}</span>
                        <span class="route">{{ $record->dpt_airport_id }}<span class="arrow">→</span>{{ $record->arr_airport_id }}</span>
                    </div>
                    <div class="fi-pirep-detail-v2-hero-meta">
                        @if ($userUrl)
                            <a href="{{ $userUrl }}">{{ $pilotName }}</a>
                        @else
                            <span>{{ $pilotName }}</span>
                        @endif
                        @if ($record->aircraft)
                            <span class="dot">·</span>
                            <span>{{ $record->aircraft->registration }} · {{ $record->aircraft->name }}</span>
                        @endif
                        @if ($record->submitted_at)
                            <span class="dot">·</span>
                            <span title="{{ $record->submitted_at->format('d-m-Y H:i') }}">Filed {{ $record->submitted_at->diffForHumans() }}</span>
                        @endif
                        @if (filled($sourceLabel))
                            <span class="dot">·</span>
                            <span>via {{ $sourceLabel }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="fi-pirep-detail-v2-hero-right">
                <x-filament::badge :color="$stateColor" size="lg">
                    {{ $record->state?->getLabel() }}
                </x-filament::badge>
            </div>
        </div>

        <div class="fi-pirep-detail-v2-stat-strip">
            <div class="cell">
                <div class="lbl">{{ __('pireps.flight_time') }}</div>
                <div class="val">{{ Time::minutesToTimeString((int) ($record->flight_time ?? 0)) }}</div>
                @if ($timeDelta !== null)
                    <div class="delta {{ $timeDelta <= 0 ? 'good' : '' }}">
                        {{ $timeDelta >= 0 ? '+' : '' }}{{ $timeDelta }}m vs plan
                    </div>
                @endif
            </div>
            <div class="cell">
                <div class="lbl">{{ __('common.distance') }}</div>
                <div class="val">
                    @if ($record->distance){{ number_format((float) $record->distance->local()) }}<span class="unit">{{ $unitDistance }}</span>@else —@endif
                </div>
                @if ($distancePlanned)
                    <div class="delta">{{ number_format((float) $distancePlanned) }} planned</div>
                @endif
            </div>
            <div class="cell">
                <div class="lbl">{{ __('pireps.score') }}</div>
                <div class="val {{ $scoreClass }}">{{ $record->score ?? '—' }}</div>
                <div class="delta">/ 100</div>
            </div>
            <div class="cell">
                <div class="lbl">{{ __('pireps.landing_rate') }}</div>
                <div class="val {{ $landingClass }}">
                    @if ($record->landing_rate){{ number_format((float) $record->landing_rate) }}<span class="unit">fpm</span>@else —@endif
                </div>
            </div>
            <div class="cell">
                <div class="lbl">{{ __('pireps.fuel_used') }}</div>
                <div class="val">
                    @if ($record->fuel_used){{ number_format((float) $record->fuel_used->local()) }}<span class="unit">{{ $unitFuel }}</span>@else —@endif
                </div>
                @if ($fuelRemaining !== null)
                    <div class="delta">{{ number_format($fuelRemaining) }} remaining</div>
                @endif
            </div>
            <div class="cell">
                <div class="lbl">Cruise</div>
                <div class="val">{{ $cruiseFL }}</div>
                @if ($cruiseFt > 0)
                    <div class="delta">{{ number_format($cruiseFt) }} ft</div>
                @endif
            </div>
        </div>
    </div>
</div>