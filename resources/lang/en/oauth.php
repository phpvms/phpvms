<?php

declare(strict_types=1);

return [
    // Admin OAuth client resource
    'client'                  => 'OAuth Client',
    'clients'                 => 'OAuth Clients',
    'client_name'             => 'Name',
    'client_type'             => 'Client type',
    'client_type_hint'        => 'Authorization Code is for third-party apps with a secret; PKCE is for public apps (mobile/SPA) without a secret; Client Credentials is for machine-to-machine access.',
    'type_authorization_code' => 'Authorization Code (confidential)',
    'type_pkce'               => 'Authorization Code + PKCE (public)',
    'type_client_credentials' => 'Client Credentials (machine-to-machine)',
    'redirect_uris'           => 'Redirect URIs',
    'redirect_uris_hint'      => 'Where users are sent back after authorizing. Press Enter to add each URI.',
    'grant_types'             => 'Grant types',
    'confidential'            => 'Confidential',
    'public_pkce'             => 'Public (PKCE)',
    'revoked'                 => 'Revoked',
    'revoked_hint'            => 'Revoked clients can no longer be used to obtain or use tokens.',
    'rotate_secret'           => 'Rotate secret',
    'rotate_secret_confirm'   => 'This generates a new secret and immediately invalidates the current one. Any application using the old secret will stop working until updated.',
    'secret_created_title'    => 'Client secret — copy it now',
    'secret_rotated_title'    => 'New client secret — copy it now',
    'secret_created_body'     => 'Client ID: :id — Secret: :secret. This secret is stored hashed and will not be shown again.',

    // User Connections UI
    'connections'                 => 'Connections',
    'connections_intro'           => 'Manage the applications and tokens that can access the API on your behalf.',
    'authorized_apps'             => 'Authorized applications',
    'authorize_request'           => ':client is requesting permission to access your account.',
    'no_authorized_apps'          => 'You have not authorized any applications.',
    'revoke'                      => 'Revoke',
    'confirm_revoke_token'        => 'Revoke this token?',
    'confirm_revoke_connection'   => 'Revoke this application?',
    'scopes'                      => 'Scopes',
    'personal_access_tokens'      => 'Personal access tokens',
    'personal_access_tokens_hint' => 'Create a token to use the API from your own scripts or ACARS client. Choose only the scopes you need.',
    'create_token'                => 'Create token',
    'token_name'                  => 'Token name',
    'no_tokens'                   => 'You have not created any personal access tokens.',
    'token_created_title'         => 'Your new personal access token',
    'token_created_body'          => 'Copy it now — it will not be shown again.',
    'created'                     => 'Created',
    'expires'                     => 'Expires',
    'select_scopes'               => 'Select scopes',

    // Legacy api key section
    'legacy_key_deprecated' => 'The API key is being replaced by scoped personal access tokens. Prefer creating a personal access token above.',
];
