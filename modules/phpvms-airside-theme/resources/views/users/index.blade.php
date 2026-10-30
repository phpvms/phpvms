@extends('app')

@section('title', trans_choice('common.pilot', 2))

@section('content')
    @php
        // The controller reads `orderBy` and `sortedBy` (App\Queries\UserSearchQuery); a header link flips the direction of the active column.
        $activeColumn = request()->query('orderBy');
        $activeDirection = request()->query('sortedBy') === 'desc' ? 'desc' : 'asc';
        $columns = [
            'id'              => ['label' => 'ID', 'align' => 'text-left'],
            'name'            => ['label' => __('common.name'), 'align' => 'text-left'],
            'airline_id'      => ['label' => __('common.airline'), 'align' => 'text-left'],
            'curr_airport_id' => ['label' => __('user.location'), 'align' => 'text-left'],
            'flights'         => ['label' => trans_choice('common.flight', 2), 'align' => 'text-right'],
            'flight_time'     => ['label' => trans_choice('common.hour', 2), 'align' => 'text-right'],
        ];
    @endphp

    <x-airside::page name="roster" label="Pilot roster">
        <x-airside::page-header headline="Crew" :title="trans_choice('common.pilot', 2)">
            <x-slot:links>
                <x-ts-badge color="slate" light :text="$users->total().' total'" />
            </x-slot:links>
        </x-airside::page-header>

        @if ($users->isEmpty())
            <x-airside::empty icon="users" :title="trans_choice('common.pilot', 2)" description="No pilots to show." />
        @else
            <div class="overflow-x-auto rounded-md border border-line bg-panel">
                <table class="w-full text-[13px]" id="users-table">
                    <thead>
                        <tr class="micro border-b border-line">
                            <th scope="col" class="w-12 px-4 py-2"><span class="sr-only">Avatar</span></th>
                            @foreach ($columns as $column => $meta)
                                @php
                                    $isActive = $activeColumn === $column;
                                    $nextDirection = $isActive && $activeDirection === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th scope="col" class="px-4 py-2 font-medium {{ $meta['align'] }}" @if ($isActive) aria-sort="{{ $activeDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    <a href="{{ request()->fullUrlWithQuery(['orderBy' => $column, 'sortedBy' => $nextDirection, 'page' => null]) }}" wire:navigate
                                       class="inline-flex items-center gap-1 text-inherit no-underline hover:text-ink {{ $isActive ? 'text-ink' : '' }}">
                                        {{ $meta['label'] }}
                                        @if ($isActive)
                                            <span aria-hidden="true">{{ $activeDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="border-b border-line last:border-b-0 hover:bg-panel-inset">
                                <td class="px-4 py-2">
                                    <img class="size-8 rounded-full border border-line-strong object-cover"
                                         src="{{ $user->avatar === null ? $user->gravatar(128) : $user->avatar->url }}" alt="" />
                                </td>
                                <td class="px-4 py-2 font-mono text-ink-dim">{{ $user->id }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('frontend.users.show.public', [$user->id]) }}" class="font-medium text-accent hover:underline" wire:navigate>
                                        <span class="font-mono">{{ $user->ident }}</span> {{ $user->name_private }}
                                    </a>
                                    @if (filled($user->country))
                                        <span class="ml-1 text-[11px] uppercase text-ink-faint" title="{{ $country->alpha2($user->country)['name'] ?? $user->country }}">{{ $user->country }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-mono">{{ $user->airline?->icao }}</td>
                                <td class="px-4 py-2 font-mono">{{ $user->current_airport ? $user->curr_airport_id : '-' }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ $user->flights }}</td>
                                <td class="px-4 py-2 text-right font-mono">@minutestotime($user->flight_time)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-center">
                {{ $users->withQueryString()->links('pagination.bootstrap-5') }}
            </div>
        @endif
    </x-airside::page>
@endsection
