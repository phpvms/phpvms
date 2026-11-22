<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class StorageLink implements Step
{
    public function step(): SetupStep
    {
        return SetupStep::StorageLink;
    }

    public function applies(StepContext $ctx): bool
    {
        return true;
    }

    public function run(StepContext $ctx): void
    {
        $failure = null;

        try {
            Artisan::call('storage:link', ['--relative' => true]);
        } catch (Throwable $throwable) {
            $failure = $throwable;
            Log::warning('Could not create the public/storage link', ['exception' => $throwable]);
        }

        // A leftover directory or broken link makes storage:link refuse, so verify the target.
        $target = realpath(storage_path('app/public'));

        if ($target === false || realpath(public_path('storage')) !== $target) {
            throw new RuntimeException('public/storage could not be linked to storage/app/public'.($failure instanceof Throwable ? ': '.$failure->getMessage() : ''), 0, $failure);
        }
    }
}
