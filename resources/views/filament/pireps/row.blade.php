@php
    use App\Enums\PirepState;
    use App\Filament\Resources\Pireps\PirepResource;
    use App\Filament\Resources\Users\UserResource;
    use App\Support\Units\Time;
    use Filament\Actions\Action;
    use Filament\Support\Icons\Heroicon;

    /** @var \App\Models\Pirep $record */
    /** @var string $recordKey */

    $pilotName = $record->user?->name ?? '—';
    $initials = collect(explode(' ', trim((string) $pilotName)))
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    if ($initials === '') {
        $initials = '?';
    }

    // Deterministic avatar hue from user id
    $hue = abs(crc32((string) ($record->user_id ?? $record->id))) % 360;
    $avatarStyle = sprintf(
        'background: hsl(%d, 70%%, 90%%); color: hsl(%d, 60%%, 30%%);',
        $hue,
        $hue,
    );

    // Stat color helpers
    $scoreClass = match (true) {
        $record->score === null => 'fi-pirep-chip-neutral',
        $record->score >= 90 => 'fi-pirep-chip-good',
        $record->score >= 70 => 'fi-pirep-chip-warn',
        default => 'fi-pirep-chip-bad',
    };

    $landing = (float) ($record->landing_rate ?? 0);
    $landingClass = match (true) {
        $landing === 0.0 => 'fi-pirep-chip-neutral',
        $landing > 0 => 'fi-pirep-chip-bad',
        $landing >= -500 && $landing <= -50 => 'fi-pirep-chip-good',
        $landing < -1000 => 'fi-pirep-chip-bad',
        default => 'fi-pirep-chip-warn',
    };

    $stateColor = match ($record->state) {
        PirepState::PENDING => 'warning',
        PirepState::ACCEPTED => 'success',
        PirepState::REJECTED => 'danger',
        default => 'gray',
    };

    $sourceName = filled($record->source_name)
        ? $record->source->getLabel().' · '.$record->source_name
        : $record->source?->getLabel();

    $dptIcao = $record->dpt_airport_id;
    $arrIcao = $record->arr_airport_id;
    $dptName = $record->dpt_airport?->name;
    $arrName = $record->arr_airport?->name;

    $blockOff = $record->block_off_time?->format('H:i').'Z';
    $blockOn = $record->block_on_time?->format('H:i').'Z';

    $userUrl = $record->user
        ? UserResource::getUrl('edit', ['record' => $record->user])
        : null;

    // Build per-record action instances. Actions are configured on PirepsTable.
    // The "view" action navigates to the dedicated ViewPirep page instead of
    // mounting a modal, so it's a plain URL action defined inline here rather
    // than via the table.
    $table = $this->getTable();
    $acceptAction = $table->getAction('accept')?->record($record);
    $rejectAction = $table->getAction('reject')?->record($record);
    $viewUrl = PirepResource::getUrl('view', ['record' => $record]);
    $viewAction = Action::make('view')
        ->color('info')
        ->icon(Heroicon::Eye)
        ->label(__('pireps.view_pirep'))
        ->url($viewUrl)
        ->livewire($this);
    $editAction   = $table->getAction('edit')?->record($record);
    $deleteAction = $table->getAction('delete')?->record($record);
    $forceDeleteAction = $table->getAction('forceDelete')?->record($record);
    $restoreAction = $table->getAction('restore')?->record($record);
@endphp

