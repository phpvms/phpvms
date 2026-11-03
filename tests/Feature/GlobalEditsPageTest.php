<?php

declare(strict_types=1);

use App\Enums\GlobalSlotPosition;
use App\Enums\GlobalSlotType;
use App\Filament\Pages\GlobalEdits;
use App\Livewire\Filament\GlobalSlotList;
use App\Models\GlobalSlot;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function globalSlotAdmin(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('refuses the page without view:global-slot', function (): void {
    $this->actingAs(globalSlotAdmin('view:theme', 'edit:theme'));

    expect(GlobalEdits::canAccess())->toBeFalse();
    $this->get(GlobalEdits::getUrl())->assertForbidden();
});

it('refuses a view-only admin when re-ordering and leaves the order unchanged', function (): void {
    $first = GlobalSlot::factory()->create(['order' => 1]);
    $second = GlobalSlot::factory()->create(['order' => 2]);
    $this->actingAs(globalSlotAdmin('view:global-slot'));

    expect(GlobalEdits::canAccess())->toBeTrue()
        ->and(GlobalEdits::canEdit())->toBeFalse();

    livewire(GlobalSlotList::class, ['canEdit' => GlobalEdits::canEdit()])
        ->assertTableActionHidden('edit', $first)
        ->assertTableActionHidden('delete', $first)
        ->call('reorderTable', [(string) $second->getKey(), (string) $first->getKey()]);

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(2);
});

it('creates a row under the global key for an admin with edit:global-slot', function (): void {
    $this->actingAs(globalSlotAdmin('view:global-slot', 'edit:global-slot'));

    expect(GlobalEdits::canEdit())->toBeTrue();

    livewire(GlobalSlotList::class, ['canEdit' => GlobalEdits::canEdit()])
        ->callAction(TestAction::make('create')->table(), [
            'name'  => 'Analytics',
            'slot'  => GlobalSlotPosition::HEAD->value,
            'type'  => GlobalSlotType::HTML->value,
            'value' => '<meta name="a" content="b">',
        ])
        ->assertHasNoFormErrors();

    expect(GlobalSlot::query()->where('name', 'Analytics')->value('key'))->toBe('global');
});
