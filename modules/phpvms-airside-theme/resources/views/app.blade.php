{{-- `@extends('app')` target for the pages not yet on the component layout; sections map onto its slots. --}}
<x-vms::layouts.app :title="trim($__env->yieldContent('title'))">
    <x-slot:head>
        @yield('css')
        @yield('scripts_head')
    </x-slot:head>

    @yield('content')

    <x-slot:scripts>
        @yield('scripts')
    </x-slot:scripts>
</x-vms::layouts.app>
