<?php

namespace Tests\Feature;

use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OccupancyEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_occupancy_empty_state_guides_next_step(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant)->get(route('tenant.occupancy.index'));

        $response->assertOk();
        $response->assertSee('You do not have an active occupancy yet.');
        $response->assertSee('View payments');
    }

    protected function createTenant(): User
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }
}
