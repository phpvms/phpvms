<?php

declare(strict_types=1);

use App\Filament\Pages\Finances;
use Database\Seeders\RolesPermissionsSeeder;
use Filament\Forms\Components\DatePicker;
use Livewire\Livewire;

/**
 * The finances filter cannot reach before the VA's start date, and falls back
 * to the last year when no start date was ever set.
 */
function financesStartPicker(): DatePicker
{
    $page = Livewire::test(Finances::class)->instance();

    return $page->getSchema('filtersForm')->getFlatFields(withHidden: true)['start_date'];
}

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('starts the finances filter at the configured VA start date', function (): void {
    setting_save('general.start_date', '2020-01-15');

    expect(financesStartPicker()->getMinDate())->toStartWith('2020-01-15');
});

it('falls back to the last year when no start date is set', function (): void {
    expect(financesStartPicker()->getMinDate())->toStartWith(now()->subYear()->toDateString());
});
