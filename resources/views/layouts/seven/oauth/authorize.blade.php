@extends('app')
@section('title', __('oauth.authorized_apps'))

@section('content')
    <div class="row justify-content-center mt-5">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <strong>{{ $client->name }}</strong>
                </div>
                <div class="card-body">
                    <p>{{ __('oauth.authorize_request', ['client' => $client->name]) }}</p>

                    @if (count($scopes) > 0)
                        <p>@lang('oauth.scopes'):</p>
                        <ul>
                            @foreach ($scopes as $scope)
                                <li>{{ $scope->description }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="d-flex gap-2 mt-3">
                        <form method="post" action="{{ route('passport.authorizations.approve') }}">
                            @csrf
                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button type="submit" class="btn btn-success">@lang('common.confirm')</button>
                        </form>

                        <form method="post" action="{{ route('passport.authorizations.deny') }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="state" value="{{ $request->state }}">
                            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button type="submit" class="btn btn-danger">@lang('common.cancel')</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
