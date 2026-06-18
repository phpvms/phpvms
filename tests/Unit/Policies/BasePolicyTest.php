<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\User;
use App\Policies\BasePolicy;

/**
 * Concrete policy used only by these tests.
 */
class WidgetTestPolicy extends BasePolicy
{
    protected string $subject = 'widget-test';
}

function widgetUserWith(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $user->givePermissionTo($name);
    }

    return $user->fresh();
}

it('grants view, viewAny and reorder from the view ability', function (): void {
    $policy = new WidgetTestPolicy();
    $user = widgetUserWith('view:widget-test');

    expect($policy->viewAny($user))->toBeTrue();
    expect($policy->view($user))->toBeTrue();
    expect($policy->reorder($user))->toBeTrue();

    // view does not grant edit/delete methods
    expect($policy->create($user))->toBeFalse();
    expect($policy->update($user))->toBeFalse();
    expect($policy->delete($user))->toBeFalse();
});

it('grants create, update and replicate from the edit ability', function (): void {
    $policy = new WidgetTestPolicy();
    $user = widgetUserWith('edit:widget-test');

    expect($policy->create($user))->toBeTrue();
    expect($policy->update($user))->toBeTrue();
    expect($policy->replicate($user))->toBeTrue();

    expect($policy->viewAny($user))->toBeFalse();
    expect($policy->delete($user))->toBeFalse();
});

it('grants delete, restore and forceDelete from the delete ability', function (): void {
    $policy = new WidgetTestPolicy();
    $user = widgetUserWith('delete:widget-test');

    expect($policy->delete($user))->toBeTrue();
    expect($policy->deleteAny($user))->toBeTrue();
    expect($policy->restore($user))->toBeTrue();
    expect($policy->restoreAny($user))->toBeTrue();
    expect($policy->forceDelete($user))->toBeTrue();
    expect($policy->forceDeleteAny($user))->toBeTrue();

    expect($policy->viewAny($user))->toBeFalse();
    expect($policy->create($user))->toBeFalse();
});

it('denies everything without permissions', function (): void {
    $policy = new WidgetTestPolicy();
    $user = widgetUserWith();

    expect($policy->viewAny($user))->toBeFalse();
    expect($policy->create($user))->toBeFalse();
    expect($policy->delete($user))->toBeFalse();
});
