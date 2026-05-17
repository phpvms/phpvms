@php
    use App\Enums\PirepState;
    use App\Filament\Resources\Users\UserResource;
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    // Caller passes $record via @include. Fall back to schema-component $getRecord()
    // for backwards compatibility if this partial is ever reused as a schema View.
    $record = $record ?? (isset($getRecord) ? $getRecord() : null);

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

    $scoreClass = match (true) {
        $record->score === null => '',
        $record->score >= 90 => 'fi-pirep-modal-stat-good',
        $record->score >= 70 => 'fi-pirep-modal-stat-warn',
        default => 'fi-pirep-modal-stat-bad',
    };

    $landing = (float) ($record->landing_rate ?? 0);
    $landingClass = match (true) {
        $landing === 0.0 => '',
        $landing > 0 => 'fi-pirep-modal-stat-bad',
        $landing >= -500 && $landing <= -50 => 'fi-pirep-modal-stat-good',
        $landing < -1000 => 'fi-pirep-modal-stat-bad',
        default => 'fi-pirep-modal-stat-warn',
    };

    $stateColor = match ($record->state) {
        PirepState::PENDING => 'warning',
        PirepState::ACCEPTED => 'success',
        PirepState::REJECTED => 'danger',
        default => 'gray',
    };

    $userUrl = $record->user
        ? UserResource::getUrl('edit', ['record' => $record->user])
        : null;

    $sourceLabel = filled($record->source_name)
        ? $record->source?->getLabel().' · '.$record->source_name
        : $record->source?->getLabel();

    $unitDistance = setting('units.distance');
    $unitFuel = setting('units.fuel');
@endphp

