@extends('app')

@section('title', $pirep->ident)

@section('content')
    <x-vms::page name="pirep-detail" label="PIREP detail">
        <div class="grid gap-3">
            <a href="{{ route('frontend.pireps.index') }}" wire:navigate class="w-fit text-xs no-underline">← Logbook</a>

            @include('pireps.show-summary', ['pirep' => $pirep])

            <div class="grid grid-cols-1 gap-3 min-[860px]:grid-cols-[2fr_1fr]">
                @include('pireps.show-content', ['pirep' => $pirep])
                @include('pireps.show-details', ['pirep' => $pirep])
            </div>

            <x-render-hook :hook="new \App\Features\Hooks\Points\RenderingPirepDetail(\App\Models\Pirep::query()->findOrFail($pirep->id))" />
        </div>
    </x-vms::page>
@endsection
