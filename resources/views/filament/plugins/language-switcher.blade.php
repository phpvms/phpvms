{{--
    Locale switcher, rendered inside the user menu dropdown right after the
    profile block (PanelsRenderHook::USER_MENU_PROFILE_AFTER — see
    LanguageSwitcherPlugin). A single "Language" row opens a nested dropdown
    of locale links, the active one omitted. Nesting works because the user
    menu is UserMenuPosition::Sidebar (AdminPanelProvider), so it never
    teleports — the submenu panel stays inside .fi-user-menu, and its
    mousedown listener is scoped to its own trigger, not this row, so opening
    it doesn't toggle the parent menu shut.
--}}
<x-filament::dropdown.list>
    <x-filament::dropdown placement="right-start" :offset="4" width="none">
        <x-slot name="trigger">
            <button type="button" class="fi-dropdown-list-item w-full">
                <div class="fi-dropdown-list-item-image" style="background-image: url('{{ $getFlag($currentLocale) }}')"></div>

                <span class="fi-dropdown-list-item-label">{{ $getLabel($currentLocale) }}</span>

                <x-filament::icon icon="heroicon-m-chevron-right" class="fi-icon h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($locales as $locale)
                @if (!app()->isLocale($locale))
                    <x-filament::dropdown.list.item
                        tag="a"
                        :href="route('frontend.lang.switch', ['lang' => $locale])"
                        :image="$getFlag($locale)"
                    >
                        {{ $getLabel($locale) }}
                    </x-filament::dropdown.list.item>
                @endif
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</x-filament::dropdown.list>
