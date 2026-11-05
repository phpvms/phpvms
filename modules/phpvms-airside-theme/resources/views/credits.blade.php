@extends('app')

@section('title', 'Credits')

@section('content')
    <x-vms::page name="credits" label="Credits">
        <x-vms::page.header title="Credits" description="phpvms and the addons installed on this site." />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <x-ts-card>
                <x-slot:header>
                    <div class="grid gap-0.5"><h2 class="text-sm font-semibold">phpvms</h2><p class="text-xs font-normal text-dark-500 dark:text-dark-400">Open-source virtual airline management</p></div>
                </x-slot:header>
                <img src="{{ public_asset('/assets/img/logo_blue_bg.svg') }}" alt="" class="w-full rounded-md" />
                <x-slot:footer>
                    <a href="https://docs.phpvms.net" target="_blank" rel="noopener" class="hover:underline">Documents and guides</a>
                    <a href="https://docs.phpvms.net/#license" target="_blank" rel="noopener" class="hover:underline">License</a>
                </x-slot:footer>
            </x-ts-card>

            <div class="grid content-start gap-3">
                @foreach ($modules as $module)
                    <x-ts-card wire:key="module-{{ $loop->index }}">
                        <x-slot:header>
                            <div class="grid gap-0.5"><h2 class="text-sm font-semibold">{{ $module->name }}</h2><p class="text-xs font-normal text-dark-500 dark:text-dark-400">{{ $module->description }}</p></div>
                        </x-slot:header>
                        @if ($module->version)
                            <p class="font-mono text-[13px]">Version {{ $module->version }}</p>
                        @endif
                        <x-slot:footer>
                            <x-ts-badge :color="\Modules\PhpvmsAirsideTheme\Support\StateColor::tallstack($module->active ? 'success' : 'warning')" light :data-state="$module->active ? 'success' : 'warning'">{{ $module->active ? 'Active' : 'Not active' }}</x-ts-badge>
                            <span class="flex flex-wrap gap-3">
                                @if ($module->attribution)
                                    <a href="{{ $module->attribution->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $module->attribution->text }}</a>
                                @endif
                                @if ($module->readme_url)
                                    <a href="{{ $module->readme_url }}" target="_blank" rel="noopener" class="hover:underline">Readme</a>
                                @endif
                                @if ($module->license_url)
                                    <a href="{{ $module->license_url }}" target="_blank" rel="noopener" class="hover:underline">License</a>
                                @endif
                            </span>
                        </x-slot:footer>
                    </x-ts-card>
                @endforeach
            </div>
        </div>
    </x-vms::page>
@endsection
