<?php

declare(strict_types=1);

namespace App\Features\Bids\Livewire;

use Illuminate\Support\Facades\Blade;
use Livewire\Component;

/**
 * A component class inside the Bids slice, for the namespace test only.
 */
final class NamespaceProbe extends Component
{
    public function render(): string
    {
        return '<div>bids probe</div>';
    }
}

it('resolves a slice Livewire component by its slice namespace', function (): void {
    expect(Blade::render('<livewire:bids::namespace-probe />'))->toContain('bids probe');
});