<div class="fi-pirep-detail">
    {{-- Header --}}
    <div class="fi-pirep-modal-header">
        <div
            class="fi-pirep-avatar"
            style="background: hsl({{ $hue }}, 70%, 90%); color: hsl({{ $hue }}, 60%, 30%); width: 3rem; height: 3rem; font-size: 1rem;"
            aria-hidden="true"
        >{{ $initials }}</div>
        <div style="flex: 1; min-width: 0;">
            <div class="fi-pirep-modal-title">
                {{ $record->ident }}
                <span style="font-weight: 400; color: var(--color-gray-500); font-size: 0.875rem;">
                    · {{ $record->dpt_airport_id }} → {{ $record->arr_airport_id }}
                </span>
            </div>
            <div class="fi-pirep-meta fi-pirep-submeta">
                @if ($userUrl)
                    <a href="{{ $userUrl }}" class="fi-pirep-pilot-link">{{ $pilotName }}</a>
                @else
                    <span>{{ $pilotName }}</span>
                @endif
                @if ($record->aircraft)
                    <span>·</span>
                    <span>{{ $record->aircraft->registration }} {{ $record->aircraft->name }}</span>
                @endif
                @if ($record->submitted_at)
                    <span>·</span>
                    <span title="{{ $record->submitted_at->format('d-m-Y H:i') }}">
                        Filed {{ $record->submitted_at->diffForHumans() }}
                    </span>
                @endif
                @if (filled($sourceLabel))
                    <span>·</span>
                    <span>via {{ $sourceLabel }}</span>
                @endif
            </div>
        </div>
        <x-filament::badge :color="$stateColor" size="lg">
            {{ $record->state?->getLabel() }}
        </x-filament::badge>
    </div>

    {{-- Stats tiles --}}
    <div class="fi-pirep-modal-stats">
        <div class="fi-pirep-modal-stat">
            <div class="fi-pirep-modal-stat-label">{{ __('pireps.flight_time') }}</div>
            <div class="fi-pirep-modal-stat-value">
                {{ Time::minutesToTimeString((int) ($record->flight_time ?? 0)) }}
            </div>
        </div>
        <div class="fi-pirep-modal-stat">
            <div class="fi-pirep-modal-stat-label">{{ __('common.distance') }}</div>
            <div class="fi-pirep-modal-stat-value">
                @if ($record->distance)
                    {{ number_format((float) $record->distance->local()) }} {{ $unitDistance }}
                @else
                    —
                @endif
            </div>
        </div>
        <div class="fi-pirep-modal-stat">
            <div class="fi-pirep-modal-stat-label">{{ __('pireps.score') }}</div>
            <div class="fi-pirep-modal-stat-value {{ $scoreClass }}">
                {{ $record->score ?? '—' }}
            </div>
        </div>
        <div class="fi-pirep-modal-stat">
            <div class="fi-pirep-modal-stat-label">{{ __('pireps.landing_rate') }}</div>
            <div class="fi-pirep-modal-stat-value {{ $landingClass }}">
                @if ($record->landing_rate)
                    {{ number_format((float) $record->landing_rate) }} fpm
                @else
                    —
                @endif
            </div>
        </div>
        <div class="fi-pirep-modal-stat">
            <div class="fi-pirep-modal-stat-label">{{ __('pireps.fuel_used') }}</div>
            <div class="fi-pirep-modal-stat-value">
                @if ($record->fuel_used)
                    {{ number_format((float) $record->fuel_used->local()) }} {{ $unitFuel }}
                @else
                    —
                @endif
            </div>
        </div>
        <div class="fi-pirep-modal-stat">
            <div class="fi-pirep-modal-stat-label">{{ __('pireps.planned_level') }}</div>
            <div class="fi-pirep-modal-stat-value">
                @if ($record->level)
                    FL{{ str_pad((string) (int) ($record->level / 100), 3, '0', STR_PAD_LEFT) }}
                @else
                    —
                @endif
            </div>
        </div>
    </div>

    {{-- Two-column body --}}
    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;" class="fi-pirep-detail-body">
        <div>
            {{-- Route panel --}}
            <div class="fi-pirep-route" style="margin-bottom: 1rem;">
                <div class="fi-pirep-route-endpoint">
                    <div class="fi-pirep-route-icao">{{ $record->dpt_airport_id }}</div>
                    @if ($record->dpt_airport?->name)
                        <div class="fi-pirep-route-time">{{ $record->dpt_airport->name }}</div>
                    @endif
                    @if ($record->block_off_time)
                        <div class="fi-pirep-route-time">{{ $record->block_off_time->format('H:i') }}Z</div>
                    @endif
                </div>
                <div class="fi-pirep-route-bar" aria-hidden="true"></div>
                <div class="fi-pirep-route-endpoint">
                    <div class="fi-pirep-route-icao">{{ $record->arr_airport_id }}</div>
                    @if ($record->arr_airport?->name)
                        <div class="fi-pirep-route-time">{{ $record->arr_airport->name }}</div>
                    @endif
                    @if ($record->block_on_time)
                        <div class="fi-pirep-route-time">{{ $record->block_on_time->format('H:i') }}Z</div>
                    @endif
                </div>
            </div>

            {{-- Notes & route string --}}
            @if (filled($record->route) || filled($record->notes))
                <details open style="background: var(--color-gray-50); border-radius: 0.5rem; padding: 0.75rem 1rem;">
                    <summary style="cursor: pointer; font-weight: 600; color: var(--color-gray-700);">
                        {{ __('common.notes') }}
                    </summary>
                    @if (filled($record->route))
                        <div style="margin-top: 0.5rem;">
                            <div class="fi-pirep-modal-stat-label">{{ __('flights.route') }}</div>
                            <code style="display: block; padding: 0.5rem; background: var(--color-white); border-radius: 0.25rem; margin-top: 0.25rem; font-size: 0.75rem;">{{ $record->route }}</code>
                        </div>
                    @endif
                    @if (filled($record->notes))
                        <div style="margin-top: 0.75rem;">
                            <div class="fi-prose">{!! $record->notes !!}</div>
                        </div>
                    @endif
                </details>
            @endif
        </div>

        <div>
            {{-- Comments --}}
            @if ($record->comments && $record->comments->isNotEmpty())
                <div style="background: var(--color-gray-50); border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.75rem;">
                    <div class="fi-pirep-modal-stat-label" style="margin-bottom: 0.5rem;">
                        {{ trans_choice('common.comment', 2) }} ({{ $record->comments->count() }})
                    </div>
                    @foreach ($record->comments as $comment)
                        <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--color-gray-200); font-size: 0.8125rem;">
                            <div class="fi-pirep-meta" style="margin-bottom: 0.125rem;">
                                {{ $comment->user?->name ?? '—' }} ·
                                <span title="{{ $comment->created_at?->format('d-m-Y H:i') }}">
                                    {{ $comment->created_at?->diffForHumans() }}
                                </span>
                            </div>
                            <div>{{ $comment->comment }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Custom fields --}}
            @if ($record->fields && $record->fields->isNotEmpty())
                <div style="background: var(--color-gray-50); border-radius: 0.5rem; padding: 0.75rem; margin-bottom: 0.75rem;">
                    <div class="fi-pirep-modal-stat-label" style="margin-bottom: 0.5rem;">
                        {{ __('pireps.fields') }}
                    </div>
                    @foreach ($record->fields as $field)
                        <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.8125rem;">
                            <span class="fi-pirep-meta">{{ $field->name }}</span>
                            <span>{{ filled($field->value) ? $field->value : '—' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Transactions --}}
            @if ($record->transactions && $record->transactions->isNotEmpty())
                <div style="background: var(--color-gray-50); border-radius: 0.5rem; padding: 0.75rem;">
                    <div class="fi-pirep-modal-stat-label" style="margin-bottom: 0.5rem;">
                        {{ trans_choice('common.transaction', 2) }} ({{ $record->transactions->count() }})
                    </div>
                    @foreach ($record->transactions as $tx)
                        <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.8125rem;">
                            <span class="fi-pirep-meta">{{ $tx->memo ?? '—' }}</span>
                            <span style="font-variant-numeric: tabular-nums;">
                                @if ($tx->credit && $tx->credit > 0)
                                    <span style="color: rgb(6 95 70);">+{{ number_format($tx->credit / 100, 2) }} {{ $tx->currency }}</span>
                                @elseif ($tx->debit && $tx->debit > 0)
                                    <span style="color: rgb(153 27 27);">-{{ number_format($tx->debit / 100, 2) }} {{ $tx->currency }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    @media (min-width: 1024px) {
        .fi-pirep-detail-body {
            grid-template-columns: 2fr 1fr !important;
        }
    }
</style>
