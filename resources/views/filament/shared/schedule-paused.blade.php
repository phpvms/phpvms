@php
    use App\Features\Setup\Support\PauseScheduleDuringSetup;
    use App\Features\Setup\Support\SetupRunState;

    // Short pauses are an update running; only a long one needs the admin.
    $since = PauseScheduleDuringSetup::pausedSince();
    $stuck = $since !== null && $since->lt(now()->subMinutes(30));
@endphp

@if ($stuck)
    <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" class="mb-6">
        <x-slot name="description">
            {{ __('installer.updater.schedule_paused', ['since' => $since->toDayDateTimeString(), 'file' => str_replace(base_path().'/', '', SetupRunState::defaultPath())]) }}
        </x-slot>
    </x-filament::callout>
@endif
