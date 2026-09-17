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

    public function test_opening_notifications_marks_only_the_current_users_copy_read_before_redirecting(): void
    {
        $tenant = $this->createTenant('notification-count-tenant@example.com');
        $notifications = collect(range(1, 7))->map(fn (int $number) => $this->createNotification(
            $tenant,
            "Tenant update {$number}",
            route('tenant.dashboard'),
        ));

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertOk()
            ->assertSee('7 unread updates')
            ->assertSee('data-notification-unread-badge', false);

        $this->actingAs($tenant)->get(route('notifications.open', $notifications->get(0)))
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertNotNull($notifications->get(0)->fresh()->read_at);

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertSee('6 unread updates');

        $this->actingAs($tenant)->get(route('notifications.open', $notifications->get(1)))
            ->assertRedirect(route('tenant.dashboard'));

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertSee('5 unread updates');

        $this->actingAs($tenant)->get(route('notifications.open', $notifications->get(0)))
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertSame(5, UserNotification::query()->forUser($tenant->id)->unread()->count());

        foreach ($notifications->slice(2) as $notification) {
            $this->actingAs($tenant)->get(route('notifications.open', $notification));
        }

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertSee('All caught up')
            ->assertDontSee('data-notification-unread-badge', false);
    }

    public function test_dropdown_orders_unread_before_read_and_uses_read_time_for_read_items(): void
    {
        $tenant = $this->createTenant('notification-order-tenant@example.com');

        Carbon::setTestNow('2026-09-10 09:00:00');
        $readOlder = $this->createNotification($tenant, 'Read older', route('tenant.dashboard'));
        $readOlder->update(['read_at' => now()->subMinutes(2)]);
        $unreadOlder = $this->createNotification($tenant, 'Unread older', route('tenant.dashboard'));

        Carbon::setTestNow('2026-09-10 09:01:00');
        $readNewest = $this->createNotification($tenant, 'Read newest', route('tenant.dashboard'));
        $readNewest->update(['read_at' => now()->addMinutes(2)]);
        $unreadNewest = $this->createNotification($tenant, 'Unread newest', route('tenant.dashboard'));

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertSeeInOrder([
                'data-notification-group="unread"',
                'Unread newest',
                'Unread older',
                'data-notification-group="read"',
                'Read newest',
                'Read older',
            ], false);

        Carbon::setTestNow('2026-09-10 09:05:00');
        $this->actingAs($tenant)->get(route('notifications.open', $unreadNewest))
            ->assertRedirect(route('tenant.dashboard'));

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertSeeInOrder([
                'data-notification-group="unread"',
                'Unread older',
                'data-notification-group="read"',
                'Unread newest',
                'Read newest',
                'Read older',
            ], false)
            ->assertSee('data-notification-unread-badge', false);

        $this->actingAs($tenant)
            ->from(route('tenant.dashboard'))
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect(route('tenant.dashboard'));

        $this->actingAs($tenant)->get(route('tenant.dashboard'))
            ->assertSee('data-notification-group="read"', false)
            ->assertDontSee('data-notification-group="unread"', false)
            ->assertDontSee('data-notification-unread-badge', false)
            ->assertSee('Unread older');
        $this->assertSame(4, UserNotification::query()->forUser($tenant->id)->count());

        Carbon::setTestNow();
    }

    public function test_shared_notification_dropdown_renders_for_tenant_landlord_admin_and_staff(): void
    {
        $tenant = $this->createTenant('shared-dropdown-tenant@example.com');
        $landlord = $this->createLandlord('shared-dropdown-landlord@example.com');
        $admin = $this->createRoleUser('admin', 'shared-dropdown-admin@example.com');
        $staff = $this->createRoleUser('staff', 'shared-dropdown-staff@example.com');

        foreach ([
            [$tenant, 'Tenant dropdown update', route('tenant.dashboard')],
            [$landlord, 'Landlord dropdown update', route('landlord.dashboard')],
            [$admin, 'Admin dropdown update', route('admin.dashboard')],
            [$staff, 'Staff dropdown update', route('admin.dashboard')],
        ] as [$user, $title, $dashboardRoute]) {
            $this->createNotification($user, $title, $dashboardRoute);

            $this->actingAs($user)->get($dashboardRoute)
                ->assertOk()
                ->assertSee('data-admin-notifications-menu', false)
                ->assertSee('data-notification-group="unread"', false)
                ->assertSee('Mark all as read')
                ->assertSee('View all notifications');
        }
    }

    public function test_notification_center_uses_the_shared_open_route_and_marks_all_only_for_current_user(): void
    {
        $tenant = $this->createTenant('mark-all-tenant@example.com');
        $landlord = $this->createLandlord('mark-all-landlord@example.com');
        $tenantNotification = $this->createNotification($tenant, 'Tenant unread update', route('tenant.dashboard'));
        $this->createNotification($tenant, 'Tenant second unread update', route('tenant.dashboard'));
        $landlordNotification = $this->createNotification($landlord, 'Landlord unread update', route('landlord.dashboard'));

        $this->actingAs($tenant)->get(route('tenant.notifications.index'))
            ->assertOk()
            ->assertSee('Mark all as read')
            ->assertSee(route('notifications.open', $tenantNotification), false)
            ->assertSee('data-notification-state="unread"', false);

        $this->actingAs($tenant)
            ->from(route('tenant.notifications.index'))
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect(route('tenant.notifications.index'));

        $this->assertSame(0, UserNotification::query()->forUser($tenant->id)->unread()->count());
        $this->assertNull($landlordNotification->fresh()->read_at);

        $this->actingAs($tenant)->get(route('tenant.notifications.index'))
            ->assertDontSee('Mark all as read')
            ->assertDontSee('data-notification-state="unread"', false)
            ->assertSee('data-notification-state="read"', false);
    }

    public function test_notification_opening_is_owner_scoped_safe_and_independent_for_each_role(): void
    {
        $tenant = $this->createTenant('open-tenant@example.com');
        $landlord = $this->createLandlord('open-landlord@example.com');
        $admin = $this->createRoleUser('admin', 'open-admin@example.com');
        $staff = $this->createRoleUser('staff', 'open-staff@example.com');
        $tenantNotification = $this->createNotification($tenant, 'Tenant update', route('tenant.dashboard'));
        $landlordNotification = $this->createNotification($landlord, 'Landlord update', route('landlord.dashboard'));
        $adminNotification = $this->createNotification($admin, 'Admin update', route('admin.dashboard'));
        $staffNotification = $this->createNotification($staff, 'Staff update', route('admin.dashboard'));
        $externalNotification = $this->createNotification($tenant, 'Unsafe destination', 'https://example.test/not-allowed');

        $this->actingAs($tenant)->get(route('notifications.open', $landlordNotification))->assertNotFound();
        $this->assertNull($landlordNotification->fresh()->read_at);

        $this->actingAs($tenant)->get(route('notifications.open', $tenantNotification))->assertRedirect(route('tenant.dashboard'));
        $this->actingAs($landlord)->get(route('notifications.open', $landlordNotification))->assertRedirect(route('landlord.dashboard'));
        $this->actingAs($admin)->get(route('notifications.open', $adminNotification))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->get(route('notifications.open', $staffNotification))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($tenant)->get(route('notifications.open', $externalNotification))->assertRedirect(route('tenant.dashboard'));

        $this->assertNotNull($tenantNotification->fresh()->read_at);
        $this->assertNotNull($landlordNotification->fresh()->read_at);
        $this->assertNotNull($adminNotification->fresh()->read_at);
        $this->assertNotNull($staffNotification->fresh()->read_at);
        $this->assertNotNull($externalNotification->fresh()->read_at);
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
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $landlord->id]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $admin->id]);

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

    public function test_rent_reminders_skip_purchase_moved_out_and_upcoming_occupancies(): void
    {
        Carbon::setTestNow('2026-06-04 09:00:00');

        $tenant = $this->createTenant('skip-tenant@example.com');
        $landlord = $this->createLandlord('skip-landlord@example.com');
        $purchaseProperty = $this->createRentProperty($landlord, [
            'title' => 'Purchased Home',
            'listing_intent' => 'for_sale',
        ]);
        $rentProperty = $this->createRentProperty($landlord, ['title' => 'Moved Out Home']);
        $upcomingProperty = $this->createRentProperty($landlord, ['title' => 'Upcoming Home']);

        $this->createOccupancy($tenant, $purchaseProperty, ['next_payment_due_at' => now()->addDays(30)]);
        $this->createOccupancy($tenant, $rentProperty, [
            'status' => 'moved_out',
            'next_payment_due_at' => now()->addDays(30),
        ]);
        $this->createOccupancy($tenant, $upcomingProperty, [
            'status' => 'upcoming',
            'started_at' => null,
            'last_payment_at' => null,
            'next_payment_due_at' => null,
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

        $this->assertSame(2, UserNotification::query()->count());
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
            ->assertDontSee('Upcoming rent due in 7 days')
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

    protected function createNotification(User $user, string $title, ?string $link): UserNotification
    {
        return UserNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => 'Notification body.',
            'category' => 'test_notification',
            'link' => $link,
        ]);
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
