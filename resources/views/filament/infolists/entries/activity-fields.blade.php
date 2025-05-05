@if (isset($getRecord()->changes['attributes']) && is_array($getRecord()->changes['attributes']))
  <x-filament-tables::container>
    <x-filament-tables::table>
      <x-slot name="header">
        <x-filament-tables::header-cell>Field</x-filament-tables::header-cell>
        <x-filament-tables::header-cell>New Value</x-filament-tables::header-cell>
        <x-filament-tables::header-cell>Old Value</x-filament-tables::header-cell>
      </x-slot>

      {{-- Check if 'attributes' key exists --}}
      @foreach($getRecord()->changes['attributes'] as $field => $newValue)
        @if(!is_array($newValue))
          <x-filament-tables::row>
            <x-filament-tables::cell>
              <div class="fi-ta-col-wrp">
                <div class="flex w-full disabled:pointer-events-none justify-start text-start">
                  <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                    <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                      {{ $field }}
                    </div>
                  </div>
                </div>
              </div>
            </x-filament-tables::cell>

            <x-filament-tables::cell>
              <div class="fi-ta-col-wrp">
                <div class="flex w-full disabled:pointer-events-none justify-start text-start">
                  <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                    <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                      {{ $newValue }}
                    </div>
                  </div>
                </div>
              </div>
            </x-filament-tables::cell>

            <x-filament-tables::cell>
              <div class="fi-ta-col-wrp">
                <div class="flex w-full disabled:pointer-events-none justify-start text-start">
                  <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                    <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                      {{-- Check if 'old' key exists --}}
                      {{ $getRecord()->changes['old'][$field] ?? 'N/A' }}
                    </div>
                  </div>
                </div>
              </div>
            </x-filament-tables::cell>
          </x-filament-tables::row>
        @else
          @foreach($newValue as $subField => $newSubFieldValue)
            <x-filament-tables::row>
              <x-filament-tables::cell>
                <div class="fi-ta-col-wrp">
                  <div class="flex w-full disabled:pointer-events-none justify-start text-start">
                    <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                      <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                        {{ $field.'.'.$subField }}
                      </div>
                    </div>
                  </div>
                </div>
              </x-filament-tables::cell>

              <x-filament-tables::cell>
                <div class="fi-ta-col-wrp">
                  <div class="flex w-full disabled:pointer-events-none justify-start text-start">
                    <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                      <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                        {{ $newSubFieldValue }}
                      </div>
                    </div>
                  </div>
                </div>
              </x-filament-tables::cell>

              <x-filament-tables::cell>
                <div class="fi-ta-col-wrp">
                  <div class="flex w-full disabled:pointer-events-none justify-start text-start">
                    <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                      <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                        {{-- Check if 'old' key exists --}}
                        {{ $getRecord()->changes['old'][$field][$subField] ?? 'N/A'  }}
                      </div>
                    </div>
                  </div>
                </div>
              </x-filament-tables::cell>
            </x-filament-tables::row>
          @endforeach
        @endif
      @endforeach

    </x-filament-tables::table>
  </x-filament-tables::container>
@endif
