@extends('app')
@inject('shell', 'App\Features\Shell\ShellData')

@section('title', __('home.welcome.title'))

@section('content')
    <x-airside::page name="home" label="Home">
        <x-airside::page-header
            :headline="__('home.welcome.title')"
            :title="__('home.welcome.message', ['appname' => $shell->appName()])"
        />

        <section class="grid gap-3" aria-labelledby="newest-pilots-heading">
            <h2 id="newest-pilots-heading" class="pv-eyebrow">{{ __('common.newest_pilots') }}</h2>

            <ul class="m-0 grid list-none gap-3 p-0 sm:grid-cols-2 lg:grid-cols-4" role="list">
                @foreach ($users as $user)
                    <li wire:key="pilot-{{ $user->id }}">
                        <a href="{{ route('frontend.profile.show', [$user->id]) }}" wire:navigate
                           class="flex items-center gap-3 rounded-md border border-line bg-panel p-4 text-ink no-underline hover:bg-panel-inset">
                            <img class="size-12 shrink-0 rounded-full border border-line-strong object-cover"
                                 src="{{ $user->avatar === null ? $user->gravatar(123) : $user->avatar->url }}" alt="" />
                            <span class="grid min-w-0">
                                <span class="truncate font-semibold">{{ $user->name_private }}</span>
                                @if (filled($user->home_airport))
                                    <span class="font-mono text-[13px] text-ink-dim">{{ $user->home_airport->icao }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </x-airside::page>
@endsection
