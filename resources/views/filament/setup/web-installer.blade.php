@php($screen = $this->screen())

<x-filament-panels::page.simple>
    {{-- Keyed per screen: the loop starts with the progress element and ends when a retry or failure replaces it.
         Idle means the mount-time request lost the lock to cron, so the loop asks again. --}}
    <div
        wire:key="web-installer-{{ $screen }}"
        class="space-y-4"
        @if (in_array($screen, ['progress', 'idle'], true))
            x-data="{
                async drive() {
                    while (this.$el.isConnected) {
                        let outcome

                        // A rejected call (e.g. a gateway timeout while a long step finishes server-side) is a wait.
                        try {
                            outcome = await this.$wire.advance()
                        } catch (error) {
                            outcome = 'wait'
                        }

                        if (outcome === 'finished' || outcome === 'failed') {
                            return
                        }

                        if (outcome === 'wait') {
                            await new Promise((resolve) => setTimeout(resolve, 2000))
                        }
                    }
                },
            }"
            x-init="drive()"
        @endif
    >
        @if ($this->waitingForCron)
            <x-filament::section>
                <div class="space-y-3">
                    <p>{{ __('installer.web_installer.cron_required') }}</p>
                    <pre class="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $this->cronLine() }}</pre>
                </div>
            </x-filament::section>
        @endif

        @include('filament.system.updater.progress', ['getLivewire' => fn () => $this])
    </div>
</x-filament-panels::page.simple>
