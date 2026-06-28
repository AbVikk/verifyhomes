<?php

namespace Tests\Feature;

use App\Livewire\Admin\Occupancy\Index as AdminOccupancyIndex;
use App\Livewire\Tenant\Occupancy\Index as TenantOccupancyIndex;
use App\Models\Occupancy;
use App\Models\OccupancyComplaint;
use App\Models\OccupancyMoveOutRequest;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OccupancyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('tenant', 'web');
        Role::findOrCreate('landlord', 'web');
    }

    public function test_tenant_can_access_the_occupancy_page(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Tenant Occupancy Listing',
        ]);
        $this->createOccupancy($tenant, $property);

        $response = $this->actingAs($tenant)->get(route('tenant.occupancy.index'));

        $response->assertOk()
            ->assertSee('Your active stays and next steps')
            ->assertSee('Tenant Occupancy Listing');
    }

    public function test_landlord_can_access_the_occupancy_page(): void
    {
        $landlord = $this->createLandlord();
        $tenant = $this->createTenant();
        $property = $this->createProperty($landlord, [
            'title' => 'Landlord Occupancy Listing',
        ]);
        $this->createOccupancy($tenant, $property);

        $response = $this->actingAs($landlord)->get(route('landlord.occupancy.index'));

        $response->assertOk()
            ->assertSee('Active tenants and rent cadence')
            ->assertSee('Landlord Occupancy Listing')
            ->assertSee($tenant->name);
    }

    public function test_landlord_sees_only_occupants_for_their_properties(): void
    {
        $landlord = $this->createLandlord('owner@example.com');
        $otherLandlord = $this->createLandlord('other-owner@example.com');
        $tenant = $this->createTenant();

        $ownedProperty = $this->createProperty($landlord, [
            'title' => 'Owned Occupancy Listing',
        ]);
        $otherProperty = $this->createProperty($otherLandlord, [
            'title' => 'Other Occupancy Listing',
        ]);

        $this->createOccupancy($tenant, $ownedProperty);
        $this->createOccupancy($tenant, $otherProperty);

        $response = $this->actingAs($landlord)->get(route('landlord.occupancy.index'));

        $response->assertOk()
            ->assertSee('Owned Occupancy Listing')
            ->assertDontSee('Other Occupancy Listing');
    }

    public function test_tenant_can_create_a_move_out_request(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord());
        $occupancy = $this->createOccupancy($tenant, $property);

        $this->actingAs($tenant);

        Livewire::test(TenantOccupancyIndex::class)
            ->set("moveOutNotes.{$occupancy->id}", 'Moving at the end of the month.')
            ->call('submitMoveOutRequest', $occupancy->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('occupancy_move_out_requests', [
            'occupancy_id' => $occupancy->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('occupancies', [
            'id' => $occupancy->id,
            'status' => 'move_out_pending',
        ]);
    }

    public function test_move_out_does_not_release_units_until_admin_approval(): void
    {
        $landlord = $this->createLandlord('move-out-landlord@example.com');
        $tenant = $this->createTenant('move-out-tenant@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Move Out Listing',
            'total_units' => 2,
            'occupied_units' => 1,
        ]);
        $occupancy = $this->createOccupancy($tenant, $property, [
            'units' => 1,
            'status' => 'move_out_pending',
        ]);
        $request = OccupancyMoveOutRequest::create([
            'occupancy_id' => $occupancy->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $property->refresh();
        $this->assertSame(1, $property->occupied_units);

        $admin = $this->createRoleUser('admin', 'move-out-admin@example.com');

        $this->actingAs($admin);

        Livewire::test(AdminOccupancyIndex::class)
            ->call('approveMoveOut', $request->id)
            ->assertHasNoErrors();

        $property->refresh();
        $occupancy->refresh();
        $request->refresh();

        $this->assertSame(0, $property->occupied_units);
        $this->assertSame('moved_out', $occupancy->status);
        $this->assertSame('approved', $request->status);
    }

    public function test_tenant_can_log_a_complaint_and_admin_can_view_it(): void
    {
        $tenant = $this->createTenant('complaint-tenant@example.com');
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Complaint Listing',
        ]);
        $occupancy = $this->createOccupancy($tenant, $property);

        $this->actingAs($tenant);

        Livewire::test(TenantOccupancyIndex::class)
            ->set("complaintCategory.{$occupancy->id}", 'maintenance')
            ->set("complaintDescription.{$occupancy->id}", 'The bathroom taps are leaking.')
            ->call('submitComplaint', $occupancy->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('occupancy_complaints', [
            'occupancy_id' => $occupancy->id,
            'tenant_id' => $tenant->id,
            'category' => 'maintenance',
            'status' => 'open',
        ]);

        $admin = $this->createRoleUser('admin', 'complaint-admin@example.com');

        $response = $this->actingAs($admin)->get(route('admin.occupancy.index'));

        $response->assertOk()
            ->assertSee('Complaint Listing')
            ->assertSee('Maintenance')
            ->assertSee('The bathroom taps are leaking.');
    }

    public function test_payment_countdown_and_overdue_state_render_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-02 12:00:00'));

        $tenant = $this->createTenant('countdown-tenant@example.com');
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Countdown Listing',
        ]);

        $this->createOccupancy($tenant, $property, [
            'next_payment_due_at' => now()->addDays(12),
        ]);
        $this->createOccupancy($tenant, $property, [
            'next_payment_due_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.occupancy.index'));

        $response->assertOk()
            ->assertSee('Rent due in 12 days.')
            ->assertSee('Rent overdue by 5 days.');

        Carbon::setTestNow();
    }

    public function test_shell_navigation_includes_occupancy_links(): void
    {
        $tenant = $this->createTenant('nav-tenant@example.com');
        $landlord = $this->createLandlord('nav-landlord@example.com');

        $this->actingAs($tenant)
            ->get(route('tenant.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('tenant.occupancy.index').'"', false)
            ->assertSee('My Stays');

        $this->actingAs($landlord)
            ->get(route('landlord.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('landlord.occupancy.index').'"', false)
            ->assertSee('Occupants');
    }

    public function test_admin_summary_counts_and_reminder_action_work(): void
    {
        Notification::fake();

        $admin = $this->createRoleUser('admin', 'summary-admin@example.com');
        $tenant = $this->createTenant('summary-tenant@example.com');
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Overdue Listing',
        ]);
        $occupancy = $this->createOccupancy($tenant, $property, [
            'next_payment_due_at' => now()->subDays(4),
        ]);

        OccupancyMoveOutRequest::create([
            'occupancy_id' => $occupancy->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        OccupancyComplaint::create([
            'occupancy_id' => $occupancy->id,
            'tenant_id' => $tenant->id,
            'category' => 'utilities',
            'description' => 'Power is unavailable.',
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.occupancy.index'));

        $response->assertOk()
            ->assertSee('Pending move-outs')
            ->assertSee('Open complaints')
            ->assertSee('Overdue rent');

        $this->actingAs($admin);

        Livewire::test(AdminOccupancyIndex::class)
            ->call('sendPaymentReminder', $occupancy->id)
            ->assertHasNoErrors();

        Notification::assertSentTo($tenant, \App\Notifications\OccupancyPaymentReminder::class);
    }

    public function test_purchase_state_is_displayed_when_listing_is_not_for_rent(): void
    {
        $tenant = $this->createTenant('purchase-tenant@example.com');
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Purchase Listing',
            'listing_intent' => 'for_sale',
        ]);
        $this->createOccupancy($tenant, $property);

        $response = $this->actingAs($tenant)->get(route('tenant.occupancy.index'));

        $response->assertOk()
            ->assertSee('Purchase recorded')
            ->assertSee('You are recorded as a buyer. Ongoing rent scheduling is not required.');
    }

    protected function createRoleUser(string $role, ?string $email = null): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createTenant(?string $email = null): User
    {
        $tenant = $this->createRoleUser('tenant', $email);

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(?string $email = null): User
    {
        $landlord = $this->createRoleUser('landlord', $email);

        return $landlord;
    }

    protected function createProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Occupancy Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'pricing_model' => 'tenant_price',
            'pricing_input_amount' => 850000,
            'rent_amount' => 850000,
            'landlord_net_amount' => 680000,
            'platform_fee_percentage' => 20,
            'total_units' => 2,
            'occupied_units' => 0,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    protected function createOccupancy(User $tenant, Property $property, array $overrides = []): Occupancy
    {
        return Occupancy::create(array_merge([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'units' => 1,
            'payment_cycle_months' => 12,
            'started_at' => now()->subMonth(),
            'last_payment_at' => now()->subMonth(),
            'next_payment_due_at' => now()->addMonths(11),
        ], $overrides));
    }
}