<div class="fi-pirep-row">
    {{-- Header line: avatar + ident + pilot + aircraft + state badge + actions --}}
    <div class="fi-pirep-row-head">
        <div
            class="fi-pirep-avatar"
            style="{{ $avatarStyle }}"
            aria-hidden="true"
        >{{ $initials }}</div>

        <div class="fi-pirep-row-head-text">
            <div class="fi-pirep-row-title">
                <a href="{{ $viewUrl }}" class="fi-pirep-ident">{{ $record->ident }}</a>
                <span class="fi-pirep-meta">·</span>
                @if ($userUrl)
                    <a href="{{ $userUrl }}" class="fi-pirep-pilot-link">{{ $pilotName }}</a>
                @else
                    <span>{{ $pilotName }}</span>
                @endif
                @if ($record->aircraft)
                    <span class="fi-pirep-meta">·</span>
                    <span class="fi-pirep-meta">{{ $record->aircraft->registration }} · {{ $record->aircraft->name }}</span>
                @endif
            </div>
            <div class="fi-pirep-meta fi-pirep-submeta">
                @if ($record->submitted_at)
                    <span title="{{ $record->submitted_at->format('d-m-Y H:i') }}">
                        {{ $record->submitted_at->diffForHumans() }}
                    </span>
                @endif
                @if (filled($sourceName))
                    <span>·</span>
                    <span>{{ $sourceName }}</span>
                @endif
            </div>
        </div>

        <div class="fi-pirep-row-head-right">
            <x-filament::badge :color="$stateColor">
                {{ $record->state?->getLabel() }}
            </x-filament::badge>

            <div class="fi-pirep-row-actions">
                @if ($viewAction && $viewAction->isVisible())
                    {{ $viewAction }}
                @endif

                @if ($acceptAction && $acceptAction->isVisible())
                    {{ $acceptAction }}
                @endif

                @if ($rejectAction && $rejectAction->isVisible())
                    {{ $rejectAction }}
                @endif

                <x-filament::dropdown placement="bottom-end">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            class="fi-icon-btn fi-pirep-row-actions-trigger"
                            aria-label="{{ __('filament-actions::group.trigger.label') }}"
                        >
                            {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::EllipsisVertical) }}
                        </button>
                    </x-slot>

                    <x-filament::dropdown.list>
                        @foreach ([$editAction, $deleteAction, $forceDeleteAction, $restoreAction] as $action)
                            @if ($action && $action->isVisible())
                                {{ $action }}
                            @endif
                        @endforeach
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            </div>
        </div>
    </div>

    {{-- Combined row: route box (fixed) + flight stat boxes (flex) --}}
    <div class="fi-pirep-row-line">
        <div class="fi-pirep-route">
            <div class="fi-pirep-route-endpoint" @if ($dptName) title="{{ $dptName }}" @endif>
                <div class="fi-pirep-route-icao">{{ $dptIcao }}</div>
                @if (filled($blockOff) && $blockOff !== 'Z')
                    <div class="fi-pirep-route-time">{{ $blockOff }}</div>
                @endif
            </div>
            <div class="fi-pirep-route-bar" aria-hidden="true"></div>
            <div class="fi-pirep-route-endpoint" @if ($arrName) title="{{ $arrName }}" @endif>
                <div class="fi-pirep-route-icao">{{ $arrIcao }}</div>
                @if (filled($blockOn) && $blockOn !== 'Z')
                    <div class="fi-pirep-route-time">{{ $blockOn }}</div>
                @endif
            </div>
        </div>

        <div class="fi-pirep-stat-grid">
            <div class="fi-pirep-stat-box fi-pirep-chip-neutral">
                <span aria-hidden="true">⏱</span>
                {{ Time::minutesToTimeString((int) ($record->flight_time ?? 0)) }}
            </div>

            @if ($record->distance)
                <div class="fi-pirep-stat-box fi-pirep-chip-neutral">
                    <span aria-hidden="true">📏</span>
                    {{ number_format((float) $record->distance->local()) }} {{ setting('units.distance') }}
                </div>
            @endif

            @if ($record->score !== null)
                <div class="fi-pirep-stat-box {{ $scoreClass }}">
                    <span aria-hidden="true">⭐</span>
                    {{ $record->score }}
                </div>
            @endif

            @if ($record->landing_rate)
                <div class="fi-pirep-stat-box {{ $landingClass }}">
                    <span aria-hidden="true">📉</span>
                    {{ number_format((float) $record->landing_rate) }} fpm
                </div>
            @endif
        </div>
    </div>

    {{-- Old stats row — kept for reference / additional information.
         Currently rendered inline with the route row above. --}}
    {{--
    <div class="fi-pirep-stats">
        <span class="fi-pirep-chip fi-pirep-chip-neutral">
            <span aria-hidden="true">⏱</span>
            {{ Time::minutesToTimeString((int) ($record->flight_time ?? 0)) }}
        </span>

        @if ($record->distance)
            <span class="fi-pirep-chip fi-pirep-chip-neutral">
                <span aria-hidden="true">📏</span>
                {{ number_format((float) $record->distance->local()) }} {{ setting('units.distance') }}
            </span>
        @endif

        @if ($record->score !== null)
            <span class="fi-pirep-chip {{ $scoreClass }}">
                <span aria-hidden="true">⭐</span>
                {{ $record->score }}
            </span>
        @endif

        @if ($record->landing_rate)
            <span class="fi-pirep-chip {{ $landingClass }}">
                <span aria-hidden="true">📉</span>
                {{ number_format((float) $record->landing_rate) }} fpm
            </span>
        @endif
    </div>
    --}}
</div>
