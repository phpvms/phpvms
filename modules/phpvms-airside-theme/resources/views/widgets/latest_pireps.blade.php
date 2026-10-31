<ul class="vms-widget m-0 grid list-none rounded-md p-0" role="list">
    @foreach ($pireps as $p)
        <li wire:key="latest-pirep-{{ $p->id }}" class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-4 py-2 text-[13px]">
            <span class="font-mono font-semibold">{{ $p->ident }}</span>
            <span class="font-mono">
                <a href="{{ route('frontend.airports.show', [$p->dpt_airport_id]) }}" class="hover:underline" wire:navigate>{{ $p->dpt_airport_id }}</a>
                <span aria-hidden="true">→</span><span class="sr-only">to</span>
                <a href="{{ route('frontend.airports.show', [$p->arr_airport_id]) }}" class="hover:underline" wire:navigate>{{ $p->arr_airport_id }}</a>
            </span>
            <span class="font-mono">{{ $p->aircraft?->ident }}</span>
        </li>
    @endforeach
</ul>
