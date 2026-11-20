@php
    $livewire = $getLivewire();
    $run = $livewire->run();
@endphp

<div class="space-y-4">
    @if ($livewire->screen() === 'done')
        <x-filament::section>
            <div class="space-y-4">
                <p>{{ __('installer.updater.done') }}</p>
                {{-- Full load: wire:navigate into another panel leaves its Alpine components half-initialised. --}}
                <x-filament::button tag="a" :href="$livewire->adminUrl()" :spa-mode="false">
                    {{ __('installer.updater.go_to_admin') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($run?->status === \App\Features\Setup\Enums\RunStatus::Requested)
        <x-filament::section>
            <p class="text-base font-semibold">{{ __('installer.updater.waiting_for_cron') }}</p>
        </x-filament::section>
    @endif

    @if ($livewire->screen() === 'failed')
        <x-filament::section>
            <x-slot name="heading">
                {{ __('installer.updater.failed_at', ['step' => $livewire->failedStep() ?? '']) }}
            </x-slot>

            <div x-data class="space-y-3">
                <pre x-ref="error" class="overflow-x-auto whitespace-pre-wrap rounded-lg bg-gray-950 p-3 text-xs text-danger-400">{{ $run?->error }}</pre>
                <div class="flex gap-2">
                    <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText($refs.error.innerText)">
                        {{ __('installer.updater.copy') }}
                    </x-filament::button>
                    <x-filament::button size="sm" wire:click="retry">
                        {{ __('installer.updater.retry') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        @if ($run?->progress)
            <p class="mb-3 text-sm">
                {{ $run->progress['done'] }} / {{ $run->progress['total'] }}
                @if ($run->progress['item'])
                    &middot; {{ $run->progress['item'] }}
                @endif
            </p>
        @endif

        <ul class="space-y-1 text-sm">
            @foreach (\App\Features\Setup\Enums\SetupStep::cases() as $step)
                @php($status = $run?->steps[$step->value]['status'] ?? \App\Features\Setup\Enums\StepStatus::Pending)
                <li class="flex justify-between">
                    <span>{{ $step->label() }}</span>
                    <span @class([
                        'text-success-600' => $status === \App\Features\Setup\Enums\StepStatus::Done,
                        'text-danger-600' => $status === \App\Features\Setup\Enums\StepStatus::Failed,
                        'text-primary-600' => $status === \App\Features\Setup\Enums\StepStatus::Running,
                        'text-gray-500' => in_array($status, [\App\Features\Setup\Enums\StepStatus::Pending, \App\Features\Setup\Enums\StepStatus::Skipped], true),
                    ])>{{ $status->value }}</span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('installer.updater.log') }}</x-slot>

        {{-- Sticks to the bottom as polls append lines, unless the user scrolled up. --}}
        <div
            x-data="{ stick: true }"
            x-init="
                $refs.log.scrollTop = $refs.log.scrollHeight;
                new MutationObserver(() => { if (stick) $refs.log.scrollTop = $refs.log.scrollHeight })
                    .observe($refs.log, { childList: true, characterData: true, subtree: true });
            "
            class="space-y-3"
        >
            <pre
                x-ref="log"
                x-on:scroll="stick = $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 8"
                class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ implode("\n", $run?->logTail ?? []) }}</pre>
            <x-filament::button size="sm" color="gray" x-on:click="navigator.clipboard.writeText($refs.log.innerText)">
                {{ __('installer.updater.copy') }}
            </x-filament::button>
        </div>
    </x-filament::section>
</div>
