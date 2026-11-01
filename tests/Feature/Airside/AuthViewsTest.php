<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

/**
 * A failed login sends the visitor back to the form with the error and the
 * entered email.
 */
beforeEach(function (): void {
    useAirside();
});

it('sends a failed login back to the form with the error and old email', function (): void {
    $this->from('/login')
        ->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors()
        ->assertSessionHasInput('email', 'nobody@example.com');
});
