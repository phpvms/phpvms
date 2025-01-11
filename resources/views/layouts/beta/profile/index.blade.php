@extends('app')
@section('title', __('common.profile'))

@section('content')
<div class="row profile-page content-center text-color-dark-beige">
  <div class="col-md-4" style="text-align:center;">
    <div class="photo-container">
      @if ($user->avatar == null)
      <img src="{{ $user->gravatar(512) }}" style="width: 123px;">
      @else
      <img src="{{ $user->avatar->url }}" style="width: 123px;">
      @endif
    </div>
    <div><br/></div>
    <div class="social-description">
      <h2>{{ $user->name_private }}</h2>
      <p>
        {{ $user->ident }}
        @if(filled($user->callsign))
        {{ ' | '.$user->callsign }}&nbsp;
        @endif
        <span class="flag-icon flag-icon-{{ $user->country }}"></span>
      </p>
    </div>
    <p class="description" style="color: #9A9A9A;">
      {{ $user->airline->name }}
    </p>
    <div class="social-description">
      @if (!empty($user->rank->image_url))
      <img src="{{ $user->rank->image_url }}" style="width: 160px;">
      @endif
      <p>{{ $user->rank->name }} <br/>
        @if($user->home_airport)
        @lang('airports.home'): {{ $user->home_airport->icao }}
        @endif
      </p>
    </div>
  </div>
  <div class="col-md-8  content-center">
    <div class="content">
      <div class="row">
        <div class="col-lg-6">
          <div class="card text-center">
            <div class="card-body">
              <h2 class="card-title">{{ $user->flights}}</h2>
              <p class="card-text">Flights</p>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card text-center">
            <div class="card-body">
              <div class="social-description">
                <h2 class="card-title">@minutestotime($user->flight_time)</h2>
                <p class="card-text">@lang('flights.flighthours')</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        @if($user->current_airport)
        <div class="col-lg-6">
          <div class="card text-center">
            <div class="card-body">
              <div class="social-description">
                <h2 class="card-title">{{ $user->current_airport->icao }}</h2>
                <p class="card-text">@lang('airports.current')</p>
              </div>
            </div>
          </div>
        </div>
        @endif


        @if(setting('pilots.allow_transfer_hours') === true)
        <div class="col-lg-6">
          <div class="card text-center">
            <div class="card-body">
              <div class="social-description">
                <h2 class="card-title">@minutestohours($user->transfer_time)h</h2>
                <p class="card-text">@lang('profile.transferhours')</p>
              </div>
            </div>
          </div>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Show the user's award if they have any --}}
@if ($user->awards)
<div class="clearfix" style="height: 50px;"></div>
<div class="row">
  <div class="col-sm-12">
    <h3 class="description">@lang('profile.your-awards')</h3>
    @foreach($user->awards->chunk(3) as $awards)
    <div class="row">
      @foreach($awards as $award)
      <div class="card card-signup">
        <div class="header header-primary text-center">
          <h4 class="title title-up">{{ $award->name }}</h4>
          @if ($award->image_url)
          <div class="photo-container">
            <img src="{{ $award->image_url }}" alt="{{ $award->description }}"
                 style="width: 123px;">
          </div>
          @endif
        </div>
        <div class="content content-center">
          <div class="social-description text-center">
            {{ $award->description }}
          </div>
        </div>
        <div class="footer text-center">
        </div>
      </div>
      @endforeach
    </div>
    <div class="clearfix" style="height: 20px;"></div>
    @endforeach
  </div>
</div>

@endif

