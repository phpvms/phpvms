@php
    $locked = $this->readOnly;
    $customFields = collect($this->customFields);
    $fares = $this->fares;
@endphp

<form x-on:submit.prevent class="grid gap-4" aria-label="{{ $this->isEditing ? __('pireps.edit_flight_report') : __('pireps.new_flight_report') }}">
    @if ($locked)
        <x-ts-alert color="primary" light shadowless role="status" data-role="readonly-notice">{{ __('pireps.fields_readonly') }}</x-ts-alert>
    @endif

    @error('filing')
        <x-ts-alert color="red" light shadowless role="alert" data-role="filing-error">{{ $message }}</x-ts-alert>
    @enderror

    <div class="grid items-start gap-4 {{ $customFields->isNotEmpty() ? 'lg:grid-cols-[minmax(0,1fr)_20rem]' : '' }}">
        <div class="grid gap-4">
            <x-ts-card :header="__('pireps.flight_information')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-vms::pireps.form-field :label="__('common.airline')" for="pirep_airline" error="form.airline_id">
                        <select id="pirep_airline" wire:model="form.airline_id" @disabled($locked)>
                            @foreach ($this->airlineOptions as $airlineId => $airlineName)
                                <option value="{{ $airlineId }}" wire:key="airline-{{ $airlineId }}">{{ $airlineName }}</option>
                            @endforeach
                        </select>
                    </x-vms::pireps.form-field>

                    <div class="grid content-start gap-1.5 sm:col-span-1">
                        <label for="pirep_flight_number" class="text-[13px] font-medium">{{ __('pireps.flight_ident') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input type="text" id="pirep_flight_number" inputmode="numeric" placeholder="{{ __('flights.flightnumber') }}" wire:model="form.flight_number" @disabled($locked) />
                            <input type="text" placeholder="{{ __('pireps.code_optional') }}" aria-label="{{ __('pireps.code_optional') }}" wire:model="form.route_code" @disabled($locked) />
                            <input type="text" placeholder="{{ __('pireps.leg_optional') }}" aria-label="{{ __('pireps.leg_optional') }}" wire:model="form.route_leg" @disabled($locked) />
                        </div>
                        @foreach (['flight_number', 'route_code', 'route_leg'] as $identField)
                            @error('form.'.$identField)
                                <p class="text-xs" role="alert">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>

                    <x-vms::pireps.form-field :label="__('flights.flighttype')" for="pirep_flight_type" error="form.flight_type">
                        <select id="pirep_flight_type" wire:model="form.flight_type" @disabled($locked)>
                            @foreach (\App\Enums\FlightType::cases() as $flightType)
                                <option value="{{ $flightType->value }}" wire:key="flight-type-{{ $flightType->value }}">{{ $flightType->value }} ({{ $flightType->getLabel() }})</option>
                            @endforeach
                        </select>
                    </x-vms::pireps.form-field>

                    <div class="grid content-start gap-1.5 sm:col-span-2">
                        <label for="pirep_hours" class="text-[13px] font-medium">{{ __('flights.flight_time') }}</label>
                        <div class="grid max-w-sm grid-cols-2 gap-2">
                            <input type="number" id="pirep_hours" min="0" placeholder="{{ trans_choice('common.hour', 2) }}" aria-label="{{ trans_choice('common.hour', 2) }}" wire:model="form.hours" @disabled($locked) />
                            <input type="number" id="pirep_minutes" min="0" placeholder="{{ trans_choice('common.minute', 2) }}" aria-label="{{ trans_choice('common.minute', 2) }}" wire:model="form.minutes" @disabled($locked) />
                        </div>
                        @foreach (['hours', 'minutes'] as $timeField)
                            @error('form.'.$timeField)
                                <p class="text-xs" role="alert">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>

                    <x-vms::pireps.form-field :label="__('flights.level').' ('.config('phpvms.internal_units.altitude').')'" for="pirep_level" error="form.level">
                        <input type="number" id="pirep_level" min="0" step="0.01" wire:model="form.level" @disabled($locked) />
                    </x-vms::pireps.form-field>
                </div>
            </x-ts-card>

            <x-ts-card :header="__('pireps.deparr_information')">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['dpt' => ['airports.departure', 'dpt_airport_id', $dptLabel, $this->dptResults], 'arr' => ['airports.arrival', 'arr_airport_id', $arrLabel, $this->arrResults]] as $side => [$sideLabel, $sideProperty, $selectedLabel, $results])
                        <x-vms::pireps.form-field :label="__($sideLabel)" :for="'pirep_'.$side.'_search'" :error="'form.'.$sideProperty" wire:key="airport-{{ $side }}">
                            <div class="relative" data-role="{{ $side }}-airport">
                                <div class="rounded-md px-3 py-1.5 text-[13px]" data-role="{{ $side }}-selected">
                                    {{ $selectedLabel === '' ? 'No airport selected' : $selectedLabel }}
                                </div>

                                @unless ($this->routeLocked)
                                    <input type="search" id="pirep_{{ $side }}_search" class="mt-1.5" autocomplete="off" placeholder="Search by ICAO or name" wire:model.live.debounce.250ms="{{ $side }}Search" />

                                    @if (mb_strlen(trim($side === 'dpt' ? $dptSearch : $arrSearch)) >= 2)
                                        <ul class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md text-[13px]" data-role="{{ $side }}-results">
                                            @forelse ($results as $airport)
                                                <li wire:key="{{ $side }}-airport-{{ $airport['value'] }}">
                                                    <button type="button" class="block w-full px-3 py-1.5 text-left" wire:click="chooseAirport('{{ $side }}', '{{ $airport['value'] }}')">{{ $airport['label'] }}</button>
                                                </li>
                                            @empty
                                                <li class="px-3 py-1.5">No airports found</li>
                                            @endforelse
                                        </ul>
                                    @endif
                                @endunless
                            </div>
                        </x-vms::pireps.form-field>
                    @endforeach
                </div>
            </x-ts-card>

            <x-ts-card :header="__('pireps.aircraft_information')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-vms::pireps.form-field :label="__('common.aircraft')" for="pirep_aircraft" error="form.aircraft_id">
                        <select id="pirep_aircraft" wire:model.live="form.aircraft_id" @disabled($locked)>
                            @foreach ($this->aircraftOptions as $group => $items)
                                @if (is_array($items))
                                    <optgroup label="{{ $group }}">
                                        @foreach ($items as $aircraftId => $aircraftLabel)
                                            <option value="{{ $aircraftId }}" wire:key="aircraft-{{ $aircraftId }}">{{ $aircraftLabel }}</option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    <option value="">{{ $items }}</option>
                                @endif
                            @endforeach
                        </select>
                    </x-vms::pireps.form-field>

                    <x-vms::pireps.form-field :label="__('pireps.block_fuel').' ('.setting('units.fuel').')'" for="pirep_block_fuel" error="form.block_fuel">
                        <input type="number" id="pirep_block_fuel" min="0" step="0.01" wire:model="form.block_fuel" @disabled($locked) />
                    </x-vms::pireps.form-field>

                    <x-vms::pireps.form-field :label="__('pireps.fuel_used').' ('.setting('units.fuel').')'" for="pirep_fuel_used" error="form.fuel_used">
                        <input type="number" id="pirep_fuel_used" min="0" step="0.01" wire:model="form.fuel_used" @disabled($locked) />
                    </x-vms::pireps.form-field>
                </div>
            </x-ts-card>

            @if ($fares->isNotEmpty())
                <x-ts-card :header="trans_choice('pireps.fare', 2)" data-role="fares">
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($fares as $fare)
                            <x-vms::pireps.form-field :label="$fare->name.' ('.$fare->type->getLabel().', code '.$fare->code.')'" :for="'pirep_fare_'.$fare->id" :error="'form.fares.'.$fare->id" wire:key="fare-{{ $fare->id }}">
                                <input type="number" id="pirep_fare_{{ $fare->id }}" min="0" wire:model="form.fares.{{ $fare->id }}" />
                            </x-vms::pireps.form-field>
                        @endforeach
                    </div>
                </x-ts-card>
            @endif

            <x-ts-card :header="__('flights.route').' & '.trans_choice('common.remark', 2)">
                <div class="grid gap-4">
                    <x-vms::pireps.form-field :label="__('flights.route')" for="pirep_route" error="form.route">
                        <textarea id="pirep_route" rows="3" wire:model="form.route"></textarea>
                    </x-vms::pireps.form-field>

                    <x-vms::pireps.form-field :label="trans_choice('common.note', 2)" for="pirep_notes" error="form.notes">
                        <textarea id="pirep_notes" rows="3" wire:model="form.notes"></textarea>
                    </x-vms::pireps.form-field>
                </div>
            </x-ts-card>
        </div>

        @if ($customFields->isNotEmpty())
            <x-ts-card :header="trans_choice('common.field', 2)" data-role="custom-fields">
                <div class="grid gap-4">
                    @foreach ($customFields as $field)
                        <x-vms::pireps.form-field :label="$field['name']" :for="'pirep_field_'.$field['slug']" :error="'form.fields.'.$field['slug']" :required="$field['required']" :hint="$field['description']" wire:key="field-{{ $field['slug'] }}">
                            @if ($field['readOnly'])
                                <input type="text" id="pirep_field_{{ $field['slug'] }}" value="{{ $field['value'] }}" readonly />
                            @else
                                <input type="text" id="pirep_field_{{ $field['slug'] }}" wire:model="form.fields.{{ $field['slug'] }}" />
                            @endif
                        </x-vms::pireps.form-field>
                    @endforeach
                </div>
            </x-ts-card>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2">
        @if ($this->isEditing && ! $locked)
            <x-ts-button outline color="amber" type="button" data-prompt="{{ __('Are you sure you want to delete this PIREP?') }}" data-confirm="{{ __('pireps.delete_pirep') }}" data-keep="Keep" x-on:click="$tsui.interaction('dialog').wireable($wire.$id).question($escapeHtml($el.dataset.prompt)).confirm($el.dataset.confirm, 'delete').cancel($el.dataset.keep).send()">{{ __('pireps.delete_pirep') }}</x-ts-button>
        @endif

        @unless ($locked)
            <x-ts-button light type="button" wire:click="save" wire:loading.attr="disabled">{{ __('pireps.save_pirep') }}</x-ts-button>
            <x-ts-button type="button" wire:click="submit" wire:loading.attr="disabled">{{ __('pireps.submit_pirep') }}</x-ts-button>
        @endunless
    </div>
</form>
