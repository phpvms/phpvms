@extends('app')
@section('title', __('profile.editprofile'))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <h2>@lang('profile.edityourprofile')</h2>
            @include('flash::message')
            <form method="post" action="{{ route('frontend.profile.update', $user->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                @include('profile.fields')
            </form>
        </div>
    </div>
    <hr>
    <div class="row mt-5">
        <div class="col-sm-12">
            <div class="text-end">
                @if (isset($acars) && $acars === true)
                    <a href="{{ route('frontend.profile.acars') }}" class="btn btn-info"
                        onclick="alert('Copy or Save to \'My Documents/vmsacars/profiles\'')">ACARS Config</a>
                    &nbsp;
                @endif

                @if (config('services.discord.enabled') && !$user->discord_id)
                    <a href="{{ route('oauth.redirect', ['provider' => 'discord']) }}" class="btn"
                        style="background-color:#738ADB;">Link Discord Account</a>
                @elseif(config('services.discord.enabled'))
                    <a href="{{ route('oauth.logout', ['provider' => 'discord']) }}" class="btn"
                        style="background-color:#738ADB;">Unlink Discord Account</a>
                @endif

                @if (config('services.ivao.enabled') && !$user->ivao_id)
                    <a href="{{ route('oauth.redirect', ['provider' => 'ivao']) }}" class="btn"
                        style="background-color:#0d2c99;">Link IVAO Account</a>
                @elseif(config('services.ivao.enabled'))
                    <a href="{{ route('oauth.logout', ['provider' => 'ivao']) }}" class="btn"
                        style="background-color:#0d2c99;">Unlink IVAO Account</a>
                @endif

                @if (config('services.vatsim.enabled') && !$user->vatsim_id)
                    <a href="{{ route('oauth.redirect', ['provider' => 'vatsim']) }}" class="btn"
                        style="background-color:#29B473;">Link VATSIM Account</a>
                @elseif(config('services.vatsim.enabled'))
                    <a href="{{ route('oauth.logout', ['provider' => 'vatsim']) }}" class="btn"
                        style="background-color:#29B473;">Unlink VATSIM Account</a>
                @endif

                <a href="{{ route('frontend.profile.regen_apikey') }}" class="btn btn-warning"
                    onclick="return confirm('Are you sure? This will reset your API key!')">@lang('profile.newapikey')</a>
            </div>

            <h3>@lang('profile.your-profile')</h3>
            <table class="table table-responsive table-striped">
                <tr>
                    <th scope="row">@lang('common.email')</th>
                    <td>{{ $user->email }}</td>
                </tr>
                <tr>
                    <th scope="row">@lang('profile.apikey')&nbsp;&nbsp;<span class="">(@lang('profile.dontshare'))</span>
                    </th>
                    <td><span id="apiKey_show" style="display: none">{{ $user->api_key }} <i class="bi bi-eye"
                                onclick="apiKeyHide()"></i></span><span id="apiKey_hide">@lang('profile.apikey-show') <i
                                class="bi bi-eye" onclick="apiKeyShow()"></i></span></td>
                </tr>
                <tr>
                    <th scope="row">Discord ID</th>
                    <td>{{ $user->discord_id ?? '-' }}</td>
                </tr>
                <tr>
                    <th scope="row">@lang('common.timezone')</th>
                    <td>{{ $user->timezone }}</td>
                </tr>
                <tr>
                    <th scope="row">@lang('profile.opt-in')</th>
                    <td>{{ $user->opt_in ? __('common.yes') : __('common.no') }}</td>
                </tr>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <table class="table table-responsive table-striped">
                @foreach ($userFields as $field)
                    @if (!$field->private)
                        <tr>
                            <th scope="row">{{ $field->name }}</th>
                            <td>{{ $field->value ?? '-' }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    @include('scripts.airport_search')

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
