<?php

namespace Tests\Feature;

use App\Livewire\Support\Create;
use App\Livewire\Support\ActiveDrawer;
use App\Livewire\Support\Show;
use App\Models\InspectionRequest;
use App\Models\MaintenanceRequest;
use App\Models\Occupancy;
use App\Models\OccupancyComplaint;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\SupportRequest;
use App\Models\SupportRequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_create_an_open_request_with_an_initial_message(): void
    {
        $tenant = $this->user('tenant');

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('category', 'payment')->set('subject', 'Payment question')->set('description', 'I need help with a payment receipt.')
            ->call('submit')
            ->assertRedirect(route('tenant.support.show', SupportRequest::firstOrFail()));

        $request = SupportRequest::firstOrFail();
        $this->assertSame($tenant->id, $request->user_id);
        $this->assertSame('open', $request->status);
        $this->assertSame('tenant', $request->role_snapshot);
        $this->assertMatchesRegularExpression('/^SUP-\d{8}-[A-Z0-9]{6}$/', $request->reference);
        $this->assertDatabaseHas('support_request_messages', ['support_request_id' => $request->id, 'user_id' => $tenant->id, 'body' => 'I need help with a payment receipt.']);
    }

    public function test_landlord_can_create_a_landlord_payout_request_with_a_landlord_payment(): void
    {
        $landlord = $this->user('landlord');
        $context = $this->contextFor($landlord, $this->user('tenant'));

        Livewire::actingAs($landlord)->test(Create::class)
            ->set('category', 'landlord_payout')->set('subject', 'Payout question')->set('description', 'Please help me understand this payout.')
            ->set('propertyId', $context['property']->id)->set('paymentTransactionId', $context['payment']->id)
            ->call('submit')->assertHasNoErrors()->assertRedirect(route('landlord.support.show', SupportRequest::firstOrFail()));

        $this->assertDatabaseHas('support_requests', ['user_id' => $landlord->id, 'role_snapshot' => 'landlord', 'category' => 'landlord_payout', 'property_id' => $context['property']->id, 'payment_transaction_id' => $context['payment']->id]);
    }

    public function test_tenant_cannot_submit_landlord_payout_category(): void
    {
        Livewire::actingAs($this->user('tenant'))->test(Create::class)
            ->set('category', 'landlord_payout')->set('subject', 'No access')->set('description', 'This category must not be available to tenants.')
            ->call('submit')->assertHasErrors('category');

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_owner_scoping_and_reply_lifecycle_work(): void
    {
        $tenant = $this->user('tenant');
        $other = $this->user('tenant');
        $request = SupportRequest::create(['user_id' => $tenant->id, 'role_snapshot' => 'tenant', 'category' => 'general', 'subject' => 'Help', 'description' => 'Original message', 'status' => 'resolved']);
        $request->messages()->create(['user_id' => $tenant->id, 'sender_type' => 'tenant', 'body' => 'Original message']);

        $this->actingAs($other)->get(route('tenant.support.show', $request))->assertNotFound();
        Livewire::actingAs($tenant)->test(Show::class, ['supportRequest' => $request])->set('reply', 'A follow-up reply')->call('reply');
        $this->assertSame('open', $request->fresh()->status);
        $this->assertDatabaseCount('support_request_messages', 2);

        $request->update(['status' => 'closed']);
        Livewire::actingAs($tenant)->test(Show::class, ['supportRequest' => $request])->set('reply', 'Blocked reply')->call('reply')->assertForbidden();
    }

    public function test_attachment_validation_uses_private_local_disk_and_owner_can_download_it(): void
    {
        Storage::fake('local');
        $tenant = $this->user('tenant');

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('subject', 'Evidence')->set('description', 'I am attaching supporting evidence for review.')
            ->set('attachment', UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf'))->call('submit');

        $attachment = SupportRequestAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->actingAs($tenant)->get(route('tenant.support.attachments.view', [$attachment->supportRequest, $attachment]))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('content-disposition', 'inline; filename=evidence.pdf');
        $this->actingAs($tenant)->get(route('tenant.support.attachments.download', [$attachment->supportRequest, $attachment]))->assertOk();

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('subject', 'Bad')->set('description', 'This unsupported attachment must be rejected safely.')
            ->set('attachment', UploadedFile::fake()->create('bad.exe', 20, 'application/octet-stream'))->call('submit')->assertHasErrors('attachment');
    }

    public function test_initial_uploaded_attachment_is_visible_to_authorized_operational_and_customer_views(): void
    {
        Storage::fake('local');
        $tenant = $this->user('tenant');
        $staff = $this->user('support_staff');
        $admin = $this->user('admin');

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('subject', 'Initial attachment visibility')
            ->set('description', 'This initial attachment must remain visible to authorized viewers.')
            ->set('attachment', UploadedFile::fake()->create('initial-evidence.pdf', 20, 'application/pdf'))
            ->call('submit')
            ->assertHasNoErrors();

        $request = SupportRequest::firstOrFail();
        $attachment = SupportRequestAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertSame($request->id, $attachment->support_request_id);
        $this->assertNotNull($attachment->support_request_message_id);

        $this->actingAs($staff)->get(route('support-team.requests.show', $request))->assertOk()->assertSee('initial-evidence.pdf');
        $this->actingAs($admin)->get(route('admin.support.show', $request))->assertOk()->assertSee('initial-evidence.pdf');
        $this->actingAs($tenant)->get(route('tenant.support.show', $request))->assertOk()->assertSee('initial-evidence.pdf');
        Livewire::actingAs($tenant)->test(ActiveDrawer::class)->assertSee('initial-evidence.pdf');
        $this->actingAs($tenant)->get(route('tenant.support.attachments.download', [$request, $attachment]))->assertOk();
    }

    public function test_active_drawer_only_contains_the_owners_open_in_progress_or_waiting_requests(): void
    {
        $tenant = $this->user('tenant');
        $otherTenant = $this->user('tenant');
        $open = SupportRequest::create(['user_id' => $tenant->id, 'role_snapshot' => 'tenant', 'category' => 'general', 'subject' => 'Open request', 'description' => 'Open request description.', 'status' => 'open']);
        $inProgress = SupportRequest::create(['user_id' => $tenant->id, 'role_snapshot' => 'tenant', 'category' => 'general', 'subject' => 'In progress request', 'description' => 'In progress description.', 'status' => 'in_progress']);
        SupportRequest::create(['user_id' => $tenant->id, 'role_snapshot' => 'tenant', 'category' => 'general', 'subject' => 'Resolved request', 'description' => 'Resolved description.', 'status' => 'resolved']);
        SupportRequest::create(['user_id' => $otherTenant->id, 'role_snapshot' => 'tenant', 'category' => 'general', 'subject' => 'Other tenant request', 'description' => 'Other tenant description.', 'status' => 'waiting_for_user']);

        Livewire::actingAs($tenant)->test(ActiveDrawer::class)
            ->assertSee('Open request')
            ->assertSee('In progress request')
            ->assertSee('Send reply')
            ->assertSee('Sending...')
            ->assertDontSee('Resolved request')
            ->assertDontSee('Other tenant request')
            ->call('selectRequest', $inProgress->id)
            ->assertSet('selectedRequestId', $inProgress->id)
            ->set('replyBody', 'Here is the information you requested.')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('replyBody', '');

        $this->assertDatabaseHas('support_request_messages', ['support_request_id' => $inProgress->id, 'body' => 'Here is the information you requested.', 'is_internal' => false]);
        $this->assertSame('in_progress', $inProgress->fresh()->status);
        $this->assertSame('open', $open->fresh()->status);
    }

    public function test_landlord_drawer_reply_is_scoped_to_the_selected_request(): void
    {
        $landlord = $this->user('landlord');
        $first = SupportRequest::create(['user_id' => $landlord->id, 'role_snapshot' => 'landlord', 'category' => 'general', 'subject' => 'First landlord request', 'description' => 'First landlord request description.', 'status' => 'open']);
        $second = SupportRequest::create(['user_id' => $landlord->id, 'role_snapshot' => 'landlord', 'category' => 'general', 'subject' => 'Second landlord request', 'description' => 'Second landlord request description.', 'status' => 'waiting_for_user']);

        Livewire::actingAs($landlord)->test(ActiveDrawer::class)
            ->call('selectRequest', $second->id)
            ->set('replyBody', 'This reply belongs to the second request.')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('replyBody', '');

        $this->assertDatabaseHas('support_request_messages', ['support_request_id' => $second->id, 'body' => 'This reply belongs to the second request.', 'is_internal' => false]);
        $this->assertDatabaseMissing('support_request_messages', ['support_request_id' => $first->id, 'body' => 'This reply belongs to the second request.']);
        $this->assertSame('open', $second->fresh()->status);
    }

    public function test_valid_submission_with_a_temporary_upload_persists_once_and_redirects_cleanly(): void
    {
        Storage::fake('local');
        $tenant = $this->user('tenant');

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('subject', 'Photo evidence')->set('description', 'I am attaching a PNG image as supporting evidence.')
            ->set('attachment', UploadedFile::fake()->image('evidence.png'))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('tenant.support.show', SupportRequest::firstOrFail()));

        $request = SupportRequest::firstOrFail();
        $this->assertDatabaseCount('support_requests', 1);
        $this->assertDatabaseCount('support_request_messages', 1);
        $attachment = SupportRequestAttachment::firstOrFail();
        $this->assertSame($request->messages()->firstOrFail()->id, $attachment->support_request_message_id);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_validation_errors_clear_after_a_valid_resubmission_without_creating_a_duplicate(): void
    {
        $tenant = $this->user('tenant');
        $component = Livewire::actingAs($tenant)->test(Create::class)
            ->set('subject', '')->set('description', 'Too short')
            ->call('submit')
            ->assertHasErrors(['subject', 'description']);

        $component->set('subject', 'Valid follow-up')->set('description', 'This valid support request clears the earlier validation state.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('tenant.support.show', SupportRequest::firstOrFail()));

        $this->assertDatabaseCount('support_requests', 1);
    }

    public function test_notification_failure_does_not_rollback_a_successful_support_request(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mail transport unavailable.'));
        $tenant = $this->user('tenant');

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('subject', 'Notification-safe request')->set('description', 'This request must remain saved when notification mail cannot be delivered.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('support_requests', 1);
        $this->assertDatabaseCount('support_request_messages', 1);
    }

    public function test_attachment_download_requires_the_request_owner_and_matching_parent_request(): void
    {
        Storage::fake('local');
        $tenant = $this->user('tenant');
        $otherTenant = $this->user('tenant');
        $landlord = $this->user('landlord');
        $request = $this->requestWithAttachment($tenant);
        $otherRequest = $this->requestWithAttachment($tenant);
        $attachment = $request->attachments()->firstOrFail();

        $this->actingAs($otherTenant)->get(route('tenant.support.attachments.download', [$request, $attachment]))->assertNotFound();
        $this->actingAs($landlord)->get(route('landlord.support.attachments.download', [$request, $attachment]))->assertNotFound();
        $this->actingAs($tenant)->get(route('tenant.support.attachments.download', [$otherRequest, $attachment]))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get(route('tenant.support.attachments.download', [$request, $attachment]))->assertRedirect(route('login'));

        Storage::disk('local')->delete($attachment->file_path);
        $this->actingAs($tenant)->get(route('tenant.support.attachments.view', [$request, $attachment]))->assertNotFound();
        $this->actingAs($tenant)->get(route('tenant.support.attachments.download', [$request, $attachment]))->assertNotFound();
    }

    public function test_landlord_owner_can_view_and_download_a_public_attachment(): void
    {
        Storage::fake('local');
        $landlord = $this->user('landlord');
        $request = $this->requestWithAttachment($landlord);
        $attachment = $request->attachments()->firstOrFail();

        $this->actingAs($landlord)->get(route('landlord.support.attachments.view', [$request, $attachment]))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('content-disposition', 'inline; filename=private.pdf');
        $this->actingAs($landlord)->get(route('landlord.support.attachments.download', [$request, $attachment]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=private.pdf');
    }

    public function test_tenant_contexts_are_owner_scoped_on_the_server_and_valid_contexts_are_stored(): void
    {
        $tenant = $this->user('tenant');
        $otherTenant = $this->user('tenant');
        $own = $this->contextFor($this->user('landlord'), $tenant);
        $foreign = $this->contextFor($this->user('landlord'), $otherTenant);

        foreach (['propertyId' => $foreign['property']->id, 'inspectionRequestId' => $foreign['inspection']->id, 'paymentTransactionId' => $foreign['payment']->id, 'maintenanceRequestId' => $foreign['maintenance']->id, 'occupancyComplaintId' => $foreign['complaint']->id] as $field => $id) {
            Livewire::actingAs($tenant)->test(Create::class)
                ->set('category', 'general')->set('subject', 'Foreign context')->set('description', 'This submitted context must be rejected safely.')
                ->set($field, $id)->call('submit')->assertHasErrors($field);
        }

        Livewire::actingAs($tenant)->test(Create::class)
            ->set('category', 'general')->set('subject', 'Connected support')->set('description', 'All of my linked activity needs support review.')
            ->set('propertyId', $own['property']->id)->set('inspectionRequestId', $own['inspection']->id)->set('paymentTransactionId', $own['payment']->id)
            ->set('maintenanceRequestId', $own['maintenance']->id)->set('occupancyComplaintId', $own['complaint']->id)
            ->call('submit')->assertHasNoErrors();

        $this->assertDatabaseHas('support_requests', ['user_id' => $tenant->id, 'property_id' => $own['property']->id, 'inspection_request_id' => $own['inspection']->id, 'payment_transaction_id' => $own['payment']->id, 'maintenance_request_id' => $own['maintenance']->id, 'occupancy_complaint_id' => $own['complaint']->id]);
    }

    public function test_landlord_contexts_are_owner_scoped_on_the_server_and_valid_contexts_are_stored(): void
    {
        $landlord = $this->user('landlord');
        $otherLandlord = $this->user('landlord');
        $tenant = $this->user('tenant');
        $own = $this->contextFor($landlord, $tenant);
        $foreign = $this->contextFor($otherLandlord, $this->user('tenant'));

        foreach (['propertyId' => $foreign['property']->id, 'inspectionRequestId' => $foreign['inspection']->id, 'paymentTransactionId' => $foreign['payment']->id, 'maintenanceRequestId' => $foreign['maintenance']->id, 'occupancyComplaintId' => $foreign['complaint']->id] as $field => $id) {
            Livewire::actingAs($landlord)->test(Create::class)
                ->set('category', 'general')->set('subject', 'Foreign context')->set('description', 'This submitted context must be rejected safely.')
                ->set($field, $id)->call('submit')->assertHasErrors($field);
        }

        Livewire::actingAs($landlord)->test(Create::class)
            ->set('category', 'general')->set('subject', 'Owned support')->set('description', 'All linked landlord activity needs support review.')
            ->set('propertyId', $own['property']->id)->set('inspectionRequestId', $own['inspection']->id)->set('paymentTransactionId', $own['payment']->id)
            ->set('maintenanceRequestId', $own['maintenance']->id)->set('occupancyComplaintId', $own['complaint']->id)
            ->call('submit')->assertHasNoErrors();

        $this->assertDatabaseHas('support_requests', ['user_id' => $landlord->id, 'property_id' => $own['property']->id, 'inspection_request_id' => $own['inspection']->id, 'payment_transaction_id' => $own['payment']->id, 'maintenance_request_id' => $own['maintenance']->id, 'occupancy_complaint_id' => $own['complaint']->id]);
    }

    /** @return array{property: Property, inspection: InspectionRequest, payment: PaymentTransaction, maintenance: MaintenanceRequest, complaint: OccupancyComplaint} */
    private function contextFor(User $landlord, User $tenant): array
    {
        $property = Property::create(['landlord_id' => $landlord->id, 'title' => 'Support Home '.fake()->uuid(), 'property_type' => 'flat', 'rent_amount' => 390000, 'lga' => 'Akure South', 'area' => 'Alagbaka']);
        $inspection = InspectionRequest::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'status' => 'requested']);
        $payment = PaymentTransaction::create(['reference' => 'SUP-PAY-'.fake()->unique()->numerify('######'), 'payer_id' => $tenant->id, 'property_id' => $property->id, 'transaction_type' => 'rent_payment', 'currency' => 'NGN', 'status' => 'paid', 'gross_amount' => 390000, 'platform_fee_percentage' => 20, 'platform_fee_amount' => 78000, 'net_amount' => 312000, 'paid_at' => now()]);
        $occupancy = Occupancy::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'payment_transaction_id' => $payment->id, 'status' => 'active']);
        $maintenance = MaintenanceRequest::create(['occupancy_id' => $occupancy->id, 'property_id' => $property->id, 'tenant_id' => $tenant->id, 'landlord_id' => $landlord->id, 'category' => 'plumbing', 'title' => 'Leaking tap', 'description' => 'The kitchen tap needs repair.', 'status' => 'submitted']);
        $complaint = OccupancyComplaint::create(['occupancy_id' => $occupancy->id, 'maintenance_request_id' => $maintenance->id, 'tenant_id' => $tenant->id, 'category' => 'maintenance', 'description' => 'The issue needs escalation.', 'status' => 'open']);

        return compact('property', 'inspection', 'payment', 'maintenance', 'complaint');
    }

    private function requestWithAttachment(User $user): SupportRequest
    {
        $request = SupportRequest::create(['user_id' => $user->id, 'role_snapshot' => $user->isLandlord() ? 'landlord' : 'tenant', 'category' => 'general', 'subject' => 'Attachment', 'description' => 'Attachment request', 'status' => 'open']);
        $message = $request->messages()->create(['user_id' => $user->id, 'sender_type' => $request->role_snapshot, 'body' => 'Attachment request']);
        $path = 'support-requests/'.$request->id.'/private.pdf';
        Storage::disk('local')->put($path, 'private attachment');
        $request->attachments()->create(['support_request_message_id' => $message->id, 'uploaded_by' => $user->id, 'original_name' => 'private.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 18]);

        return $request;
    }

    private function user(string $role): User
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }
}