{{--
show the details/edit fields only for the currently logged in user
--}}
@if(Auth::check() && $user->id === Auth::user()->id)
<div class="clearfix" style="height: 50px;"></div>
<div class="row">
  <div class="col-sm-12">
    <div class="text-right">
      @if (isset($acars) && $acars === true)
      <a href="{{ route('frontend.profile.acars') }}" class="btn btn-primary"
         onclick="alert('Copy or Save to \'My Documents/vmsacars/profiles\'')">ACARS Config</a>
      &nbsp;
      @endif

      @if(config('services.discord.enabled') && !$user->discord_id)
      <a href="{{ route('oauth.redirect', ['provider' => 'discord']) }}" class="btn"
         style="background-color:#738ADB;">Link Discord Account</a>
      @elseif(config('services.discord.enabled'))
      <a href="{{ route('oauth.logout', ['provider' => 'discord']) }}" class="btn"
         style="background-color:#738ADB;">Unlink Discord Account</a>
      @endif

      @if(config('services.ivao.enabled') && !$user->ivao_id)
      <a href="{{ route('oauth.redirect', ['provider' => 'ivao']) }}" class="btn"
         style="background-color:#0d2c99;">Link IVAO Account</a>
      @elseif(config('services.ivao.enabled'))
      <a href="{{ route('oauth.logout', ['provider' => 'ivao']) }}" class="btn"
         style="background-color:#0d2c99;">Unlink IVAO Account</a>
      @endif

      @if(config('services.vatsim.enabled') && !$user->vatsim_id)
      <a href="{{ route('oauth.redirect', ['provider' => 'vatsim']) }}" class="btn"
         style="background-color:#29B473;">Link VATSIM Account</a>
      @elseif(config('services.vatsim.enabled'))
      <a href="{{ route('oauth.logout', ['provider' => 'vatsim']) }}" class="btn"
         style="background-color:#29B473;">Unlink VATSIM Account</a>
      @endif

      <a href="{{ route('frontend.profile.regen_apikey') }}" class="btn btn-warning"
         onclick="return confirm('Are you sure? This will reset your API key!')">@lang('profile.newapikey')</a>
      &nbsp;
      <a href="{{ route('frontend.profile.edit', [$user->id]) }}"
         class="btn btn-primary">@lang('common.edit')</a>
    </div>

    <h3 class="description">@lang('profile.your-profile')</h3>
    <table class="table table-full-width">
      <tr>
        <td>@lang('common.email')</td>
        <td>{{ $user->email }}</td>
      </tr>
      <tr>
        <td>@lang('profile.apikey')&nbsp;&nbsp;<span class="description">(@lang('profile.dontshare'))</span>
        </td>
        <td><span id="apiKey_show" style="display: none">{{ $user->api_key }} <i
              class="fas fa-eye-slash" onclick="apiKeyHide()"></i></span><span id="apiKey_hide">@lang('profile.apikey-show') <i
              class="fas fa-eye" onclick="apiKeyShow()"></i></span></td>
      </tr>
      <tr>
        <td>Discord ID</td>
        <td>{{ $user->discord_id ?? '-' }}</td>
      </tr>
      <tr>
        <td>@lang('common.timezone')</td>
        <td>{{ $user->timezone }}</td>
      </tr>
      <tr>
        <td>@lang('profile.opt-in')</td>
        <td>{{ $user->opt_in ? __('common.yes') : __('common.no') }}</td>
      </tr>
    </table>
  </div>
</div>
@endif

<div class="clearfix" style="height: 50px;"></div>
<div class="row">
  <div class="col-sm-12">
    <table class="table table-full-width">
      @foreach($userFields as $field)
      @if(!$field->private)
      <tr>
        <td>{{ $field->name }}</td>
        <td>{{ $field->value ?? '-'}}</td>
      </tr>
      @endif
      @endforeach
    </table>
  </div>
</div>
@endsection

@section('scripts')
<script>
  function apiKeyShow() {
    document.getElementById("apiKey_show").style = "display:block";
    document.getElementById("apiKey_hide").style = "display:none";
  }

  function apiKeyHide() {
    document.getElementById("apiKey_show").style = "display:none";
    document.getElementById("apiKey_hide").style = "display:block";
  }
</script>
@endsection
