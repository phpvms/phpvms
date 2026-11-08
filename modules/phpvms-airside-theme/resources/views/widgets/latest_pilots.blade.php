<ul class="vms-widget m-0 grid list-none rounded-md p-0" role="list">
    @foreach ($users as $u)
        <li wire:key="latest-pilot-{{ $u->id }}" class="flex items-baseline gap-3 px-4 py-2 text-[13px]">
            <span class="font-mono font-semibold">{{ $u->ident }}</span>
            <span class="min-w-0 truncate">{{ $u->name_private }}</span>
        </li>
    @endforeach
</ul>
