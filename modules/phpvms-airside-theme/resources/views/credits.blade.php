@extends('app')

@section('title', 'Credits')

@section('content')
    <x-airside::page name="credits" label="Credits">
        <x-airside::page-header headline="About" title="Credits" description="phpvms and the addons installed on this site." />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-airside::card title="phpvms" description="Open-source virtual airline management">
                <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" alt="" class="w-full rounded-md" />
                <x-slot:footer>
                    <a href="https://docs.phpvms.net" target="_blank" rel="noopener" class="text-accent hover:underline">Documents and guides</a>
                    <a href="https://docs.phpvms.net/#license" target="_blank" rel="noopener" class="text-accent hover:underline">License</a>
                </x-slot:footer>
            </x-airside::card>

            <div class="grid content-start gap-3">
                @foreach ($modules as $module)
                    <x-airside::card :title="$module->name" :description="$module->description" wire:key="module-{{ $loop->index }}">
                        @if ($module->version)
                            <p class="font-mono text-[13px] text-ink-dim">Version {{ $module->version }}</p>
                        @endif
                        <x-slot:footer>
                            <x-airside::state-badge :state-color="$module->active ? 'success' : 'warning'" :label="$module->active ? 'Active' : 'Not active'" />
                            <span class="flex flex-wrap gap-3">
                                @if ($module->attribution)
                                    <a href="{{ $module->attribution->url }}" target="_blank" rel="noopener" class="text-accent hover:underline">{{ $module->attribution->text }}</a>
                                @endif
                                @if ($module->readme_url)
                                    <a href="{{ $module->readme_url }}" target="_blank" rel="noopener" class="text-accent hover:underline">Readme</a>
                                @endif
                                @if ($module->license_url)
                                    <a href="{{ $module->license_url }}" target="_blank" rel="noopener" class="text-accent hover:underline">License</a>
                                @endif
                            </span>
                        </x-slot:footer>
                    </x-airside::card>
                @endforeach
            </div>
        </div>
    </x-airside::page>
@endsection
