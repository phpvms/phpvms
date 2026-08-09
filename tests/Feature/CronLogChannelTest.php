<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function fireCommandStarting(string $command): void
{
    event(new CommandStarting($command, new ArrayInput([]), new NullOutput()));
}

test('the application does not default to the cron_daily channel', function (): void {
    // Regression: routes/console.php used to call Log::setDefaultDriver('cron_daily')
    // at file-load time, permanently switching the process-wide default channel.
    // Under Octane (octane:start is an Artisan command that loads the console
    // routes) this leaked into every HTTP worker, sending web logs to cron.log.
    expect(Log::getDefaultDriver())->not->toBe('cron_daily');
});

test('the scheduler switches the default log channel to cron_daily', function (): void {
    Log::setDefaultDriver('daily');

    fireCommandStarting('schedule:run');

    expect(Log::getDefaultDriver())->toBe('cron_daily');
});

test('the queue worker switches the default log channel to cron_daily', function (): void {
    Log::setDefaultDriver('daily');

    fireCommandStarting('queue:work');

    expect(Log::getDefaultDriver())->toBe('cron_daily');
});

test('other console commands keep the default log channel', function (): void {
    Log::setDefaultDriver('daily');

    fireCommandStarting('route:list');

    expect(Log::getDefaultDriver())->toBe('daily');
});
