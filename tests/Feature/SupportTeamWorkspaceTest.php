<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\SupportRequestEvent;
use App\Models\SupportRequestAttachment;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTeamWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); foreach (['admin', 'tenant', 'landlord', 'support_staff'] as $role) Role::findOrCreate($role, 'web'); }

    public function test_admin_navigation_and_support_queue_are_available(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Support Requests')->assertSee('Support Team');
        $this->actingAs($admin)->get(route('admin.support.index'))->assertOk();
    }

    public function test_active_support_staff_can_read_the_dashboard_queue_detail_and_private_attachment(): void
    {
        Storage::fake('local');
        $staff = $this->user('support_staff');
        $request = $this->request($this->user('tenant'));
        $path = 'support-requests/'.$request->id.'/evidence.pdf';
        Storage::disk('local')->put($path, 'private');
        $attachment = $request->attachments()->create(['uploaded_by' => $request->user_id, 'original_name' => 'evidence.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 7]);
        $this->actingAs($staff)->get(route('support-team.dashboard'))->assertOk()->assertSee('Open requests');
        $this->actingAs($staff)->get(route('support-team.requests.index'))->assertOk()->assertSee($request->reference);
        $this->actingAs($staff)->get(route('support-team.requests.show', $request))->assertOk()->assertSee($request->subject)->assertSee('evidence.pdf')->assertSee('Download');
        $this->actingAs($staff)->get(route('support-team.requests.attachments.view', [$request, $attachment]))->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->actingAs($staff)->get(route('support-team.requests.attachments.download', [$request, $attachment]))->assertOk();

        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.support.show', $request))->assertOk()->assertSee('evidence.pdf')->assertSee('Download');
        $this->actingAs($admin)->get(route('admin.support.attachments.view', [$request, $attachment]))->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->actingAs($admin)->get(route('admin.support.attachments.download', [$request, $attachment]))->assertOk();
    }

    public function test_customers_and_suspended_staff_cannot_access_support_team_operations(): void
    {
        $this->actingAs($this->user('tenant'))->get(route('support-team.requests.index'))->assertForbidden();
        $this->actingAs($this->user('landlord'))->get(route('support-team.requests.index'))->assertForbidden();
        $suspended = $this->user('support_staff', ['suspended_at' => now()]);
        $this->actingAs($suspended)->get(route('support-team.requests.index'))->assertRedirect(route('support-team.login'));
        $this->actingAs($this->user('support_staff'))->get(route('admin.settlements.index'))->assertForbidden();
    }

    public function test_admin_can_assign_reassign_and_unassign_only_active_support_staff(): void
    {
        $admin = $this->user('admin');
        $first = $this->user('support_staff');
        $second = $this->user('support_staff');
        $suspended = $this->user('support_staff', ['suspended_at' => now()]);
        $request = $this->request($this->user('tenant'));

        $this->actingAs($admin)->post(route('admin.support.assignment', $request), ['assigned_to' => $suspended->id])->assertSessionHasErrors('assigned_to');
        $this->actingAs($admin)->post(route('admin.support.assignment', $request), ['assigned_to' => $first->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_requests', ['id' => $request->id, 'assigned_to' => $first->id]);
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'assigned']);

        $this->actingAs($admin)->post(route('admin.support.assignment', $request), ['assigned_to' => $second->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'reassigned']);
        $this->actingAs($admin)->post(route('admin.support.assignment', $request), [])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_requests', ['id' => $request->id, 'assigned_to' => null]);
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'unassigned']);
    }

    public function test_staff_can_claim_once_but_cannot_overwrite_another_claim_or_assign_others(): void
    {
        $first = $this->user('support_staff');
        $second = $this->user('support_staff');
        $request = $this->request($this->user('tenant'));

        $this->actingAs($first)->post(route('support-team.requests.claim', $request))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_requests', ['id' => $request->id, 'assigned_to' => $first->id]);
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'claimed']);
        $this->actingAs($second)->post(route('support-team.requests.claim', $request))->assertSessionHasErrors('assignment');
        $this->assertSame($first->id, $request->fresh()->assigned_to);
        $this->actingAs($second)->post(route('admin.support.assignment', $request), ['assigned_to' => $second->id])->assertForbidden();
    }

    public function test_priority_and_status_permissions_preserve_operational_history_and_timestamps(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('support_staff');
        $other = $this->user('support_staff');
        $request = $this->request($this->user('tenant'));
        $this->assertSame('normal', $request->priority);

        $this->actingAs($admin)->post(route('admin.support.assignment', $request), ['assigned_to' => $owner->id]);
        $this->actingAs($owner)->post(route('support-team.requests.priority', $request), ['priority' => 'high'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_requests', ['id' => $request->id, 'priority' => 'high']);
        $this->actingAs($other)->post(route('support-team.requests.priority', $request), ['priority' => 'urgent'])->assertForbidden();

        $this->actingAs($owner)->post(route('support-team.requests.status', $request), ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('support-team.requests.status', $request), ['status' => 'resolved'])->assertSessionHasNoErrors();
        $this->assertNotNull($request->fresh()->resolved_at);
        $this->actingAs($owner)->post(route('support-team.requests.status', $request), ['status' => 'open'])->assertSessionHasNoErrors();
        $this->assertNull($request->fresh()->resolved_at);
        $this->actingAs($owner)->post(route('support-team.requests.status', $request), ['status' => 'closed'])->assertSessionHasErrors('status');

        $this->actingAs($admin)->post(route('admin.support.status', $request), ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertNotNull($request->fresh()->closed_at);
        $this->actingAs($owner)->post(route('support-team.requests.status', $request), ['status' => 'open'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.support.status', $request), ['status' => 'open'])->assertSessionHasNoErrors();
        $this->assertNull($request->fresh()->closed_at);
        $this->assertGreaterThanOrEqual(5, SupportRequestEvent::query()->where('support_request_id', $request->id)->count());
    }

    public function test_customers_cannot_see_internal_assignment_priority_or_activity(): void
    {
        $tenant = $this->user('tenant');
        $staff = $this->user('support_staff');
        $request = $this->request($tenant);
        $request->update(['assigned_to' => $staff->id, 'assigned_at' => now(), 'priority' => 'urgent']);
        $request->events()->create(['actor_id' => $staff->id, 'event_type' => 'assigned', 'new_value' => $staff->id]);

        $this->actingAs($tenant)->get(route('tenant.support.show', $request))
            ->assertOk()
            ->assertDontSee('Operational activity')
            ->assertDontSee('Assigned to:')
            ->assertDontSee('Urgent');
    }

    public function test_assigned_staff_and_admin_can_send_public_replies_but_other_staff_cannot(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('support_staff');
        $other = $this->user('support_staff');
        $tenant = $this->user('tenant');
        $request = $this->request($tenant);

        $this->actingAs($owner)->post(route('support-team.requests.reply', $request), ['body' => 'Not claimed yet.'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.support.assignment', $request), ['assigned_to' => $owner->id]);
        $this->actingAs($other)->post(route('support-team.requests.reply', $request), ['body' => 'Other staff reply.'])->assertForbidden();
        $this->actingAs($owner)->post(route('support-team.requests.reply', $request), ['body' => 'We are reviewing your request.', 'status' => 'waiting_for_user'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_request_messages', ['support_request_id' => $request->id, 'sender_type' => 'support', 'body' => 'We are reviewing your request.', 'is_internal' => false]);
        $this->assertSame('waiting_for_user', $request->fresh()->status);
        $this->actingAs($tenant)->get(route('tenant.support.show', $request))->assertOk()->assertSee('We are reviewing your request.')->assertSee('VerifyHomes Support');
        $this->actingAs($admin)->post(route('admin.support.reply', $request), ['body' => 'Admin update.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'public_reply_sent']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $tenant->id, 'title' => 'Support replied to '.$request->reference]);
    }

    public function test_internal_notes_are_operational_only_and_internal_attachments_are_not_customer_accessible(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin');
        $staff = $this->user('support_staff');
        $tenant = $this->user('tenant');
        $request = $this->request($tenant);
        $this->actingAs($admin)->post(route('admin.support.assignment', $request), ['assigned_to' => $staff->id]);
        $this->actingAs($staff)->post(route('support-team.requests.notes', $request), ['body' => 'Internal KYC follow-up required.'])->assertSessionHasNoErrors();
        $note = $request->messages()->where('is_internal', true)->firstOrFail();
        $path = 'support-requests/'.$request->id.'/internal.pdf';
        Storage::disk('local')->put($path, 'private');
        $attachment = SupportRequestAttachment::create(['support_request_id' => $request->id, 'support_request_message_id' => $note->id, 'uploaded_by' => $staff->id, 'original_name' => 'internal.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 7]);

        $this->actingAs($tenant)->get(route('tenant.support.show', $request))->assertOk()->assertDontSee('Internal KYC follow-up required');
        $this->actingAs($tenant)->get(route('tenant.support.attachments.download', [$request, $attachment]))->assertNotFound();
        $this->actingAs($staff)->get(route('support-team.requests.show', $request))->assertOk()->assertSee('Internal KYC follow-up required');
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'internal_note_added']);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $tenant->id, 'title' => 'Internal note']);
    }

    public function test_customer_reply_reopens_waiting_and_resolved_requests_and_notifies_the_assignee(): void
    {
        $staff = $this->user('support_staff');
        $tenant = $this->user('tenant');
        $request = $this->request($tenant);
        $request->update(['assigned_to' => $staff->id, 'assigned_at' => now(), 'status' => 'waiting_for_user']);

        \Livewire\Livewire::actingAs($tenant)->test(\App\Livewire\Support\Show::class, ['supportRequest' => $request])->set('reply', 'Here is the requested information.')->call('reply');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $staff->id, 'title' => 'New reply on '.$request->reference]);

        $request->update(['status' => 'resolved', 'resolved_at' => now()]);
        \Livewire\Livewire::actingAs($tenant)->test(\App\Livewire\Support\Show::class, ['supportRequest' => $request])->set('reply', 'I still need help.')->call('reply');
        $this->assertSame('open', $request->fresh()->status);
    }

    public function test_staff_can_escalate_only_their_request_and_admin_can_clear_without_erasing_history(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('support_staff');
        $other = $this->user('support_staff');
        $tenant = $this->user('tenant');
        $request = $this->request($tenant);
        $request->update(['assigned_to' => $owner->id, 'assigned_at' => now()]);

        $this->actingAs($other)->post(route('support-team.requests.escalate', $request), ['category' => 'financial_action', 'note' => 'Needs admin payment action.'])->assertForbidden();
        $this->actingAs($owner)->post(route('support-team.requests.escalate', $request), ['category' => 'financial_action', 'note' => 'Needs admin payment action.'])->assertSessionHasNoErrors();
        $this->assertNotNull($request->fresh()->escalated_at);
        $this->actingAs($tenant)->get(route('tenant.support.show', $request))->assertOk()->assertDontSee('Needs admin payment action.')->assertDontSee('Escalated to Admin');
        $this->actingAs($admin)->get(route('admin.support.index', ['assignment' => 'escalated']))->assertOk()->assertSee($request->reference)->assertSee('Escalated');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'title' => 'Support request escalated']);
        $this->actingAs($admin)->post(route('admin.support.clear-escalation', $request))->assertSessionHasNoErrors();
        $this->assertNull($request->fresh()->escalated_at);
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'escalated_to_admin']);
        $this->assertDatabaseHas('support_request_events', ['support_request_id' => $request->id, 'event_type' => 'escalation_cleared']);
    }

    public function test_resolved_and_closed_statuses_notify_the_customer(): void
    {
        $admin = $this->user('admin');
        $tenant = $this->user('tenant');
        $request = $this->request($tenant);
        $this->actingAs($admin)->post(route('admin.support.status', $request), ['status' => 'resolved'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $tenant->id, 'title' => 'Support request Resolved']);
        $this->actingAs($admin)->post(route('admin.support.status', $request), ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $tenant->id, 'title' => 'Support request Closed']);
    }

    private function request(User $user): SupportRequest { return SupportRequest::create(['user_id' => $user->id, 'role_snapshot' => 'tenant', 'category' => 'general', 'subject' => 'Need help', 'description' => 'I need support with my account.', 'status' => 'open']); }
    private function user(string $role, array $attributes = []): User { $user = User::factory()->create(array_merge(['email_verified_at' => now(), 'must_change_password' => false], $attributes)); $user->assignRole($role); return $user; }
}
