<?php

namespace Tests;

use App\Models\Role;
use App\Models\Subfleet;
use App\Models\User;

final class AdminControllerTests extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');
    }

    private function addAdminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::where(['name' => 'admin'])->first();
        $user->addRole($role);

        return $user;
    }

    /**
     * Test adding a subfleet, deleting it and seeing that the type
     * can be added again
     */
    public function test_add_subfleet(): void
    {
        $user = $this->addAdminUser();
        $add = Subfleet::factory()->make(['type' => 'B737'])->toArray();
        $this->actingAs($user, 'web')->post('/admin/subfleets', $add);

        $add = Subfleet::factory()->make(['type' => 'A320'])->toArray();
        $this->actingAs($user, 'web')->post('/admin/subfleets', $add);

        // Make sure it was added
        $sf = Subfleet::where(['type' => $add['type']])->first();
        $this->assertNotNull($sf);

        $original_sf_id = $sf->id;

        // delete it
        $resp = $this->actingAs($user, 'web')->delete('/admin/subfleets/'.$sf->id);
        $sf = Subfleet::where(['type' => $add['type']])->first();
        $this->assertNull($sf);

        // Try readding now, it shouldn't complain about the type being unique
        // Would throw a validation error
        $resp = $this->actingAs($user, 'web')->post('/admin/subfleets', $add);
        $resp->assertSessionDoesntHaveErrors();

        $sf = Subfleet::where(['type' => $add['type']])->first();
        $this->assertNotNull($sf);
    }
}
