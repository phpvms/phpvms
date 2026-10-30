@extends('app')

@section('title', 'Logbook')

@section('content')
    <x-airside::page name="logbook" label="Logbook">
        <x-airside::page-header headline="Logbook" title="My PIREPs" description="Every flight you have filed, most recent first.">
            <x-slot:links>
                <x-ts-badge color="slate" light :text="$pagination['total'].' total'" />
            </x-slot:links>
        </x-airside::page-header>

        @if (count($pireps) > 0)
            <ul class="m-0 grid list-none gap-2 p-0" role="list">
                @foreach ($pireps as $pirep)
                    <li wire:key="pirep-{{ $pirep->id }}">
                        <x-airside::logbook-card :pirep="$pirep" />
                    </li>
                @endforeach
            </ul>
        @else
            <x-airside::empty icon="notebook" title="No PIREPs yet" description="Fly a flight and it will show up in your logbook." />
        @endif

        <x-airside::logbook-pagination :current-page="$pagination['currentPage']" :last-page="$pagination['lastPage']" />
    </x-airside::page>
@endsection
