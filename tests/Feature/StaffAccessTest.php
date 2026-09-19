<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settlements\Index as SettlementIndex;
use App\Models\LandlordProfile;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'staff', 'support_staff', 'tenant', 'landlord'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_admin_can_create_staff_and_only_admin_can_manage_staff_accounts(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user('staff');

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Operations Staff',
            'email' => 'operations@example.test',
            'phone' => '08000000000',
        ])->assertRedirect(route('admin.staff.index'));

        $member = User::query()->where('email', 'operations@example.test')->firstOrFail();
        $this->assertTrue($member->hasRole('staff'));
        $this->assertTrue($member->must_change_password);
        $this->assertNotEmpty(session('staff_credentials.password'));
        $this->actingAs($member)->get(route('admin.dashboard'))->assertRedirect(route('profile.edit'));

        $this->actingAs($staff)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.staff.store'), ['name' => 'Nope', 'email' => 'nope@example.test'])->assertForbidden();
    }

    public function test_staff_can_log_in_and_access_only_the_operational_workspace(): void
    {
        $staff = $this->user('staff');

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('Staff Console')
            ->assertSee('Properties')
            ->assertSee('Landlords')
            ->assertSee('Tenants')
            ->assertSee('Inspection Requests')
            ->assertSee('Maintenance')
            ->assertSee('Payments')
            ->assertSee('Settlements')
            ->assertDontSee('Support Requests')
            ->assertDontSee('Support Team')
            ->assertDontSee('Audit')
            ->assertDontSee('>Staff<', false);

        foreach ([
            'admin.properties.index',
            'admin.landlords.index',
            'admin.tenants.index',
            'admin.inspection-requests.index',
            'admin.occupancy.index',
            'admin.maintenance.index',
            'admin.payments.index',
            'admin.settlements.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_staff_cannot_access_sensitive_kyc_support_or_financial_mutations(): void
    {
        $staff = $this->user('staff');
        $tenant = $this->user('tenant');
        $profile = TenantProfile::create(['user_id' => $tenant->id, 'verification_status' => 'pending', 'id_number' => 'A123456789']);

        $this->actingAs($staff)->get(route('admin.tenants.verification.download', [$profile, 'id']))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.tenants.show', $profile))->assertOk()->assertDontSee('A123456789');
        $this->actingAs($staff)->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.support-team.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.audit.index'))->assertForbidden();
        Livewire::actingAs($staff)->test(SettlementIndex::class)->call('beginPayout', 1)->assertForbidden();
        Livewire::actingAs($staff)->test(SettlementIndex::class)->call('beginReversal', 1)->assertForbidden();

        $landlord = $this->user('landlord');
        $landlordProfile = LandlordProfile::create(['user_id' => $landlord->id, 'account_number' => '0123456789']);
        $this->actingAs($staff)->get(route('admin.landlords.show', $landlordProfile))->assertOk()->assertDontSee('0123456789');
    }

    public function test_support_staff_tenant_and_landlord_cannot_use_the_staff_workspace(): void
    {
        foreach (['support_staff', 'tenant', 'landlord'] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.dashboard'))->assertForbidden();
        }
    }

    public function test_inactive_staff_cannot_log_in_or_use_operational_routes(): void
    {
        $staff = $this->user('staff', ['suspended_at' => now(), 'status' => 'inactive']);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
