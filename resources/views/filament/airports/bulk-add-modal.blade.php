{{-- Bulk-add airports modal body. State + actions live on the ListAirports page
     (a Livewire component), so every wire:* here targets that component. The
     Alpine loop drives lookups one row at a time with a delay, keeping the API
     calls sequential and rate-limited. --}}
<div
    x-data="{
        rateMs: 300,
        running: false,
        async run() {
            if (this.running) return;
            this.running = true;
            try {
                while (true) {
                    const remaining = await $wire.processNextBulkAirport();
                    if (! remaining) break;
                    await new Promise((resolve) => setTimeout(resolve, this.rateMs));
                }
            } finally {
                this.running = false;
            }
        },
    }"
    x-on:bulk-add-start.window="run()"
    class="flex flex-col gap-4"
>
    <div class="flex items-start gap-2">
        <div class="flex-1">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="bulkIcaoInput"
                    x-on:keydown.enter.prevent="$wire.addBulkAirports()"
                    placeholder="KJFK, EGLL, LFPG…"
                />
            </x-filament::input.wrapper>
        </div>

        <x-filament::button
            icon="heroicon-o-plus"
            wire:click="addBulkAirports"
            wire:loading.attr="disabled"
            wire:target="addBulkAirports"
        >
            {{ __('common.add') }}
        </x-filament::button>
    </div>

    @if (filled($this->bulkRows))
        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="px-3 py-2 text-start font-medium text-gray-700 dark:text-gray-200">ICAO</th>
                        <th class="px-3 py-2 text-start font-medium text-gray-700 dark:text-gray-200">{{ __('common.name') }}</th>
                        <th class="px-3 py-2 text-center font-medium text-gray-700 dark:text-gray-200">{{ __('airports.hub') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($this->bulkRows as $i => $row)
                        <tr wire:key="bulk-airport-{{ $row['icao'] }}">
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                <span class="inline-flex items-center gap-1.5">
                                    {{ $row['icao'] }}

                                    @switch($row['status'])
                                        @case('pending')
                                            <x-filament::loading-indicator class="h-4 w-4 text-gray-400" />
                                            @break
                                        @case('updated')
                                            <span title="{{ __('common.updated') }}">
                                                <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4 text-warning-500" />
                                            </span>
                                            @break
                                        @case('error')
                                            <span title="{{ __('airports.no_airport_found', ['icao' => $row['icao']]) }}">
                                                <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-4 w-4 text-danger-500" />
                                            </span>
                                            @break
                                        @default
                                            <span title="{{ __('common.added') }}">
                                                <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4 text-success-500" />
                                            </span>
                                    @endswitch
                                </span>
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                @if ($row['status'] === 'error')
                                    <span class="text-danger-500">{{ __('airports.lookup_failed') }}</span>
                                @elseif ($row['status'] === 'pending')
                                    <span class="text-gray-400">…</span>
                                @else
                                    {{ $row['name'] }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @unless (in_array($row['status'], ['error', 'pending'], true))
                                    <x-filament::input.checkbox
                                        wire:click="toggleBulkHub({{ $i }})"
                                        @checked($row['hub'])
                                    />
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
