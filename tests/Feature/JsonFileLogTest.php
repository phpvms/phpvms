<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Opcodes\LogViewer\LogFile;

test('file logs are JSON lines with a structured exception, and the admin log viewer reads them', function (): void {
    $path = storage_path('framework/testing/json-log-'.uniqid().'.log');
    config(['logging.channels.json_probe' => [...config('logging.channels.single'), 'path' => $path]]);

    Log::channel('json_probe')->error('boom happened', ['exception' => new RuntimeException('boom')]);

    $record = json_decode(trim((string) file_get_contents($path)), true, flags: JSON_THROW_ON_ERROR);
    $logs = new LogFile($path)->logs()->get();
    unlink($path);

    expect($record['level_name'])->toBe('ERROR')
        ->and($record['context']['exception']['class'])->toBe(RuntimeException::class)
        ->and($record['context']['exception']['trace'])->not->toBeEmpty()
        ->and($logs)->toHaveCount(1)
        ->and($logs[0]->message)->toBe('boom happened')
        ->and($logs[0]->level)->toBe('ERROR')
        ->and($logs[0]->context['exception']['message'])->toBe('boom');
});
