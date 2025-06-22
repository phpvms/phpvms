<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Keep custom directives that can be used in templates
 */
class DirectiveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blade::directive('minutestotime', fn ($expr): string => "<?php echo \App\Support\Units\Time::minutesToTimeString($expr); ?>");

        Blade::directive('minutestohours', fn ($expr): string => "<?php echo \App\Support\Units\Time::minutesToHours($expr); ?>");

        Blade::directive('secstohhmm', fn ($expr): string => "<?php echo secstohhmm($expr); ?>");
    }
}
