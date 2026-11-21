<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

test('an expected API error such as a 404 is not logged', function (): void {
    Log::spy();

    $this->getJson('/api/does-not-exist', ['Authorization' => 'Bearer secret-token-123'])->assertNotFound();

    Log::shouldNotHaveReceived('error');
});

test('a logged API exception carries no argument values, so request data never reaches the log', function (): void {
    Route::middleware('api')->get('api/test-boom', static fn () => throw new RuntimeException('boom'));
    $logged = null;
    Log::listen(static function ($message) use (&$logged): void {
        $logged = $message->context['exception'] ?? $logged;
    });

    $this->getJson('/api/test-boom', ['Authorization' => 'Bearer secret-token-123'])->assertServerError();

    expect($logged)->toBeInstanceOf(RuntimeException::class)
        // include/require frames always name the file; only function calls carry request data.
        ->and(array_filter(
            $logged->getTrace(),
            static fn (array $frame): bool => array_key_exists('args', $frame) && !in_array($frame['function'], ['include', 'include_once', 'require', 'require_once'], true),
        ))->toBe([]);
});
