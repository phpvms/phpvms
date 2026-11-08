@php
    // airside eject: stable ids tie each tab to its panel (aria-controls / aria-labelledby).
    $tabId = 'tab-'.\Illuminate\Support\Str::slug((string) $tab);
    $panelId = 'tabpanel-'.\Illuminate\Support\Str::slug((string) $tab);
@endphp

<div x-show="selected === @js($tab)" role="tabpanel" id="{{ $panelId }}" x-init="tabs.push({ tab: @js($tab), id: @js($tabId), panel: @js($panelId), title: @js($title), right: @js($content['right']), left: @js($content['left']), href: @js($href), navigate: @js((bool) $navigate), navigateHover: @js((bool) $navigateHover) }); @if($shouldRender && $href) selected = @js($tab); @endif" aria-labelledby="{{ $tabId }}">
    @if ($shouldRender)
        {{ $slot }}
    @endif
</div>
