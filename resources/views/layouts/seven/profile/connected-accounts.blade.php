@if($oauthConnections->isNotEmpty())
    <div class="card my-4">
        <div class="card-header">
            <h3 class="mb-0">@lang('auth.connected_accounts')</h3>
        </div>
        <div class="list-group list-group-flush">
            @foreach($oauthConnections as $connection)
                <div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <strong>{{ $connection->display_name }}</strong>
                        <div class="text-muted small">
                            @lang($linkedConnectionIds->contains($connection->connection_id)
                                ? 'auth.oauth_connected'
                                : 'auth.oauth_not_connected')
                        </div>
                    </div>

                    @if($linkedConnectionIds->contains($connection->connection_id))
                        <form method="post" action="{{ route('oauth.unlink', ['provider' => $connection->connection_id]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                @lang('auth.unlink_provider', ['provider' => $connection->display_name])
                            </button>
                        </form>
                    @elseif($connectableConnectionIds->contains($connection->connection_id))
                        <a href="{{ route('oauth.redirect', [
                            'provider' => $connection->connection_id,
                            'intent' => 'link',
                        ]) }}" class="btn btn-outline-secondary btn-sm">
                            @lang('auth.link_provider', ['provider' => $connection->display_name])
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
