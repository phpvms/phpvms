{{--
    Every pilot part-way through this tour right now, from the
    (bundle_id, status) index on user_tours. Progress and the active leg are
    read off the row's own columns -- the `legs` JSON is never parsed here.

    tours: Collection<UserTour>, already eager-loaded with user and flight.
--}}
<div class="fi-section-content">
    @if ($tours->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament.bundles.live_tours.empty') }}
        </p>
    @else
        <div class="fi-ta-ctn overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="fi-ta-table w-full text-start text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-start font-medium">{{ __('filament.bundles.live_tours.pilot') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('filament.bundles.live_tours.progress') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('filament.bundles.live_tours.flight') }}</th>
                        <th class="px-3 py-2 text-start font-medium">{{ __('filament.bundles.live_tours.started') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($tours as $tour)
                        <tr>
                            <td class="px-3 py-2">{{ $tour->user?->name ?? __('filament.bundles.live_tours.no_flight') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ $tour->legs_completed }} / {{ $tour->legs_total }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono">
                                @if ($tour->flight !== null)
                                    {{ $tour->flight->ident }}
                                    <span class="text-gray-500 dark:text-gray-400">{{ $tour->flight->dpt_airport_id }} → {{ $tour->flight->arr_airport_id }}</span>
                                @else
                                    {{ __('filament.bundles.live_tours.no_flight') }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $tour->started_at?->diffForHumans() ?? __('filament.bundles.live_tours.no_flight') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
