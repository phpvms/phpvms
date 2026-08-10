{{--
    Sticky section-jump bar for edit pages that stack their relation managers
    (see App\Filament\Concerns\StacksRelationManagers).

    Sits at the top of the page body rather than in the hero band: the band
    scrolls away, this stays. Links scroll to (and expand) the matching section
    by DOM id. The optional switcher jumps to a sibling record.

    Reuses .subtab from the PIREP view's in-page tabs so every page with an
    in-page nav navigates the same way.
--}}
@php
    $sectionIds = array_column($sectionLinks, 'id');
    $switcher ??= [];
    $switcherLabel ??= '';
@endphp

<nav
    class="secnav"
    aria-label="{{ __('filament.page_sections') }}"
    x-data="{
        active: @js($sectionIds[0] ?? ''),
        init() {
            const ids = @js($sectionIds);
            const spy = () => {
                let current = ids[0];
                for (const id of ids) {
                    const el = document.getElementById(id);
                    if (el && el.getBoundingClientRect().top <= 120) {
                        current = id;
                    }
                }
                this.active = current;
            };
            window.addEventListener('scroll', spy, { passive: true });
            spy();
        },
        go(id) {
            const el = document.getElementById(id);
            if (el) {
                window.dispatchEvent(new CustomEvent('expand-section', { detail: { id } }));
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
    }"
>
    <div class="secnav__links">
        @foreach ($sectionLinks as $link)
            <a
                href="#{{ $link['id'] }}"
                class="subtab"
                @click.prevent="go('{{ $link['id'] }}')"
                x-bind:aria-selected="active === '{{ $link['id'] }}' ? 'true' : 'false'"
            >
                {{ $link['label'] }}
                @if (($link['count'] ?? null) !== null)
                    <em>{{ $link['count'] }}</em>
                @endif
            </a>
        @endforeach
    </div>

    @if ($switcher)
        <select
            class="secnav__switch"
            x-on:change="if ($event.target.value) window.location.href = $event.target.value"
            aria-label="{{ __('filament.record_switch') }}"
        >
            <option value="">{{ $switcherLabel }}</option>
            @foreach ($switcher as $option)
                <option value="{{ $option['url'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
    @endif
</nav>
