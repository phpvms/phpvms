@extends('auth.login_layout')
@section('title', __('auth.oauth_not_linked_title'))

@section('content')
    <div class="d-flex flex-column justify-content-center align-items-center">
        <div class="card w-100" style="max-width: 440px;">
            <div class="card-body">
                <h1 class="h3">@lang('auth.oauth_not_linked_title')</h1>
                <p class="text-muted">
                    @lang('auth.oauth_not_linked_message', ['provider' => $connection->display_name])
                </p>

                @if($verifiedEmailMatch)
                    <div class="alert alert-info" role="status">
                        @lang('auth.oauth_verified_email_match', ['email' => $pending['email']])
                    </div>
                @endif

                <div class="d-grid gap-2">
                    @if($registrationAvailable)
                        <form method="post" action="{{ route('oauth.register', ['provider' => $connection->connection_id]) }}"
                            class="d-grid">
                            @csrf
                            <button type="submit" class="btn btn-primary">@lang('auth.oauth_create_pilot')</button>
                        </form>
                    @endif

                    @if($connection->linking_enabled)
                        <form method="post" action="{{ route('oauth.link', ['provider' => $connection->connection_id]) }}"
                            class="d-grid">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">
                                @lang('auth.oauth_link_existing')
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-footer">
                <form method="post" action="{{ route('oauth.cancel') }}">
                    @csrf
                    <button type="submit" class="btn btn-link p-0">@lang('auth.oauth_cancel')</button>
                </form>
            </div>
        </div>
    </div>
@endsection
