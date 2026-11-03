@props(['title' => null])

{{--
  The app layout: the authenticated pilot shell on TallStackUI's layout.
  Slots: default (page), head (extra <head> markup), scripts (page scripts).
  Pages that still `@extends('app')` reach this through app.blade.php.
  ShellData is request-scoped, so @inject hands every render the current
  request's instance (Octane-safe: nothing holds the request across requests).
--}}
@inject('shell', 'App\Features\Shell\ShellData')
@php($topbar = theme_option('layout') === 'topbar')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-airside-layout="{{ theme_option('layout') }}" x-data="tallstackui_darkTheme({ default: 'system' })" x-bind:class="{ 'dark': darkTheme }">

<head>
    @include('partials.head', ['title' => $title])
    {{ $head ?? '' }}
</head>

<body class="vms-shell antialiased">
    <x-ts-layout>
        {{-- The layout theme option picks one navigation: the sidebar, or the top bar row above the strip. --}}
        @if ($topbar)
            <x-slot:top>
                <x-vms::shell.topbar :shell="$shell" />
            </x-slot:top>
        @else
            <x-slot:menu>
                <x-vms::shell.sidebar :shell="$shell" />
            </x-slot:menu>
        @endif

        {{--
          Nothing in the shell is @persist-ed. The header's mobile menu button is
          bound to the layout scope it rendered in, so a persisted copy stops
          driving the new page's sidebar; and the sector, duty and station must
          follow the page. The METAR is fetched through the cached weather endpoint.
        --}}
        <x-slot:header>
            <x-vms::shell.header :shell="$shell" :topbar="$topbar" />
        </x-slot:header>

        {{ $slot }}
    </x-ts-layout>

    <x-vms::shell.toasts :shell="$shell" />

    @livewireScripts

    {{ $scripts ?? '' }}

    <x-theme-footer />
</body>

</html>
