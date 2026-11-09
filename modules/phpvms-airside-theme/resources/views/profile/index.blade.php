@extends('app')
@section('title', $profile->name)

@section('content')
    @php
        $memberYear = $profile->memberSince ? \Illuminate\Support\Carbon::parse($profile->memberSince)->utc()->format('Y') : '—';
        $initials = collect(preg_split('/\s+/', trim($profile->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    @endphp

    <x-vms::page name="profile" :label="$profile->name.', pilot profile'">
        <x-vms::page.header :title="$profile->name">
            <div class="flex flex-wrap items-center gap-2">
                <span x-data="{ failed: false }" class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-full text-sm font-semibold">
                    @if ($profile->avatar)
                        <img src="{{ $profile->avatar }}" alt="{{ $profile->name }}" class="size-full object-cover" x-show="! failed" x-on:error="failed = true" />
                        <span x-show="failed" x-cloak>{{ $initials }}</span>
                    @else
                        {{ $initials }}
                    @endif
                </span>
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
        </x-vms::page.header>

        <div class="grid gap-4">
            <x-ts-card header="Record" aria-label="Statistics">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <x-ts-stats title="Total Hours"><span class="font-semibold tabular-nums text-cyan-600 dark:text-cyan-400 font-mono text-2xl">{{ $profile->flightTimeMinutes ?? '—' }}</span></x-ts-stats>
                    <x-ts-stats title="Flights"><span class="font-semibold tabular-nums text-green-600 dark:text-green-400 text-2xl">{{ $profile->flights ?? '—' }}</span></x-ts-stats>
                    <x-ts-stats title="Home"><span class="font-semibold tabular-nums text-primary-600 dark:text-primary-400 font-mono text-xl">{{ $profile->homeAirport?->icao ?? '—' }}</span></x-ts-stats>
                    <x-ts-stats title="Current"><span class="font-semibold tabular-nums text-amber-600 dark:text-amber-400 font-mono text-xl">{{ $profile->currentAirport?->icao ?? '—' }}</span></x-ts-stats>
                </div>
            </x-ts-card>

            @if (count($profile->typeRatings))
                <x-ts-card header="Type ratings" aria-label="Type ratings">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($profile->typeRatings as $rating)
                            <x-ts-badge color="slate" light lg>
                                <span class="mr-1 font-mono font-semibold">{{ $rating->type }}</span>
                                {{ $rating->name }}
                            </x-ts-badge>
                        @endforeach
                    </div>
                </x-ts-card>
            @endif

            <x-ts-card aria-label="Awards">
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
            </x-ts-card>

            <x-ts-card aria-label="Tours completed">
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
                                        <x-vms::ui.icon name="map" class="size-5" />
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
            </x-ts-card>

            @if (count($profile->fields))
                <x-ts-card header="Details" aria-label="Fields">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        @foreach ($profile->fields as $field)
                            <div class="grid gap-0.5">
                                <dt>{{ $field->name }}</dt>
                                <dd class="text-[13px]">{{ $field->value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ts-card>
            @endif

            {{-- Addon extension point: cards for the profile body. The context carries the profile (with isOwnProfile). --}}
            <x-render-hook :hook="new \App\Features\Hooks\Points\RenderingProfileCards($profile)" />

            @if ($profile->isOwnProfile && ($profileEdit ?? null))
                <livewire:profile::profile-edit />

                @if (count($profileEdit->connections))
                    <x-ts-card aria-label="Connected accounts">
                        <x-slot:header>
        <div class="grid gap-0.5"><h2 class="text-sm font-semibold">{{ 'Connected accounts' }}</h2><p class="text-xs font-normal text-dark-500 dark:text-dark-400">{{ 'Sign in to phpvms with an external account.' }}</p></div>
    </x-slot:header>
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
                                            <x-ts-button sm outline color="red" type="button" data-prompt="{{ 'Unlink '.$connection->displayName.'?' }}" data-confirm="Unlink" data-keep="Keep" x-on:click="$tsui.interaction('dialog').wireable().question($escapeHtml($el.dataset.prompt)).confirm($el.dataset.confirm, () => $el.closest('form').submit()).cancel($el.dataset.keep).send()">Unlink</x-ts-button>
                                        </form>
                                    @elseif ($connection->linkable)
                                        <x-ts-button sm outline href="{{ url('/oauth/'.rawurlencode($connection->connectionId).'/redirect?intent=link') }}">Link</x-ts-button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-ts-card>
                @endif

                <livewire:profile::api-access />
                <livewire:profile::api-connections />
            @endif
        </div>
    </x-vms::page>
@endsection
