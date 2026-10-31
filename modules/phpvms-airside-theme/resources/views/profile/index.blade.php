@extends('app')
@section('title', $profile->name)

@section('content')
    @php
        $memberYear = $profile->memberSince ? \Illuminate\Support\Carbon::parse($profile->memberSince)->utc()->format('Y') : '—';
        $initials = collect(preg_split('/\s+/', trim($profile->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    @endphp

    <x-airside::page name="profile" :label="$profile->name.', pilot profile'">
        <x-airside::page-header headline="Pilot profile">
            <div class="flex items-center gap-3">
                <span x-data="{ failed: false }" class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-full text-sm font-semibold">
                    @if ($profile->avatar)
                        <img src="{{ $profile->avatar }}" alt="{{ $profile->name }}" class="size-full object-cover" x-show="! failed" x-on:error="failed = true" />
                        <span x-show="failed" x-cloak>{{ $initials }}</span>
                    @else
                        {{ $initials }}
                    @endif
                </span>
                <h1 class="text-xl font-semibold">{{ $profile->name }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($profile->airline)
                    <x-ts-badge color="slate" light :text="$profile->airline->icao.' · '.$profile->airline->name" />
                @endif
                @if ($profile->rank)
                    <x-ts-badge color="primary" light :text="$profile->rank->name" />
                @endif
                <x-ts-badge color="slate" light :text="$profile->state->label" />
                <span class="text-xs">Member since {{ $memberYear }}</span>
            </div>

            @if ($profile->isOwnProfile && $profile->acars)
                <x-slot:links>
                    <x-ts-button href="{{ route('frontend.profile.acars') }}" sm outline>ACARS config</x-ts-button>
                </x-slot:links>
            @endif
        </x-airside::page-header>

        <div class="grid gap-4">
            <x-airside::card title="Record" aria-label="Statistics">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <x-airside::stat label="Total Hours" :value="$profile->flightTimeMinutes" mono accent="cyan" />
                    <x-airside::stat label="Flights" :value="$profile->flights" accent="green" />
                    <x-airside::stat label="Home" :value="$profile->homeAirport?->icao" mono small accent="mag" />
                    <x-airside::stat label="Current" :value="$profile->currentAirport?->icao" mono small accent="amber" />
                </div>
            </x-airside::card>

            @if (count($profile->typeRatings))
                <x-airside::card title="Type ratings" aria-label="Type ratings">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($profile->typeRatings as $rating)
                            <x-ts-badge color="slate" light lg>
                                <span class="mr-1 font-mono font-semibold">{{ $rating->type }}</span>
                                {{ $rating->name }}
                            </x-ts-badge>
                        @endforeach
                    </div>
                </x-airside::card>
            @endif

            <x-airside::card aria-label="Awards">
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">Awards &amp; merits</h2>
                        <x-ts-badge color="slate" light :text="count($profile->awards).' earned'" />
                    </div>
                </x-slot:header>

                @if (count($profile->awards) === 0)
                    <p class="text-[13px]">No awards earned yet.</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($profile->awards as $award)
                            <div class="flex items-start gap-3 rounded-md p-3" aria-label="{{ $award->name }}, earned">
                                <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-md">
                                    @if ($award->image)
                                        <img src="{{ $award->image }}" alt="" class="size-full object-contain" />
                                    @else
                                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <circle cx="12" cy="9" r="6" />
                                            <path d="M8.5 14 7 22l5-3 5 3-1.5-8" />
                                        </svg>
                                    @endif
                                </span>
                                <div class="grid min-w-0 gap-0.5">
                                    <h3 class="text-[13px] font-semibold">{{ $award->name }}</h3>
                                    <p class="text-xs">{{ $award->description ?? 'Qualifier unavailable' }}</p>
                                    <span>Earned</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-airside::card>

            <x-airside::card aria-label="Tours completed">
                <x-slot:header>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">Tours completed</h2>
                        <x-ts-badge color="slate" light :text="count($profile->tours).' completed'" />
                    </div>
                </x-slot:header>

                @if (count($profile->tours) === 0)
                    <p class="text-[13px]">No tours completed yet.</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($profile->tours as $tour)
                            <div class="flex items-start gap-3 rounded-md p-3" aria-label="{{ $tour->name }}, completed">
                                <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-full">
                                    @if ($tour->image)
                                        <img src="{{ $tour->image }}" alt="" class="size-full object-cover" />
                                    @else
                                        <x-airside::icon name="map" class="size-5" />
                                    @endif
                                </span>
                                <div class="grid min-w-0 gap-0.5">
                                    <h3 class="text-[13px] font-semibold">{{ $tour->name }}</h3>
                                    <p class="text-xs">{{ $tour->legs }} {{ $tour->legs === 1 ? 'leg' : 'legs' }}</p>
                                    @if ($tour->completedAt)
                                        <span>Completed {{ \Illuminate\Support\Carbon::parse($tour->completedAt)->utc()->format('M j, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-airside::card>

            @if (count($profile->fields))
                <x-airside::card title="Details" aria-label="Fields">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        @foreach ($profile->fields as $field)
                            <div class="grid gap-0.5">
                                <dt>{{ $field->name }}</dt>
                                <dd class="text-[13px]">{{ $field->value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-airside::card>
            @endif

            {{-- Addon extension point: cards for the profile body. The context carries the profile (with isOwnProfile). --}}
            <x-slot-outlet name="profile.cards" :context="['profile' => $profile]" />

            @if ($profile->isOwnProfile && ($profileEdit ?? null))
                <livewire:profile::profile-edit />

                @if (count($profileEdit->connections))
                    <x-airside::card title="Connected accounts" description="Sign in to phpvms with an external account." aria-label="Connected accounts">
                        <ul class="grid gap-3">
                            @foreach ($profileEdit->connections as $connection)
                                <li class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="grid gap-1">
                                        <span class="text-[13px] font-medium">{{ $connection->displayName }}</span>
                                        <span class="flex flex-wrap items-center gap-2">
                                            <x-ts-badge :color="$connection->linked ? 'green' : 'slate'" light :text="$connection->linked ? 'Connected' : 'Not connected'" />
                                            @if ($connection->providerUserId)
                                                <span class="font-mono text-xs">{{ $connection->providerUserId }}</span>
                                            @endif
                                        </span>
                                    </div>

                                    @if ($connection->linked)
                                        <form method="post" action="{{ route('oauth.unlink', ['provider' => $connection->connectionId]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-airside::confirm :prompt="'Unlink '.$connection->displayName.'?'" confirm-label="Unlink">
                                                <x-slot:trigger>
                                                    <x-ts-button sm outline color="red" type="button">Unlink</x-ts-button>
                                                </x-slot:trigger>
                                                <x-slot:confirm>
                                                    <x-ts-button sm color="red" type="submit">Unlink</x-ts-button>
                                                </x-slot:confirm>
                                            </x-airside::confirm>
                                        </form>
                                    @elseif ($connection->linkable)
                                        <x-ts-button sm outline href="{{ url('/oauth/'.rawurlencode($connection->connectionId).'/redirect?intent=link') }}">Link</x-ts-button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-airside::card>
                @endif

                <livewire:profile::api-access />
                <livewire:profile::api-connections />
            @endif
        </div>
    </x-airside::page>
@endsection
