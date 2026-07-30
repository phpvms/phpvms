<?php

use App\Models\User;

test('login form action uses https when behind a TLS-terminating proxy', function (): void {
    // A user must exist or InstalledCheck redirects to the installer.
    User::factory()->create();

    // Caddy terminates TLS and forwards plain HTTP with X-Forwarded-Proto: https.
    $response = $this->get('/login', ['X-Forwarded-Proto' => 'https']);

    $response->assertOk();
    // The form action must honour the forwarded scheme, otherwise the browser
    // warns about posting from an https page to an http action.
    $response->assertSee('action="https://', false);
});
