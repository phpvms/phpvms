<x-filament-tables::container>
  <x-filament-tables::table>
    <x-slot name="header">
      <x-filament-tables::header-cell>Expense</x-filament-tables::header-cell>
      <x-filament-tables::header-cell>Credit</x-filament-tables::header-cell>
      <x-filament-tables::header-cell>Debit</x-filament-tables::header-cell>
    </x-slot>

    @foreach($transactions as $ta)
      <x-filament-tables::row>
        <x-filament-tables::cell>
          <div class="fi-ta-col-wrp">
            <div class="flex w-full disabled:pointer-events-none justify-start text-start">
              <div class="fi-ta-text grid gap-y-1 px-3 py-4">
                <div class="fi-ta-text-item inline-flex items-center gap-1.5 text-sm text-gray-950 dark:text-white">
                  {{ $ta->transaction_group }}
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
                  {{ money($ta->sum_credits ?? 0, $ta->currency) }}
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
                  {{ money($ta->sum_debits ?? 0, $ta->currency) }}
                </div>
              </div>
            </div>
          </div>
        </x-filament-tables::cell>
      </x-filament-tables::row>
    @endforeach

    <x-slot name="footer">
      <x-filament-tables::header-cell>Total</x-filament-tables::header-cell>
      <x-filament-tables::header-cell>{{ money($sum_all_credits, setting('units.currency')) }}</x-filament-tables::header-cell>
      <x-filament-tables::header-cell>{{ money($sum_all_debits, setting('units.currency')) }}</x-filament-tables::header-cell>
    </x-slot>
  </x-filament-tables::table>
</x-filament-tables::container>
