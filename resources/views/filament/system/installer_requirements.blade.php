<style>
  .requirements-section .fi-section-content {
    padding: 0;
  }
</style>

<x-filament::section class="mb-4 requirements-section">

  <header class="fi-section-header flex flex-col gap-3 px-6 py-4">
    <div class="flex items-center gap-3">
      <div class="grid flex-1 gap-y-1">
        <x-filament::section.heading>
          <div class="flex justify-between items-center">
            <div class="flex items-center gap-2">
              PHP Version
              <x-filament::badge>
                {{ $php['version'] }}
              </x-filament::badge>
            </div>

            <x-filament::badge :color="$php['passed'] === true ? 'success' : 'danger'">
              @if($php['passed'] === true)
                OK
              @else
                Failed
              @endif
            </x-filament::badge>
          </div>
        </x-filament::section.heading>
      </div>
    </div>
  </header>
</x-filament::section>

<x-filament::section class="mb-4 requirements-section">
  <x-slot name="heading">
    <div class="flex justify-between items-center">
      <div>
        PHP Extensions
      </div>

      <x-filament::badge :color="$extensionsPassed ? 'success' : 'danger'">
        @if($extensionsPassed)
          OK
        @else
          Failed
        @endif
      </x-filament::badge>
    </div>
  </x-slot>

  <x-filament-tables::table>
    @foreach($extensions as $ext)
      <x-filament-tables::row>

        <x-filament-tables::cell>
          <div class="fi-ta-col-wrp">
            <div class="flex w-full disabled:pointer-events-none justify-start text-start">
              <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                  {{ $ext['ext'] }}
                </div>
              </div>
            </div>
          </div>
        </x-filament-tables::cell>

        <x-filament-tables::cell>
          <div class="fi-ta-col-wrp">
            <div class="flex w-full disabled:pointer-events-none justify-end text-start">
              <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                  <x-filament::badge :color="$ext['passed'] === true ? 'success' : 'danger'">
                    @if($ext['passed'] === true)
                      OK
                    @else
                      Failed
                    @endif
                  </x-filament::badge>
                </div>
              </div>
            </div>
          </div>
        </x-filament-tables::cell>

      </x-filament-tables::row>
    @endforeach
  </x-filament-tables::table>
</x-filament::section>

<x-filament::section class="requirements-section">
  <x-slot name="heading">
    <div class="flex justify-between items-center">
      <div>
        Directory Permissions
      </div>

      <x-filament::badge :color="$directoriesPassed ? 'success' : 'danger'">
        @if($directoriesPassed)
          OK
        @else
          Failed
        @endif
      </x-filament::badge>
    </div>
  </x-slot>

  <x-slot name="description">
    Make sure these directories have read and write permissions.
  </x-slot>

  <x-filament-tables::table>
    @foreach($directories as $dir)
      <x-filament-tables::row>

        <x-filament-tables::cell>
          <div class="fi-ta-col-wrp">
            <div class="flex w-full disabled:pointer-events-none justify-start text-start">
              <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                  {{ $dir['dir'] }}
                </div>
              </div>
            </div>
          </div>
        </x-filament-tables::cell>

        <x-filament-tables::cell>
          <div class="fi-ta-col-wrp">
            <div class="flex w-full disabled:pointer-events-none justify-end text-start">
              <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                  <x-filament::badge :color="$dir['passed'] === true ? 'success' : 'danger'">
                    @if($dir['passed'] === true)
                      OK
                    @else
                      Failed
                    @endif
                  </x-filament::badge>
                </div>
              </div>
            </div>
          </div>
        </x-filament-tables::cell>

      </x-filament-tables::row>
    @endforeach
  </x-filament-tables::table>
</x-filament::section>
