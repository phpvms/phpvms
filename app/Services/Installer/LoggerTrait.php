<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Illuminate\Support\Facades\Log;

trait LoggerTrait
{
    protected function comment(string $text): void
    {
        Log::info($text);
    }

    protected function info(string $text): void
    {
        Log::info($text);
    }

    protected function error(string $text): void
    {
        Log::error($text);
    }
}
