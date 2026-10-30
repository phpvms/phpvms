@extends('auth.login_layout')
@section('title', __('auth.register'))
@section('card_width', 'max-w-xl')

@section('content')
    <x-airside::auth-card :title="__('common.register')">
        <form method="post" action="{{ url('/register') }}" class="grid gap-4" x-data="{ accepted: false }">
            @csrf

            @include('auth.oauth-connections', ['surface' => 'registration'])

            @if ($oauthRegistration)
                <p class="text-[13px] text-ink-dim">{{ __('auth.oauth_complete_registration', ['provider' => $oauthProviderName]) }}</p>

                <div class="grid gap-1">
                    <div class="text-[13px] font-medium">@lang('auth.full_name')</div>
                    <p>{{ old('name') }}</p>
                </div>

                <div class="grid gap-1">
                    <div class="text-[13px] font-medium">@lang('auth.email_address')</div>
                    <p>{{ old('email') }}</p>
                </div>
            @else
                <x-airside::field name="name" :label="__('auth.full_name')" :value="old('name')" autocomplete="name" />
                <x-airside::field name="email" type="email" :label="__('auth.email_address')" :value="old('email')" autocomplete="email" />
            @endif

            <div class="grid gap-1.5">
                <label for="airline_id" class="text-[13px] font-medium">@lang('common.airline')</label>
                <select name="airline_id" id="airline_id">
                    @foreach ($airlines as $airline_id => $airline_label)
                        <option value="{{ $airline_id }}" @selected((string) $airline_id === (string) old('airline_id'))>{{ $airline_label }}</option>
                    @endforeach
                </select>
                @error('airline_id')
                    <p class="text-xs text-bad" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- Home airport: type to search; the chosen airport's id is what the form posts. --}}
            <div class="grid gap-1.5" x-data="airsideAirportSearch(@js((bool) $hubs_only))" @click.outside="results = []">
                <label for="home_airport_search" class="text-[13px] font-medium">@lang('airports.home')</label>
                <input type="hidden" name="home_airport_id" :value="selected" value="{{ old('home_airport_id') }}" />
                <div class="relative">
                    <input type="search" id="home_airport_search" autocomplete="off" x-model="query" @input.debounce.250ms="search()" :placeholder="selectedLabel" />
                    <ul x-show="results.length" x-cloak class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-line bg-panel text-[13px]">
                        <template x-for="airport in results" :key="airport.id">
                            <li><button type="button" class="block w-full px-3 py-1.5 text-left hover:bg-hover" x-text="airport.description" @click="choose(airport)"></button></li>
                        </template>
                    </ul>
                </div>
                @error('home_airport_id')
                    <p class="text-xs text-bad" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-1.5">
                <label for="country" class="text-[13px] font-medium">@lang('common.country')</label>
                <select name="country" id="country">
                    @foreach ($countries as $country_id => $country_label)
                        <option value="{{ $country_id }}" @selected((string) $country_id === (string) old('country'))>{{ $country_label }}</option>
                    @endforeach
                </select>
                @error('country')
                    <p class="text-xs text-bad" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-1.5">
                <label for="timezone" class="text-[13px] font-medium">@lang('common.timezone')</label>
                <select name="timezone" id="timezone">
                    @foreach ($timezones as $group_name => $group_timezones)
                        <optgroup label="{{ $group_name }}">
                            @foreach ($group_timezones as $timezone_id => $timezone_label)
                                <option value="{{ $timezone_id }}" @selected((string) $timezone_id === (string) old('timezone'))>{{ $timezone_label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('timezone')
                    <p class="text-xs text-bad" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if (setting('pilots.allow_transfer_hours') === true)
                <x-airside::field name="transfer_time" type="number" :label="__('auth.transfer_hours')" :value="old('transfer_time')" />
            @endif

            @unless ($oauthRegistration)
                <x-airside::field name="password" type="password" :label="__('auth.password')" autocomplete="new-password" />
                <x-airside::field name="password_confirmation" type="password" :label="__('passwords.confirm')" autocomplete="new-password" />
            @endunless

            @foreach ($userFields ?? [] as $field)
                <x-airside::field :name="'field_'.$field->slug" :label="$field->name" :value="old('field_'.$field->slug)" />
            @endforeach

            @if ($captcha['enabled'] === true)
                <div class="grid gap-1.5">
                    <span class="text-[13px] font-medium">@lang('auth.fill_captcha')</span>
                    <div class="h-captcha" data-sitekey="{{ $captcha['site_key'] }}"></div>
                    @error('h-captcha-response')
                        <p class="text-xs text-bad" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if ($invite)
                <input type="hidden" name="invite" value="{{ $invite->id }}" />
                <input type="hidden" name="invite_token" value="{{ base64_encode($invite->token) }}" />
            @endif

            @if ($oauthRegistration)
                <input type="hidden" name="oauth_registration" value="{{ $oauthRegistration }}" />
            @endif

            @include('auth.toc')

            <label class="flex items-start gap-2 text-[13px]">
                <input type="checkbox" name="toc_accepted" id="toc_accepted" class="mt-0.5" x-model="accepted" />
                <span>@lang('auth.toc_accept')</span>
            </label>
            @error('toc_accepted')
                <p class="text-xs text-bad" role="alert">{{ $message }}</p>
            @enderror

            <label class="flex items-start gap-2 text-[13px]">
                <input type="hidden" name="opt_in" value="0" />
                <input type="checkbox" name="opt_in" value="1" class="mt-0.5" />
                <span>@lang('profile.opt-in-descrip')</span>
            </label>

            <x-ts-button submit block id="register_button" x-bind:disabled="! accepted" disabled>{{ __('auth.register') }}</x-ts-button>
        </form>
    </x-airside::auth-card>
@endsection

@section('scripts')
    @if ($captcha['enabled'])
        <script src="https://hcaptcha.com/1/api.js" async defer></script>
    @endif
@endsection
