<?php

namespace Tests\Feature;

use App\Models\LandlordProfile;
use App\Models\Occupancy;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
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

    public function test_tenant_can_view_notifications_center(): void
    {
        $tenant = $this->createRoleUser('tenant');

        UserNotification::create([
            'user_id' => $tenant->id,
            'title' => 'Rent payment confirmed',
            'body' => 'Your rent payment is confirmed.',
            'category' => 'payment_confirmed',
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.notifications.index'));

        $response->assertOk();
        $response->assertSee('Notifications');
        $response->assertSee('Rent payment confirmed');
        $response->assertSee('Unread');
    }

    public function test_rent_reminder_command_is_scheduled_daily(): void
    {
        $exitCode = Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('0 6 * * *', $output);
        $this->assertStringContainsString('rent-reminders:generate', $output);
    }

    public function test_rent_reminder_command_generates_sixty_day_notifications_for_active_rent_occupancy(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $admin = $this->createRoleUser('admin');
        $tenant = $this->createTenant('sixty-tenant@example.com');
        $landlord = $this->createLandlord('sixty-landlord@example.com');
        $property = $this->createRentProperty($landlord, ['title' => 'Sixty Day Rent Home']);
        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->addDays(60)]);

        Artisan::call('rent-reminders:generate');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Your rent is due in 60 days',
            'category' => 'rent_reminder:tenant:1:due_in_60_days:2026-08-03',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $landlord->id,
            'title' => 'Tenant rent due in 60 days',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Upcoming rent due in 60 days',
        ]);

        Carbon::setTestNow();
    }

    public function test_rent_reminder_command_generates_thirty_day_and_seven_day_notifications(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $tenant = $this->createTenant('stages-tenant@example.com');
        $landlord = $this->createLandlord('stages-landlord@example.com');
        $property = $this->createRentProperty($landlord, ['title' => 'Staged Rent Home']);

        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->addDays(30)]);
        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->addDays(7)]);

        Artisan::call('rent-reminders:generate');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Your rent is due in 30 days',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Your rent is due in 7 days',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $landlord->id,
            'title' => 'Tenant rent due in 30 days',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $landlord->id,
            'title' => 'Tenant rent due in 7 days',
        ]);

        Carbon::setTestNow();
    }

    public function test_rent_reminder_command_generates_due_today_and_overdue_notifications(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $tenant = $this->createTenant('overdue-tenant@example.com');
        $landlord = $this->createLandlord('overdue-landlord@example.com');
        $property = $this->createRentProperty($landlord, ['title' => 'Overdue Rent Home']);

        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()]);
        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->subDays(7)]);
        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->subDays(30)]);

        Artisan::call('rent-reminders:generate');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Your rent is due today',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Your rent is overdue by 7 days',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $tenant->id,
            'title' => 'Your rent is overdue by 30 days',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $landlord->id,
            'title' => 'Tenant rent overdue by 7 days',
        ]);

        Carbon::setTestNow();
    }

    public function test_rent_reminders_skip_purchase_and_moved_out_occupancies(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $tenant = $this->createTenant('skip-tenant@example.com');
        $landlord = $this->createLandlord('skip-landlord@example.com');
        $purchaseProperty = $this->createRentProperty($landlord, [
            'title' => 'Purchased Home',
            'listing_intent' => 'for_sale',
        ]);
        $rentProperty = $this->createRentProperty($landlord, ['title' => 'Moved Out Home']);

        $this->createOccupancy($tenant, $purchaseProperty, ['next_payment_due_at' => now()->addDays(30)]);
        $this->createOccupancy($tenant, $rentProperty, [
            'status' => 'moved_out',
            'next_payment_due_at' => now()->addDays(30),
        ]);

        Artisan::call('rent-reminders:generate');

        $this->assertDatabaseCount('user_notifications', 0);

        Carbon::setTestNow();
    }

    public function test_rent_reminders_are_deduplicated_by_occupancy_stage_and_due_cycle(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $this->createRoleUser('admin');
        $tenant = $this->createTenant('dedupe-tenant@example.com');
        $landlord = $this->createLandlord('dedupe-landlord@example.com');
        $property = $this->createRentProperty($landlord, ['title' => 'Dedupe Rent Home']);
        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->addDays(7)]);

        Artisan::call('rent-reminders:generate');
        Artisan::call('rent-reminders:generate');

        $this->assertSame(3, UserNotification::query()->count());
        $this->assertSame(1, UserNotification::query()->where('user_id', $tenant->id)->count());
        $this->assertSame(1, UserNotification::query()->where('user_id', $landlord->id)->count());

        Carbon::setTestNow();
    }

    public function test_tenant_landlord_and_admin_can_view_generated_rent_reminders(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $admin = $this->createRoleUser('admin');
        $tenant = $this->createTenant('view-tenant@example.com');
        $landlord = $this->createLandlord('view-landlord@example.com');
        $property = $this->createRentProperty($landlord, ['title' => 'Visible Reminder Home']);
        $this->createOccupancy($tenant, $property, ['next_payment_due_at' => now()->addDays(7)]);

        Artisan::call('rent-reminders:generate');

        $this->actingAs($tenant)
            ->get(route('tenant.notifications.index'))
            ->assertOk()
            ->assertSee('Your rent is due in 7 days')
            ->assertSee('Visible Reminder Home');

        $this->actingAs($landlord)
            ->get(route('landlord.notifications.index'))
            ->assertOk()
            ->assertSee('Tenant rent due in 7 days')
            ->assertSee('Visible Reminder Home')
            ->assertDontSee('Inspection payment')
            ->assertDontSee('Booking fee');

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Upcoming rent due in 7 days')
            ->assertSee('Visible Reminder Home')
            ->assertSee('Operational updates');

        Carbon::setTestNow();
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

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'approved',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        return $landlord;
    }

    protected function createRentProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Reminder Rent Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'pricing_model' => 'tenant_price',
            'pricing_input_amount' => 850000,
            'rent_amount' => 850000,
            'landlord_net_amount' => 680000,
            'platform_fee_percentage' => 20,
            'total_units' => 2,
            'occupied_units' => 1,
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
            'started_at' => now()->subMonths(10),
            'last_payment_at' => now()->subMonths(10),
            'next_payment_due_at' => now()->addDays(30),
        ], $overrides));
    }
}
