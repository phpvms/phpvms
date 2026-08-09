<?php

declare(strict_types=1);

namespace App\Services\Awards;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/**
 * Headless Livewire component used purely as a schema container.
 *
 * `Schema::make()` accepts a nullable container, but `Schema::getLivewire()`
 * declares a non-nullable return type and throws without one. This is the
 * smallest thing that satisfies it, so criteria can hydrate with no HTTP
 * request behind them (nightly cron, dry runs, jobs). It is never rendered.
 */
class CriteriaSchemaHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed> */
    public array $data = [];
}
