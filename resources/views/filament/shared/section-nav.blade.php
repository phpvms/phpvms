{{--
    Sticky record-switcher bar inside the form tab of edit pages that use
    App\Filament\Concerns\HasRelationManagerTabs. The switcher jumps to a
    sibling record; the tab hides the bar when there is none.

    Sits in the page body rather than in the hero band: the band scrolls away,
    this stays.
--}}
@php
    $switcher ??= [];
    $switcherLabel ??= '';
@endphp

<nav class="secnav" aria-label="{{ __('filament.page_sections') }}">
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
