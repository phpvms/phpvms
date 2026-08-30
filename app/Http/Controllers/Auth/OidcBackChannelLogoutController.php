<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\Controller;
use App\Features\OAuth\Helpers\ExternalAuthSessionService;
use App\Features\OAuth\Helpers\InvalidOidcLogoutToken;
use App\Features\OAuth\Helpers\OidcLogoutTokenValidator;
use App\Models\OAuthConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class OidcBackChannelLogoutController extends Controller
{
    public function __invoke(
        Request $request,
        string $connection,
        OidcLogoutTokenValidator $validator,
        ExternalAuthSessionService $sessions,
    ): Response {
        $oauthConnection = OAuthConnection::query()
            ->where('connection_id', $connection)
            ->firstOrFail();
        $logoutToken = $request->input('logout_token');

        if (!is_string($logoutToken) || $logoutToken === '') {
            return response('', Response::HTTP_BAD_REQUEST);
        }

        try {
            $identifiers = $validator->validate($oauthConnection, $logoutToken);
        } catch (InvalidOidcLogoutToken) {
            return response('', Response::HTTP_BAD_REQUEST);
        }

        $sessions->revoke($oauthConnection, $identifiers['sid'], $identifiers['subject']);

        return response('', Response::HTTP_NO_CONTENT);
    }
}
