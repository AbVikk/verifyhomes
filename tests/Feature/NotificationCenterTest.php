<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

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

    protected function createRoleUser(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
