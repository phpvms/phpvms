@props(['title', 'flights', 'direction' => 'inbound'])

{{--
  One side of the airport traffic board. Props: title, flights (a collection of Flight with the airports loaded),
  direction (inbound shows the departure airport, outbound the arrival airport).
--}}
<x-airside::card :title="$title">
    @if ($flights->isEmpty())
        <p class="py-4 text-center text-[13px] text-ink-dim">@lang('flights.none')</p>
    @else
        <div class="-mx-4 overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead>
                    <tr class="micro border-b border-line">
                        <th scope="col" class="px-4 py-2 font-medium">@lang('airports.ident')</th>
                        <th scope="col" class="px-4 py-2 font-medium">{{ $direction === 'inbound' ? __('airports.departure') : __('airports.arrival') }}</th>
                        <th scope="col" class="px-4 py-2 text-right font-medium">@lang('flights.dep')</th>
                        <th scope="col" class="px-4 py-2 text-right font-medium">@lang('flights.arr')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($flights as $flight)
                        @php
                            $other = $direction === 'inbound' ? $flight->dpt_airport : $flight->arr_airport;
                            $otherId = $direction === 'inbound' ? $flight->dpt_airport_id : $flight->arr_airport_id;
                        @endphp
                        <tr wire:key="{{ $direction }}-{{ $flight->id }}" class="border-b border-line last:border-b-0">
                            <td class="px-4 py-2">
                                <a href="{{ route('frontend.flights.show', [$flight->id]) }}" wire:navigate class="font-mono font-semibold text-accent hover:underline">{{ $flight->ident }}</a>
                            </td>
                            <td class="px-4 py-2">
                                {{ $other?->name }}
                                <a href="{{ route('frontend.airports.show', ['id' => $otherId]) }}" wire:navigate class="font-mono text-accent hover:underline">{{ $otherId }}</a>
                            </td>
                            <td class="px-4 py-2 text-right font-mono">{{ $flight->dpt_time }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ $flight->arr_time }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-airside::card>
