<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Illuminate\Support\Facades\Log;

trait LoggerTrait
{
    protected function comment($text)
    {
        Log::info($text);
    }

    protected function info($text)
    {
        Log::info($text);
    }

    protected function error($text)
    {
        Log::error($text);
    }
}
