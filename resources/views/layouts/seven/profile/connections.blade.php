@extends('app')
@section('title', __('oauth.connections'))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <h2>@lang('oauth.connections')</h2>
            <p class="text-muted">@lang('oauth.connections_intro')</p>
            @include('flash::message')

            {{-- One-time display of a freshly created personal access token --}}
            @if (session('plain_text_token'))
                <div class="alert alert-success">
                    <strong>@lang('oauth.token_created_title')</strong>
                    <div class="small">@lang('oauth.token_created_body')</div>
                    <code class="d-block mt-2 p-2 bg-light border" style="word-break: break-all;">{{ session('plain_text_token') }}</code>
                </div>
            @endif
        </div>
    </div>

    {{-- Personal access tokens ------------------------------------------------}}
    <div class="row mt-4">
        <div class="col-md-12">
            <h3>@lang('oauth.personal_access_tokens')</h3>
            <p class="text-muted small">@lang('oauth.personal_access_tokens_hint')</p>

            <form method="post" action="{{ route('frontend.profile.tokens.store') }}" class="mb-4">
                @csrf
                <div class="mb-3">
                    <label class="form-label">@lang('oauth.token_name')</label>
                    <input type="text" name="name" class="form-control" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">@lang('oauth.select_scopes')</label>
                    <div class="row">
                        @foreach ($scopes as $scope => $description)
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]"
                                        value="{{ $scope }}" id="scope_{{ \Illuminate\Support\Str::slug($scope) }}">
                                    <label class="form-check-label" for="scope_{{ \Illuminate\Support\Str::slug($scope) }}">
                                        <code>{{ $scope }}</code> — {{ $description }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">@lang('oauth.create_token')</button>
            </form>

            @if ($personalTokens->isEmpty())
                <p class="text-muted">@lang('oauth.no_tokens')</p>
            @else
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>@lang('oauth.token_name')</th>
                            <th>@lang('oauth.scopes')</th>
                            <th>@lang('oauth.created')</th>
                            <th>@lang('oauth.expires')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($personalTokens as $token)
                            <tr>
                                <td>{{ $token->name }}</td>
                                <td>
                                    @forelse ($token->scopes as $scope)
                                        <span class="badge bg-secondary">{{ $scope }}</span>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                                <td>{{ optional($token->created_at)->diffForHumans() }}</td>
                                <td>{{ $token->expires_at ? $token->expires_at->diffForHumans() : __('common.never') }}</td>
                                <td class="text-end">
                                    <form method="post"
                                        action="{{ route('frontend.profile.tokens.destroy', $token->id) }}"
                                        onsubmit="return confirm('{{ __('oauth.confirm_revoke_token') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">@lang('oauth.revoke')</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Authorized third-party applications -----------------------------------}}
    <div class="row mt-5">
        <div class="col-md-12">
            <h3>@lang('oauth.authorized_apps')</h3>

            @if ($authorizedApps->isEmpty())
                <p class="text-muted">@lang('oauth.no_authorized_apps')</p>
            @else
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>@lang('oauth.client')</th>
                            <th>@lang('oauth.scopes')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($authorizedApps as $clientId => $clientTokens)
                            <tr>
                                <td>{{ $clientTokens->first()->client->name }}</td>
                                <td>
                                    @foreach ($clientTokens->pluck('scopes')->flatten()->unique() as $scope)
                                        <span class="badge bg-secondary">{{ $scope }}</span>
                                    @endforeach
                                </td>
                                <td class="text-end">
                                    <form method="post"
                                        action="{{ route('frontend.profile.connections.destroy', $clientId) }}"
                                        onsubmit="return confirm('{{ __('oauth.confirm_revoke_connection') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">@lang('oauth.revoke')</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <a href="{{ route('frontend.profile.edit', $user->id) }}" class="btn btn-secondary">@lang('common.back')</a>
        </div>
    </div>
@endsection
