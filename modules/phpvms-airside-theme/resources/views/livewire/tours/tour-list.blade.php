{{-- The tours index. State comes from App\Features\Tour\Livewire\TourList; the tabs filter in the browser. --}}
@php
    $tours = $this->tours;
    $allCount = count($tours);
    $mineCount = count(array_filter($tours, fn ($tour): bool => $tour->status !== null));
@endphp
<div class="grid gap-6" x-data="{ filter: 'all', all: {{ $allCount }}, mine: {{ $mineCount }}, get count() { return this.filter === 'mine' ? this.mine : this.all } }">
    <x-vms::page.header
        headline="Flights"
        title="Tours"
        description="Multi-leg journeys flown in order. Bid a tour once and every leg is reserved for you."
    >
        <x-slot:links>
            <x-ts-badge color="slate" light><span x-text="count + (count === 1 ? ' tour' : ' tours')">{{ $allCount }} {{ $allCount === 1 ? 'tour' : 'tours' }}</span></x-ts-badge>
        </x-slot:links>
    </x-vms::page.header>

    <div class="flex w-fit gap-1 rounded-md p-1" role="group" aria-label="Tour filter">
        @foreach (['all' => 'All tours', 'mine' => 'My tours'] as $value => $label)
            <button
                type="button"
                class="rounded px-3 py-1 text-[13px] font-medium"
                x-bind:aria-pressed="filter === '{{ $value }}' ? 'true' : 'false'"
                x-on:click="filter = '{{ $value }}'"
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($allCount > 0)
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($tours as $tour)
                <x-vms::tours.card
                    wire:key="tour-{{ $tour->id }}"
                    :tour="$tour"
                    :error="$cancelErrors[$tour->id] ?? null"
                />
            @endforeach
        </div>
        <x-vms::ui.empty
            x-show="filter === 'mine' && mine === 0"
            x-cloak
            icon="map"
            title="No tours of your own yet"
            description="Bid a tour from the All tours list and it will show up here."
        />
    @else
        <x-vms::ui.empty icon="map" title="No tours yet" description="When your airline publishes a tour, it will appear here ready to bid." />
    @endif
</div>
