<!DOCTYPE html>
<html lang="en">

<head>
    @include('partials.head')
</head>

{{-- Token showcase: skylight's palette, type, radii and the TallStackUI components in airside's personalisation. Not routed. --}}
<body x-data="airsideTheme" class="bg-bg text-ink">
    @php
        $ink = [50, 100, 150, 200, 300, 400, 500, 600, 700, 800, 850, 900, 950];
        $accent = [50 => 'bg-primary-50', 100 => 'bg-primary-100', 200 => 'bg-primary-200', 300 => 'bg-primary-300', 400 => 'bg-primary-400', 500 => 'bg-primary-500', 600 => 'bg-primary-600', 700 => 'bg-primary-700', 800 => 'bg-primary-800', 900 => 'bg-primary-900', 950 => 'bg-primary-950'];
        $status = ['accent' => 'Accent', 'cyan' => 'Cyan', 'green' => 'Green', 'amber' => 'Amber', 'red' => 'Red'];
        $radii = ['sm' => ['6px', 'rounded-sm'], 'md' => ['8px', 'rounded-md'], 'lg' => ['10px', 'rounded-lg'], 'xl' => ['12px', 'rounded-xl']];
    @endphp

    <div class="pv-container grid gap-8 py-8">
        <header class="flex items-center justify-between gap-4">
            <div>
                <p class="micro">airside</p>
                <h1 class="text-xl font-semibold">Token showcase</h1>
            </div>
            <div class="flex gap-2" role="group" aria-label="Theme mode">
                @foreach (['light', 'dark', 'auto'] as $themeMode)
                    <button type="button" class="rounded-md border border-line-strong px-3 py-1.5 text-[13px] hover:bg-hover" :class="mode === '{{ $themeMode }}' ? 'bg-accent-soft text-accent' : ''" @click="select('{{ $themeMode }}')">{{ ucfirst($themeMode) }}</button>
                @endforeach
            </div>
        </header>

        <section>
            <h2 class="pv-eyebrow">Surfaces and ink</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                @foreach (['bg' => 'bg-bg', 'panel' => 'bg-panel', 'panel-inset' => 'bg-panel-inset', 'hover' => 'bg-hover', 'line' => 'bg-line', 'line-strong' => 'bg-line-strong', 'ink' => 'bg-ink'] as $label => $class)
                    <div class="rounded-md border border-line p-3 {{ $class }} {{ in_array($label, ['line', 'line-strong', 'ink']) ? 'h-16' : '' }}">
                        <span class="micro {{ $label === 'ink' ? '!text-bg' : '' }}">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 grid grid-cols-7 gap-1 sm:grid-cols-13">
                @foreach ($ink as $step)
                    <div class="rounded-sm border border-line" style="background: var(--pv-ink-{{ $step }}); height: 40px" title="ink-{{ $step }}"></div>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-ink-dim">Ink ramp 50 to 950 (also <code>dark-*</code> in TallStackUI).</p>
        </section>

        <section>
            <h2 class="pv-eyebrow">Accent (TallStackUI primary) and status</h2>
            <div class="flex flex-wrap gap-1">
                @foreach ($accent as $step => $class)
                    <div class="grid h-12 w-14 place-items-end rounded-sm p-1 text-[10px] {{ $class }} {{ $step >= 400 ? 'text-white' : 'text-ink' }}">{{ $step }}</div>
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($status as $token => $label)
                    <span class="inline-flex items-center gap-2 rounded-full border border-line bg-panel px-3 py-1 text-[13px]">
                        <span class="size-2 rounded-full" style="background: var(--pv-{{ $token }})"></span>{{ $label }}
                    </span>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="pv-eyebrow">Type</h2>
            <div class="grid gap-1">
                <p class="text-base font-semibold">Inter 600, 16px: Dispatch workspace</p>
                <p>Inter 400, 14px body copy, line height 1.45, with <span class="tnum">tabular 0123456789</span> figures.</p>
                <p class="micro">micro label, uppercase tracked</p>
                <p class="font-mono text-[13px]">IBM Plex Mono: KJFK → EGLL  TRK 084°  DIST 3 451 nm  BLOCK 07:12</p>
            </div>
        </section>

        <section>
            <h2 class="pv-eyebrow">Radii, hairlines, no shadows</h2>
            <div class="flex flex-wrap gap-3">
                @foreach ($radii as $name => [$px, $class])
                    <div class="grid size-24 place-items-center border border-line bg-panel text-xs text-ink-dim {{ $class }}">{{ $name }} {{ $px }}</div>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="pv-eyebrow">Components (TallStackUI, personalised)</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ts-card header="Card" class="!shadow-none">
                    <p class="text-[13px] text-ink-dim">Hairline border, panel surface, no shadow.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ts-button>Primary</x-ts-button>
                        <x-ts-button outline>Outline</x-ts-button>
                        <x-ts-button light>Light</x-ts-button>
                        <x-ts-button color="red">Danger</x-ts-button>
                        <x-ts-button sm disabled>Disabled</x-ts-button>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-ts-badge text="Badge" />
                        <x-ts-badge text="Green" color="green" />
                        <x-ts-badge text="Amber" color="amber" />
                    </div>
                </x-ts-card>

                <x-airside::auth-card title="Form controls" description="Plain inputs share the same tokens.">
                    <x-airside::field name="callsign" label="Callsign" value="PVA101" />
                    <x-airside::field name="password" type="password" label="Password" />
                    <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" checked /> Remember me</label>
                    <x-airside::alert kind="success">Saved the profile.</x-airside::alert>
                    <x-airside::alert kind="error">The bid could not be removed.</x-airside::alert>
                </x-airside::auth-card>
            </div>
        </section>

        <section>
            <h2 class="pv-eyebrow">Component kit (x-airside::)</h2>
            <div class="grid gap-6">
                <x-airside::page name="showcase" label="Kit page">
                    <x-airside::page-header headline="Logbook" title="My PIREPs" description="Every flight you have filed, most recent first.">
                        <x-slot:links>
                            <x-airside::state-badge state-color="gray" label="12 total" />
                        </x-slot:links>
                    </x-airside::page-header>
                    <x-airside::back-link href="#">flight manifest</x-airside::back-link>
                </x-airside::page>

                <div class="grid gap-4 md:grid-cols-4">
                    <x-airside::stat label="Flights" value="128" />
                    <x-airside::stat label="Hours" value="342:10" mono accent="mag" />
                    <x-airside::stat label="Landing rate" value="-142" small accent="green" />
                    <x-airside::stat label="Rank" :value="null" accent="amber" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <x-airside::card title="Flight ident" description="Initials fallback and stacked layout.">
                        <div class="grid gap-3">
                            <x-airside::flight-ident callsign="PVA101" dpt="KJFK" arr="EGLL" airline="Phpvms Air" aircraft="N123PV · B738" />
                            <x-airside::flight-ident callsign="AP9100" dpt="KJFK" arr="EGLL" stacked />
                            <x-airside::flight-ident callsign="PVA202" dpt="LFPG" arr="LEMD" airline="Phpvms Air" size="lg" />
                        </div>
                        <x-slot:footer>
                            <x-airside::stats :items="[['label' => 'ETE', 'value' => '7:12'], ['label' => 'Dist', 'value' => '3 451 nm'], ['label' => 'Expires', 'value' => null]]" />
                        </x-slot:footer>
                    </x-airside::card>

                    <x-airside::card title="State badges" variant="subtle">
                        <div class="flex flex-wrap gap-2">
                            @foreach (['success' => 'Accepted', 'warning' => 'Pending', 'danger' => 'Rejected', 'info' => 'In progress', 'gray' => 'Draft'] as $state => $text)
                                <x-airside::state-badge :state-color="$state" :label="$text" />
                            @endforeach
                        </div>
                        <div class="mt-4"><x-airside::loading text="Loading flights" /></div>
                    </x-airside::card>

                    <x-airside::card title="Confirm and drawer">
                        <div class="grid gap-3">
                            <x-airside::confirm prompt="Cancel this bid?" confirm-label="Yes, cancel it" keep-label="Keep">
                                <x-slot:trigger><x-ts-button sm light type="button">Cancel bid</x-ts-button></x-slot:trigger>
                            </x-airside::confirm>

                            <x-airside::drawer title="Flight details" description="Opens from the right.">
                                <x-slot:trigger><x-ts-button sm outline type="button">Open drawer</x-ts-button></x-slot:trigger>
                                <p class="text-[13px] text-ink-dim">Escape, the backdrop or the close button dismiss it.</p>
                                <x-slot:footer><x-ts-button sm type="button" x-on:click="open = false">Done</x-ts-button></x-slot:footer>
                            </x-airside::drawer>

                            <x-airside::drawer title="Bottom sheet" side="bottom">
                                <x-slot:trigger><x-ts-button sm outline type="button">Open bottom</x-ts-button></x-slot:trigger>
                                <p class="text-[13px] text-ink-dim">Slides up on small screens.</p>
                            </x-airside::drawer>
                        </div>
                    </x-airside::card>
                </div>

                <x-airside::empty icon="map" title="No PIREPs yet" description="Fly a flight and it will show up in your logbook.">
                    <x-slot:actions><x-ts-button sm type="button">Find a flight</x-ts-button></x-slot:actions>
                </x-airside::empty>
            </div>
        </section>

        <section>
            <h2 class="pv-eyebrow">Toast and scroll</h2>
            <div class="flex flex-wrap items-start gap-4">
                <div class="pv-toast success"><span class="tag">OK</span><span class="text">Bid placed</span></div>
                <div class="pv-toast error"><span class="tag">FAULT</span><span class="text">Aircraft already reserved</span></div>
                <div class="scroll-thin h-20 w-48 overflow-auto rounded-md border border-line bg-panel p-2 text-xs">
                    @for ($i = 1; $i <= 12; $i++)
                        <p>scroll-thin row {{ $i }}</p>
                    @endfor
                </div>
            </div>
        </section>
    </div>

    @livewireScripts
</body>

</html>
