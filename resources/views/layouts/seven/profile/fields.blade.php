<div class="card">
    <div class="card-body p-4">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="name" class="form-label">{{ __('common.name') }}</label>
                <input type="text" name="name" id="name"
                    class="form-control {{ $errors->has('name') ? ' is-invalid' : ' ' }}" value="{{ $user->name }}" />
                @if ($errors->has('name'))
                    <div id="nameFeedback" class="invalid-feedback">{{ $errors->first('name') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">{{ __('common.email') }}</label>
                <input type="email" name="email" id="email"
                    class="form-control {{ $errors->has('email') ? ' is-invalid' : ' ' }}"
                    value="{{ $user->email }}" />
                @if ($errors->has('email'))
                    <div id="emailFeedback" class="invalid-feedback">{{ $errors->first('email') }}</div>
                @endif
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="airline_id" class="form-label">{{ __('common.airline') }}</label>
                <select id="airline_id" name="airline_id" placeholder="{{ __('common.airline') }}" autocomplete="off">
                    @foreach ($airlines as $airline_id => $airline_label)
                        <option value="{{ $airline_id }}" @if ($user->airline_id === $airline_id) selected @endif>
                            {{ $airline_label }}</option>
                    @endforeach
                </select>
                @if ($errors->has('airline_id'))
                    <div id="airlineIdFeedback" class="invalid-feedback">{{ $errors->first('airline_id') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label for="home_airport_id" class="form-label">{{ __('airports.home') }}</label>
                <select id="home_airport_id" name="home_airport_id" placeholder="{{ __('airports.home') }}"
                    autocomplete="off">
                    @foreach ($airports as $airport_id => $airport_label)
                        <option value="{{ $airport_id }}">{{ $airport_label }}</option>
                    @endforeach
                </select>
                @if ($errors->has('home_airport_id'))
                    <div id="homeAirportIdFeedback" class="invalid-feedback">{{ $errors->first('home_airport_id') }}
                    </div>
                @endif
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="country" class="form-label">{{ __('common.country') }}</label>
                <select id="country" name="country" placeholder="{{ __('common.country') }}" autocomplete="off">
                    @foreach ($countries as $country_id => $country_label)
                        <option value="{{ $country_id }}" @if ($user->country === $country_id) selected @endif>
                            {{ $country_label }}</option>
                    @endforeach
                </select>
                @if ($errors->has('country'))
                    <div id="countryFeedback" class="invalid-feedback">{{ $errors->first('country') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label for="timezone" class="form-label">{{ __('common.timezone') }}</label>
                <select id="timezone" name="timezone" placeholder="{{ __('common.timezone') }}" autocomplete="off">
                    @foreach ($timezones as $group_name => $group_timezones)
                        <optgroup label="{{ $group_name }}">
                            @foreach ($group_timezones as $timezone_id => $timezone_label)
                                <option value="{{ $timezone_id }}" @if ($timezone_id === $user->timezone) selected @endif>
                                    {!! $timezone_label !!}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @if ($errors->has('timezone'))
                    <div id="homeAirportIdFeedback" class="invalid-feedback">{{ $errors->first('timezone') }}</div>
                @endif
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <label for="avatar" class="form-label">{{ __('profile.avatar') }}</label>
                <input type="file" name="avatar" id="avatar"
                    class="form-control {{ $errors->has('avatar') ? ' is-invalid' : ' ' }}" />
                <p class="small text-muted">
                    {{ __('profile.avatarresize', [
                        'width' => config('phpvms.avatar.width'),
                        'height' => config('phpvms.avatar.height'),
                    ]) }}
                </p>
                @if ($errors->has('avatar'))
                    <div id="avatarFeedback" class="invalid-feedback">{{ $errors->first('avatar') }}</div>
                @endif
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="form-check {{ $errors->has('opt_in') ? ' is-invalid' : ' ' }}">
                    <input class="form-check-input" type="checkbox" checked="{{ $user->opt_in }}" name="opt_in"
                        id="opt_in">
                    <label class="form-check-label" for="opt_in">
                        {{ __('profile.opt-in') }}
                    </label>
                </div>
                <p class="small text-muted">
                    {{ __('profile.opt-in-descrip') }}
                </p>
                @if ($errors->has('opt_in'))
                    <div id="opt_inFeedback" class="invalid-feedback">{{ $errors->first('opt_in') }}</div>
                @endif
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="vatsim_id" class="form-label">VATSIM ID</label>
                <input type="text" name="vatsim_id" id="vatsim_id"
                    class="form-control {{ $errors->has('vatsim_id') ? ' is-invalid' : ' ' }}"
                    value="{{ $user->vatsim_id }}" />
                @if ($errors->has('vatsim_id'))
                    <div id="vatsim_idFeedback" class="invalid-feedback">{{ $errors->first('vatsim_id') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label for="ivao_id" class="form-label">IVAO ID</label>
                <input type="text" name="ivao_id" id="ivao_id"
                    class="form-control {{ $errors->has('ivao_id') ? ' is-invalid' : ' ' }}"
                    value="{{ $user->ivao_id }}" />
                @if ($errors->has('ivao_id'))
                    <div id="ivao_idFeedback" class="invalid-feedback">{{ $errors->first('ivao_id') }}</div>
                @endif
            </div>
        </div>
        {{-- Custom fields --}}
        <div class="row mb-3">
            @foreach ($userFields as $field)
                <div class="col-md-{{ $userFields->count() > 1 ? 6 : 12 }}">
                    <label for="field_{{ $field->slug }}" class="form-label">{{ $field->name }}
                        @if ($field->required === true)
                            <span class="text-danger">*</span>
                        @endif
                    </label>
                    @if ($field->type === 'select')
                        <select id="field_{{ $field->slug }}" name="field_{{ $field->slug }}" class="form-select"
                            autocomplete="off">
                            @foreach ($field->options as $option_id => $option_label)
                                <option value="{{ $option_id }}"
                                    @if ($field->value === $option_id) selected @endif>
                                    {{ $option_label }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="field_{{ $field->slug }}" id="field_{{ $field->slug }}"
                            class="form-control" value="{{ $field->value }}" />
                    @endif
                    @if ($errors->has('field_' . $field->slug))
                        <div id="field_{{ $field->slug }}Feedback" class="invalid-feedback">
                            {{ $errors->first('field_' . $field->slug) }}</div>
                    @endif
                </div>
                @if ($loop->iteration % 2 == 0 && !$loop->last)
        </div>
        <div class="row mb-3">
            @endif
            @endforeach
        </div>
        <div class="row mb-3">
            <h5>{{ __('profile.changepassword') }}</h5>
            <div class="col-md-6">
                <label for="password" class="form-label">{{ __('profile.newpassword') }}</label>
                <input type="password" name="password" id="password"
                    class="form-control {{ $errors->has('password') ? ' is-invalid' : ' ' }}" />
                @if ($errors->has('password'))
                    <div id="passwordFeedback" class="invalid-feedback">{{ $errors->first('password') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">{{ __('passwords.confirm') }}</label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                    class="form-control {{ $errors->has('password_confirmation') ? ' is-invalid' : ' ' }}" />
                @if ($errors->has('password_confirmation'))
                    <div id="passwordConfirmationFeedback" class="invalid-feedback">
                        {{ $errors->first('password_confirmation') }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="card-footer d-grid">
        <button type="submit" class="btn btn-primary">
            @lang('profile.updateprofile')
        </button>
    </div>
</div>
