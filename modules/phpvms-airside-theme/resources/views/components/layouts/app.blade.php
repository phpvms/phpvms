@props(['title' => null])

{{--
  The app layout: the authenticated pilot shell on TallStackUI's layout.
  Slots: default (page), head (extra <head> markup), scripts (page scripts).
  Pages that still `@extends('app')` reach this through app.blade.php.
  ShellData is request-scoped, so @inject hands every render the current
  request's instance (Octane-safe: nothing holds the request across requests).
--}}
@inject('shell', 'App\Features\Shell\ShellData')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme({ default: 'system' })" x-bind:class="{ 'dark': darkTheme }">

<head>
    @include('partials.head', ['title' => $title])
    {{ $head ?? '' }}
</head>

<body class="bg-white text-dark-900 antialiased dark:bg-dark-900 dark:text-dark-100">
    <x-ts-layout>
        <x-slot:menu>
            <x-airside::shell.sidebar :shell="$shell" />
        </x-slot:menu>

        {{--
          Nothing in the shell is @persist-ed. The header's mobile menu button is
          bound to the layout scope it rendered in, so a persisted copy stops
          driving the new page's sidebar; and the sector, duty and station must
          follow the page. The METAR is fetched through the cached weather endpoint.
        --}}
        <x-slot:header>
            <x-airside::shell.header :shell="$shell" />
        </x-slot:header>

        {{ $slot }}
    </x-ts-layout>

    <x-airside::shell.toasts :shell="$shell" />

    @livewireScripts

    {{ $scripts ?? '' }}
</body>

</html>
