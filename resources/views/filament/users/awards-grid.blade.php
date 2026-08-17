{{--
    The pilot's earned awards, as a tile grid on their admin profile.

    Read-only on purpose — attaching and detaching stays in the Awards relation
    manager tab below, which is built for it. This answers "what has this pilot
    earned" at a glance, which a three-column table does not.

    Every tile carries the same accent tint: categories are free-form (admins
    invent their own), so there is no fixed set to map colours onto.
--}}
@php
    $awards = $getRecord()?->awards ?? collect();
@endphp

@if ($awards->isEmpty())
    <p class="text-sm" style="color: var(--ink-4);">{{ __('filament.user_awards_empty') }}</p>
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach ($awards as $award)
            @php
                // Icon wins over image when both are set. Icon sets rename and
                // drop glyphs between releases, so a name saved months ago may
                // no longer resolve — that must not take down the profile.
                $glyph = null;

                if (filled($award->icon)) {
                    try {
                        $glyph = svg($award->icon, 'w-5 h-5')->toHtml();
                    } catch (\Throwable) {
                        $glyph = null;
                    }
                }
            @endphp

            <div
                class="rounded-xl border p-3 flex flex-col items-center text-center gap-2"
                style="border-color: var(--line); background: var(--surface);"
                @if (filled($award->description)) title="{{ strip_tags($award->description) }}" @endif
            >
                <div
                    class="w-10 h-10 rounded-full grid place-items-center overflow-hidden shrink-0"
                    style="background: color-mix(in oklab, var(--primary-600) 12%, transparent); color: var(--primary-600);"
                >
                    @if ($glyph)
                        {!! $glyph !!}
                    @elseif (filled($award->image))
                        <img src="{{ $award->image }}" alt="" class="w-full h-full object-cover">
                    @else
                        <x-filament::icon :icon="\Filafly\Icons\Phosphor\Enums\Phosphor::MedalLight" class="w-5 h-5" />
                    @endif
                </div>

                <div class="text-xs font-medium leading-tight" style="color: var(--ink);">
                    {{ $award->name }}
                </div>

                @if (filled($award->category))
                    <div class="text-[10px] uppercase tracking-wide" style="color: var(--ink-4);">
                        {{ $award->category }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
