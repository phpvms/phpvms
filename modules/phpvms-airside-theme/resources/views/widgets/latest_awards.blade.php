@if ($awards->count() > 0)
    <div class="pv-widget overflow-x-auto rounded-md">
        <table class="w-full text-left text-[13px]">
            <thead>
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">Ident</th>
                    <th scope="col" class="px-4 py-2 font-medium">Name</th>
                    <th scope="col" class="px-4 py-2 font-medium">Award</th>
                    <th scope="col" class="px-4 py-2 font-medium">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($awards as $a)
                    <tr wire:key="award-{{ $a->id }}">
                        <td class="px-4 py-2 font-mono">{{ $a->user?->ident }}</td>
                        <td class="px-4 py-2">{{ $a->user?->name_private }}</td>
                        <td class="px-4 py-2">{{ $a->award?->name }}</td>
                        <td class="px-4 py-2 font-mono">{{ $a->created_at->format('d.M.Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
