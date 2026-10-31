@extends('app')

@section('title', 'Logbook')

@section('content')
    <x-vms::page name="logbook" label="Logbook">
        <x-vms::page.header headline="Logbook" title="My PIREPs" description="Every flight you have filed, most recent first.">
            <x-slot:links>
                <x-ts-badge color="slate" light :text="$pagination['total'].' total'" />
            </x-slot:links>
        </x-vms::page.header>

        @if (count($pireps) > 0)
            <ul class="m-0 grid list-none gap-2 p-0" role="list">
                @foreach ($pireps as $pirep)
                    <li wire:key="pirep-{{ $pirep->id }}">
                        <x-vms::pireps.card :pirep="$pirep" />
                    </li>
                @endforeach
            </ul>
        @else
            <x-vms::ui.empty icon="notebook" title="No PIREPs yet" description="Fly a flight and it will show up in your logbook." />
        @endif

        <x-vms::pireps.pagination :current-page="$pagination['currentPage']" :last-page="$pagination['lastPage']" />
    </x-vms::page>
@endsection
