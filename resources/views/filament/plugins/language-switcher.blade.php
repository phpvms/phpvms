<x-filament::dropdown
    placement="bottom-end"
    width="flags-only"
    maxHeight="max-content"
    teleport
    class="fi-dropdown fi-user-menu"
    data-nosnippet="true"
>
    <x-slot name="trigger">
        <div
        class="flex items-center justify-center w-10 h-9 rounded-lg ring-2 ring-inset ring-gray-200 hover:ring-gray-300 dark:ring-gray-500 hover:dark:ring-gray-400"
            x-tooltip="{
                content: @js($getLabel(app()->getLocale())),
                theme: $store.theme,
                placement: 'bottom'
            }"
        >
            <img 
                src="{{ $getFlag(app()->getLocale()) }}"
                class="h-full w-full object-cover object-center rounded-md" 
                alt="{{ $getLabel(app()->getLocale()) }}"
                />
        </div>
    </x-slot>

    <x-filament::dropdown.list @class(array: ['!border-t-0 space-y-1 !p-2.5'])>
        @foreach ($locales as $locale)
            @if (!app()->isLocale($locale))
                <a
                    href="{{ route('frontend.lang.switch', ['lang' => $locale]) }}"                   
                    class="flex items-center w-full justify-start space-x-2 rtl:space-x-reverse p-1 transition-colors duration-75 rounded-md outline-none fi-dropdown-list-item whitespace-nowrap disabled:pointer-events-none disabled:opacity-70 fi-dropdown-list-item-color-gray hover:bg-gray-950/5 focus:bg-gray-950/5 dark:hover:bg-white/5 dark:focus:bg-white/5"
                >
                    <img 
                        src="{{ $getFlag($locale) }}"
                        class="object-cover object-center rounded-lg w-7 h-7" 
                        alt="{{ $getLabel($locale) }}"
                    />

                    <span class="text-sm font-medium text-gray-600 hover:bg-transparent dark:text-gray-200">
                        {{ $getLabel($locale) }}
                    </span>
                </a>
            @endif
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
