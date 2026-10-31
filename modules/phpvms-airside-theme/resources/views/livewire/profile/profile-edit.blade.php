<div>
    <x-airside::card title="Edit profile" description="Update your details. Leave the password fields blank to keep your current password." aria-label="Edit profile">
        <form wire:submit="save" class="grid gap-4 sm:grid-cols-2" enctype="multipart/form-data">
            <x-airside::profile-field label="Name" for="profile_name" error="form.name" required>
                <input type="text" id="profile_name" autocomplete="name" wire:model="form.name" />
            </x-airside::profile-field>

            <x-airside::profile-field label="Email" for="profile_email" error="form.email" required>
                <input type="email" id="profile_email" autocomplete="email" wire:model="form.email" />
            </x-airside::profile-field>

            <x-airside::profile-field label="Airline" for="profile_airline" error="form.airline_id" required>
                <select id="profile_airline" wire:model="form.airline_id">
                    @foreach ($this->edit->airlines as $airline)
                        <option value="{{ $airline->value }}" wire:key="airline-{{ $airline->value }}">{{ $airline->label }}</option>
                    @endforeach
                </select>
            </x-airside::profile-field>

            <x-airside::profile-field label="Home airport" for="profile_home_airport" error="form.home_airport_id" :hint="$this->edit->hubsOnly ? 'Hubs only' : null">
                <div class="relative">
                    <div class="flex items-center justify-between gap-2 rounded-md px-3 py-1.5 text-[13px]" data-role="home-airport-selected">
                        <span>{{ $airportLabel === '' ? 'No home airport selected' : $airportLabel }}</span>
                        @if ($form->home_airport_id !== null)
                            <button type="button" class="text-xs" wire:click="clearAirport">Clear</button>
                        @endif
                    </div>

                    <input type="search" id="profile_home_airport" class="mt-1.5" autocomplete="off" placeholder="Search by ICAO or name" wire:model.live.debounce.250ms="airportSearch" />

                    @if (mb_strlen(trim($airportSearch)) >= 2)
                        <ul class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md text-[13px]" data-role="airport-results">
                            @forelse ($this->airportResults as $airport)
                                <li wire:key="airport-{{ $airport['value'] }}">
                                    <button type="button" class="block w-full px-3 py-1.5 text-left" wire:click="chooseAirport('{{ $airport['value'] }}')">{{ $airport['label'] }}</button>
                                </li>
                            @empty
                                <li class="px-3 py-1.5">No airports found</li>
                            @endforelse
                        </ul>
                    @endif
                </div>
            </x-airside::profile-field>

            <x-airside::profile-field label="Country" for="profile_country" error="form.country">
                <select id="profile_country" wire:model="form.country">
                    @foreach ($this->edit->countries as $country)
                        <option value="{{ $country->value }}" wire:key="country-{{ $country->value }}">{{ $country->label }}</option>
                    @endforeach
                </select>
            </x-airside::profile-field>

            <x-airside::profile-field label="Timezone" for="profile_timezone" error="form.timezone">
                <select id="profile_timezone" wire:model="form.timezone">
                    @foreach ($this->edit->timezones as $timezone)
                        <option value="{{ $timezone->value }}" wire:key="timezone-{{ $timezone->value }}">{{ $timezone->label }}</option>
                    @endforeach
                </select>
            </x-airside::profile-field>

            <x-airside::profile-field label="SimBrief username" for="profile_simbrief" error="form.simbrief_username">
                <input type="text" id="profile_simbrief" wire:model="form.simbrief_username" />
            </x-airside::profile-field>

            <x-airside::profile-field label="Avatar" for="profile_avatar" error="form.avatar" :hint="'JPEG or PNG. Resized to '.$this->edit->avatarWidth.'x'.$this->edit->avatarHeight.'.'">
                <input type="file" id="profile_avatar" accept="image/jpeg,image/png" wire:model="form.avatar" />
                <p class="text-xs" wire:loading wire:target="form.avatar">Uploading…</p>
            </x-airside::profile-field>

            <div class="col-span-full">
                <label class="flex items-start gap-2">
                    <input type="checkbox" class="mt-0.5" wire:model="form.opt_in" />
                    <span class="grid gap-0.5">
                        <span class="text-[13px] font-medium">Opt in to emails</span>
                        <span class="text-xs">Receive news and announcements from the airline.</span>
                    </span>
                </label>
                @error('form.opt_in')
                    <p class="text-xs" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if (count($this->edit->fields))
                <p class="col-span-full pt-3">Additional details</p>

                @foreach ($this->edit->fields as $field)
                    <x-airside::profile-field :label="$field->name" :for="'profile_field_'.$field->slug" :error="'form.fields.field_'.$field->slug" :required="$field->required" wire:key="field-{{ $field->slug }}">
                        <input type="text" id="profile_field_{{ $field->slug }}" wire:model="form.fields.field_{{ $field->slug }}" />
                    </x-airside::profile-field>
                @endforeach
            @endif

            <p class="col-span-full pt-3">Change password</p>

            <x-airside::profile-field label="New password" for="profile_password" error="form.password">
                <input type="password" id="profile_password" autocomplete="new-password" wire:model="form.password" />
            </x-airside::profile-field>

            <x-airside::profile-field label="Confirm password" for="profile_password_confirmation" error="form.password_confirmation">
                <input type="password" id="profile_password_confirmation" autocomplete="new-password" wire:model="form.password_confirmation" />
            </x-airside::profile-field>

            <div class="col-span-full flex justify-end">
                <x-ts-button submit wire:loading.attr="disabled" wire:target="save">Save changes</x-ts-button>
            </div>
        </form>
    </x-airside::card>
</div>
