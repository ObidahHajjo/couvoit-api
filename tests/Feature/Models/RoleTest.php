<?php

namespace Tests\Feature\Models;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_has_timestamps_disabled(): void
    {
        $this->assertFalse((new Role())->timestamps);
    }

    public function test_role_fillable_allows_mass_assignment(): void
    {
        $role = Role::query()->create(['name' => 'admin']);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'admin',
        ]);
    }
}
