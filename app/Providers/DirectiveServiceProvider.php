<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Units\Time;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Keep custom directives that can be used in templates
 */
class DirectiveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blade::directive('minutestotime', fn (string $expr): string => sprintf('<?php echo '.Time::class.'::minutesToTimeString(%s); ?>', $expr));

        Blade::directive('minutestohours', fn (string $expr): string => sprintf('<?php echo '.Time::class.'::minutesToHours(%s); ?>', $expr));

        Blade::directive('secstohhmm', fn (string $expr): string => sprintf('<?php echo secstohhmm(%s); ?>', $expr));
    }
}
