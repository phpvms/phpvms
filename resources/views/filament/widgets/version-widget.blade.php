<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex justify-between items-center">
            <div class="flex flex-col">
                <img src="{{ public_asset('/assets/img/logo_white_no_bg.svg') }}" width="135px" alt="phpvms Logo" />
                <span class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    title="{{ $version_full }}">{{ $version }}</span>
            </div>
            <div class="flex flex-col items-end gap-y-1">
                <a href="https://docs.phpvms.net/" target="_blank"
                    class="fi-link group/link relative inline-flex items-center justify-center outline-none fi-size-md fi-link-size-md gap-1.5  fi-color-gray"
                    rel="noopener noreferrer">
                    <svg class="fi-link-icon h-5 w-5 text-gray-400 dark:text-gray-500"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                        data-slot="icon">
                        <path
                            d="M10.75 16.82A7.462 7.462 0 0 1 15 15.5c.71 0 1.396.098 2.046.282A.75.75 0 0 0 18 15.06v-11a.75.75 0 0 0-.546-.721A9.006 9.006 0 0 0 15 3a8.963 8.963 0 0 0-4.25 1.065V16.82ZM9.25 4.065A8.963 8.963 0 0 0 5 3c-.85 0-1.673.118-2.454.339A.75.75 0 0 0 2 4.06v11a.75.75 0 0 0 .954.721A7.506 7.506 0 0 1 5 15.5c1.579 0 3.042.487 4.25 1.32V4.065Z">
                        </path>
                    </svg>

                    <span
                        class="font-semibold text-sm text-gray-700 dark:text-gray-200 group-hover/link:underline group-focus-visible/link:underline"
                        style="">
                        Documentation
                    </span>
                </a>
                <a href="https://github.com/phpvms/phpvms" target="_blank"
                    class="fi-link group/link relative inline-flex items-center justify-center outline-none fi-size-md fi-link-size-md gap-1.5  fi-color-gray"
                    rel="noopener noreferrer">
                    <span class="fi-link-icon h-5 w-5 text-gray-400 dark:text-gray-500" style=";">
                        <svg viewBox="0 0 98 96" xmlns="http://www.w3.org/2000/svg">
                            <path clip-rule="evenodd" fill="currentColor" fill-rule="evenodd"
                                d="M48.854 0C21.839 0 0 22 0 49.217c0 21.756 13.993 40.172 33.405 46.69 2.427.49 3.316-1.059 3.316-2.362 0-1.141-.08-5.052-.08-9.127-13.59 2.934-16.42-5.867-16.42-5.867-2.184-5.704-5.42-7.17-5.42-7.17-4.448-3.015.324-3.015.324-3.015 4.934.326 7.523 5.052 7.523 5.052 4.367 7.496 11.404 5.378 14.235 4.074.404-3.178 1.699-5.378 3.074-6.6-10.839-1.141-22.243-5.378-22.243-24.283 0-5.378 1.94-9.778 5.014-13.2-.485-1.222-2.184-6.275.486-13.038 0 0 4.125-1.304 13.426 5.052a46.97 46.97 0 0 1 12.214-1.63c4.125 0 8.33.571 12.213 1.63 9.302-6.356 13.427-5.052 13.427-5.052 2.67 6.763.97 11.816.485 13.038 3.155 3.422 5.015 7.822 5.015 13.2 0 18.905-11.404 23.06-22.324 24.283 1.78 1.548 3.316 4.481 3.316 9.126 0 6.6-.08 11.897-.08 13.526 0 1.304.89 2.853 3.316 2.364 19.412-6.52 33.405-24.935 33.405-46.691C97.707 22 75.788 0 48.854 0z">
                            </path>
                        </svg>
                    </span>

                    <span
                        class="font-semibold text-sm text-gray-700 dark:text-gray-200 group-hover/link:underline group-focus-visible/link:underline"
                        style="">
                        GitHub
                    </span>
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
