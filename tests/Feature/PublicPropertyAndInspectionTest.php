<?php

namespace Tests\Feature;

use App\Livewire\Admin\InspectionRequests\Index as AdminInspectionRequestIndex;
use App\Livewire\Admin\InspectionRequests\Show as AdminInspectionRequestShow;
use App\Livewire\Tenant\InspectionRequests\Index as TenantInspectionRequestIndex;
use App\Models\InspectionRequest;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicPropertyAndInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_access_public_property_index(): void
    {
        $property = $this->createPublicProperty();
        $pendingProperty = $this->createProperty(title: 'Pending Listing', status: 'pending_review', isVerified: false);

        $response = $this->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee($property->title);
        $response->assertDontSee($pendingProperty->title);
    }

    public function test_tenant_property_browse_page_renders_in_tenant_shell(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $response = $this->actingAs($tenant)->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee($property->title);
        $response->assertSee('data-admin-shell-key="tenant"', false);
        $response->assertSee('Workspace Menu');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_guests_can_access_approved_property_detail(): void
    {
        $property = $this->createPublicProperty();

        $response = $this->get(route('properties.show', $property));

        $response->assertOk();
        $response->assertSee($property->title);
    }

    public function test_tenant_property_detail_page_renders_in_tenant_shell(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk();
        $response->assertSee($property->title);
        $response->assertSee('data-admin-shell-key="tenant"', false);
        $response->assertSee('Workspace Menu');
        $response->assertSee('View terms');
        $response->assertSee('admin-button admin-button-primary', false);
        $response->assertSee('VerifyHomes will handle scheduling.');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_tenant_property_detail_renders_request_unavailable_state_when_inspection_requests_table_is_missing(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk();
        $response->assertSee('Inspection requests are not available yet.');
    }

    public function test_unapproved_or_unverified_properties_are_not_publicly_accessible(): void
    {
        $pendingProperty = $this->createProperty(status: 'pending_review', isVerified: false);
        $unverifiedApprovedProperty = $this->createProperty(status: PublicPropertyVisibility::APPROVED_STATUS, isVerified: false);
        $unpublishedApprovedProperty = $this->createProperty(title: 'Approved But Unpublished', status: PublicPropertyVisibility::APPROVED_STATUS, isVerified: true, isPublished: false);

        $this->get(route('properties.show', $pendingProperty))->assertNotFound();
        $this->get(route('properties.show', $unverifiedApprovedProperty))->assertNotFound();
        $this->get(route('properties.show', $unpublishedApprovedProperty))->assertNotFound();
    }

    public function test_public_visibility_rule_matches_scope_and_model_helper(): void
    {
        $visibleProperty = $this->createPublicProperty();
        $hiddenProperty = $this->createProperty(status: PublicPropertyVisibility::APPROVED_STATUS, isVerified: true, isPublished: false);

        $visibleIds = Property::query()->publiclyVisible()->pluck('id');

        $this->assertTrue($visibleIds->contains($visibleProperty->id));
        $this->assertFalse($visibleIds->contains($hiddenProperty->id));
        $this->assertTrue($visibleProperty->isPubliclyVisible());
        $this->assertFalse($hiddenProperty->isPubliclyVisible());
    }

    public function test_approved_verified_unpublished_property_is_not_visible_publicly(): void
    {
        $property = $this->createProperty(status: PublicPropertyVisibility::APPROVED_STATUS, isVerified: true, isPublished: false);

        $this->get(route('properties.index'))->assertDontSee($property->title);
        $this->get(route('properties.show', $property))->assertNotFound();
    }

    public function test_approved_verified_published_property_is_visible_publicly(): void
    {
        $property = $this->createProperty(status: PublicPropertyVisibility::APPROVED_STATUS, isVerified: true, isPublished: true);

        $this->get(route('properties.index'))->assertSee($property->title);
        $this->get(route('properties.show', $property))->assertOk()->assertSee($property->title);
    }

    public function test_tenant_can_submit_an_inspection_request(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();
        $gate = 'inspection-request:property:'.$property->id;

        $response = $this->actingAs($tenant)
            ->withSession($this->completedTermsGateSession($gate))
            ->post(route('inspection-requests.store', $property), [
                'accepted_inspection_terms' => '1',
                'preferred_date' => now()->addDays(3)->toDateString(),
                'preferred_time_note' => 'After 4pm',
                'message' => 'I would like to inspect this on a weekday.',
            ]);

        $response->assertRedirect(route('properties.show', $property));
        $this->assertDatabaseHas('inspection_requests', [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
        ]);
        $this->assertDatabaseHas('inspection_request_status_histories', [
            'to_status' => 'requested',
            'changed_by' => null,
        ]);
    }

    public function test_guest_cannot_submit_an_inspection_request(): void
    {
        $property = $this->createPublicProperty();

        $response = $this->post(route('inspection-requests.store', $property), [
            'preferred_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('inspection_requests', 0);
    }

    public function test_landlord_cannot_submit_tenant_inspection_request(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createPublicProperty();

        $response = $this->actingAs($landlord)->post(route('inspection-requests.store', $property), [
            'preferred_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('inspection_requests', 0);
    }

    public function test_admin_staff_can_access_inspection_request_index_and_detail(): void
    {
        $admin = $this->createReviewer('admin');
        $staff = $this->createReviewer('staff');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin)->get(route('admin.inspection-requests.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.inspection-requests.show', $inspectionRequest))->assertOk();
        $this->actingAs($staff)->get(route('admin.inspection-requests.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.inspection-requests.show', $inspectionRequest))->assertOk();
    }

    public function test_admin_and_tenant_inspection_surfaces_use_platform_led_lifecycle_language(): void
    {
        $admin = $this->createReviewer('admin');
        $tenant = $this->createTenant('platform-led-tenant@example.com');
        $inspectionRequest = $this->createInspectionRequest($tenant);

        $tenantResponse = $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', $inspectionRequest));

        $tenantResponse->assertOk();
        $tenantResponse->assertSee('Waiting for scheduling');
        $tenantResponse->assertSee('Waiting for scheduling');
        $tenantResponse->assertSee('Not started');
        $tenantResponse->assertSee('We are scheduling your visit.');
        $tenantResponse->assertSee('View terms');
        $tenantResponse->assertSee('admin-button admin-button-primary', false);

        $adminResponse = $this->actingAs($admin)->get(route('admin.inspection-requests.show', $inspectionRequest));

        $adminResponse->assertOk();
        $adminResponse->assertSee('Inspection control center');
        $adminResponse->assertSee('Manage the inspection request here.');
        $adminResponse->assertSee('Payment readiness');
        $adminResponse->assertSee('Scheduling responsibility');
        $adminResponse->assertSee('Landlord coordination');
        $adminResponse->assertSee('Outcome status');
    }

    public function test_admin_inspection_request_index_renders_unavailable_state_when_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($admin)->get(route('admin.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Inspection control center');
        $response->assertSee('Inspection request data is not available yet in this environment.');
    }

    public function test_admin_inspection_request_index_search_and_status_filter_can_be_combined(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingTenant = $this->createTenant('match-tenant@example.com');
        $matchingTenant->update([
            'name' => 'Search Match Tenant',
        ]);
        $matchingProperty = $this->createProperty(
            title: 'Search Match Property',
            status: PublicPropertyVisibility::APPROVED_STATUS,
            isVerified: true,
            isPublished: true,
        );

        $matchingRequest = InspectionRequest::create([
            'property_id' => $matchingProperty->id,
            'tenant_id' => $matchingTenant->id,
            'status' => 'scheduled',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'preferred_time_note' => 'Late morning',
            'message' => 'Please confirm access.',
            'scheduled_at' => now()->addDays(4),
        ]);

        $matchingRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'scheduled',
            'changed_by' => $admin->id,
            'notes' => 'Scheduled for follow-up.',
        ]);

        $otherRequest = $this->createInspectionRequest();

        $response = $this->actingAs($admin)->get(route('admin.inspection-requests.index', [
            'search' => 'Search Match',
            'statusFilter' => 'scheduled',
        ]));

        $response->assertOk();
        $response->assertSee($matchingProperty->title);
        $response->assertSee($matchingTenant->name);
        $response->assertDontSee($otherRequest->property->title);
    }

    public function test_admin_can_bulk_reject_selected_inspection_requests(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingRequest = $this->createInspectionRequest();
        $matchingRequest->update([
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Visited successfully.',
        ]);

        $otherRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestIndex::class)
            ->set('selectedInspectionRequestIds', [$matchingRequest->id])
            ->call('bulkReject');

        $matchingRequest->refresh();
        $otherRequest->refresh();

        $this->assertSame('rejected', $matchingRequest->status);
        $this->assertNull($matchingRequest->outcome_type);
        $this->assertNull($matchingRequest->outcome_notes);
        $this->assertDatabaseHas('inspection_request_status_histories', [
            'inspection_request_id' => $matchingRequest->id,
            'from_status' => 'completed',
            'to_status' => 'rejected',
            'changed_by' => $admin->id,
            'notes' => 'Bulk action from inspection request queue.',
        ]);

        $this->assertSame('requested', $otherRequest->status);
    }

    public function test_admin_can_bulk_cancel_selected_inspection_requests(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingRequest = $this->createInspectionRequest();
        $otherRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestIndex::class)
            ->set('selectedInspectionRequestIds', [$matchingRequest->id])
            ->call('bulkCancel');

        $matchingRequest->refresh();
        $otherRequest->refresh();

        $this->assertSame('cancelled', $matchingRequest->status);
        $this->assertDatabaseHas('inspection_request_status_histories', [
            'inspection_request_id' => $matchingRequest->id,
            'from_status' => 'requested',
            'to_status' => 'cancelled',
            'changed_by' => $admin->id,
            'notes' => 'Bulk action from inspection request queue.',
        ]);

        $this->assertSame('requested', $otherRequest->status);
    }

    public function test_bulk_inspection_status_change_skips_no_op_history_rows(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingRequest = $this->createInspectionRequest();
        $matchingRequest->update([
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestIndex::class)
            ->set('selectedInspectionRequestIds', [$matchingRequest->id])
            ->call('bulkCancel');

        $matchingRequest->refresh();

        $this->assertSame('cancelled', $matchingRequest->status);
        $this->assertDatabaseCount('inspection_request_status_histories', 1);
    }

    public function test_admin_inspection_request_index_explains_bulk_schedule_and_complete_require_per_request_input(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $response = $this->actingAs($admin)->get(route('admin.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Schedule');
        $response->assertSee('Complete');
        $response->assertSee('Schedule and outcome updates happen on each request page.');
        $response->assertSee($inspectionRequest->property->title);
    }

    public function test_admin_inspection_request_detail_renders_unavailable_state_when_inspection_requests_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($admin)->get(route('admin.inspection-requests.show', ['inspectionRequestId' => 1]));

        $response->assertOk();
        $response->assertSee('Inspection request detail data is not available yet in this environment.');
    }

    public function test_admin_inspection_request_detail_renders_unavailable_state_when_inspection_request_status_histories_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        Schema::dropIfExists('inspection_request_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]));

        $response->assertOk();
        $response->assertSee('Inspection request detail data is not available yet in this environment.');
    }

    public function test_admin_change_status_fails_safely_when_inspection_requests_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('inspection_requests');

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequestId' => 1])
            ->call('changeStatus', 'scheduled')
            ->assertSee('Inspection request actions are not available yet in this environment.');
    }

    public function test_admin_change_status_fails_safely_when_inspection_request_status_histories_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        Schema::dropIfExists('inspection_request_status_histories');

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->call('changeStatus', 'scheduled')
            ->assertSee('Inspection request actions are not available yet in this environment.');
    }

    public function test_admin_save_coordination_notes_fails_safely_when_inspection_tables_are_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequestId' => 1])
            ->set('adminNotes', 'This should not persist.')
            ->call('saveCoordinationNotes')
            ->assertSee('Inspection request actions are not available yet in this environment.');
    }

    public function test_admin_can_navigate_into_inspection_section_when_tables_are_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee(route('admin.inspection-requests.index'));

        $indexResponse = $this->actingAs($admin)->get(route('admin.inspection-requests.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Inspection request data is not available yet in this environment.');

        $detailResponse = $this->actingAs($admin)->get(route('admin.inspection-requests.show', ['inspectionRequestId' => 999]));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Inspection request detail data is not available yet in this environment.');
    }

    public function test_admin_can_change_inspection_request_status(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('scheduledAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('adminNotes', 'Confirmed with tenant for tomorrow afternoon.')
            ->call('changeStatus', 'scheduled');

        $inspectionRequest->refresh();

        $this->assertSame('scheduled', $inspectionRequest->status);
        $this->assertNotNull($inspectionRequest->scheduled_at);
        $this->assertDatabaseHas('inspection_request_status_histories', [
            'inspection_request_id' => $inspectionRequest->id,
            'from_status' => 'requested',
            'to_status' => 'scheduled',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_admin_can_set_outcome_type_and_notes_on_completed_inspection_request(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('outcomeType', 'follow_up_needed')
            ->set('outcomeNotes', 'Tenant asked for a second visit after confirming budget.')
            ->call('changeStatus', 'completed');

        $inspectionRequest->refresh();

        $this->assertSame('completed', $inspectionRequest->status);
        $this->assertSame('follow_up_needed', $inspectionRequest->outcome_type);
        $this->assertSame('Tenant asked for a second visit after confirming budget.', $inspectionRequest->outcome_notes);
    }

    public function test_completed_inspection_request_requires_outcome_type(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('outcomeType', null)
            ->set('outcomeNotes', 'Visited the property and wrapped up.')
            ->call('changeStatus', 'completed')
            ->assertHasErrors(['outcomeType']);
    }

    public function test_changing_completed_request_back_to_scheduled_clears_outcome_type_and_outcome_notes(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $inspectionRequest->update([
            'status' => 'completed',
            'outcome_type' => 'inspected',
            'outcome_notes' => 'Inspection was completed successfully.',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('scheduledAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('changeStatus', 'scheduled');

        $inspectionRequest->refresh();

        $this->assertSame('scheduled', $inspectionRequest->status);
        $this->assertNull($inspectionRequest->outcome_type);
        $this->assertNull($inspectionRequest->outcome_notes);
    }

    public function test_save_coordination_notes_does_not_persist_outcome_type_or_outcome_notes_when_request_is_not_completed(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('adminNotes', 'Waiting on schedule confirmation.')
            ->set('outcomeType', 'follow_up_needed')
            ->set('outcomeNotes', 'This should not stick before completion.')
            ->call('saveCoordinationNotes');

        $inspectionRequest->refresh();

        $this->assertSame('Waiting on schedule confirmation.', $inspectionRequest->admin_notes);
        $this->assertNull($inspectionRequest->outcome_type);
        $this->assertNull($inspectionRequest->outcome_notes);
    }

    public function test_invalid_outcome_type_is_rejected(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('outcomeType', 'not_real')
            ->call('changeStatus', 'completed')
            ->assertHasErrors(['outcomeType']);
    }

    public function test_scheduling_without_scheduled_at_is_rejected(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('scheduledAt', null)
            ->call('changeStatus', 'scheduled')
            ->assertHasErrors(['scheduledAt']);
    }

    public function test_scheduling_an_inspection_in_the_past_is_rejected(): void
    {
        $admin = $this->createReviewer('admin');
        $inspectionRequest = $this->createInspectionRequest();

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('scheduledAt', now()->subHour()->format('Y-m-d\TH:i'))
            ->call('changeStatus', 'scheduled')
            ->assertHasErrors(['scheduledAt']);
    }

    public function test_tenant_dashboard_shows_inspection_request_data(): void
    {
        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);
        $inspectionRequest->update([
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        $completedInspectionRequest = $this->createInspectionRequest($tenant);
        $completedInspectionRequest->update([
            'status' => 'completed',
            'outcome_type' => 'follow_up_needed',
            'outcome_notes' => 'A second visit is still needed before deciding.',
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee('Inspection requests');
        $response->assertSee('Open requests');
        $response->assertSee('Closed requests');
        $response->assertSee('What needs attention');
        $response->assertSee('Upcoming scheduled inspection');
        $response->assertSee('Latest completed visit outcome');
        $response->assertSee($inspectionRequest->property->title);
        $response->assertSee($completedInspectionRequest->property->title);
        $response->assertSee('data-admin-shell-key="tenant"', false);
        $response->assertSee('Workspace Menu');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
        $response->assertSee('href="'.route('tenant.inspection-requests.index').'"', false);
        $response->assertSee('href="'.route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]).'"', false);
        $response->assertSee('href="'.route('tenant.inspection-requests.show', ['inspectionRequestId' => $completedInspectionRequest->getKey()]).'"', false);
    }

    public function test_tenant_dashboard_renders_unavailable_state_when_inspection_tables_are_missing(): void
    {
        $tenant = $this->createTenant();

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee('Inspection requests');
        $response->assertSee('Inspection request data is not available yet.');
    }

    public function test_landlord_dashboard_shows_operational_summary_and_attention_items(): void
    {
        $landlord = $this->createLandlord();

        $profile = LandlordProfile::create([
            'user_id' => $landlord->id,
            'business_name' => 'Sunrise Homes',
            'city' => 'Akure',
            'state' => 'Ondo',
            'verification_status' => 'under_review',
        ]);

        LandlordDocument::create([
            'landlord_profile_id' => $profile->id,
            'document_type' => 'government_id',
            'original_name' => 'id-card.pdf',
            'file_path' => 'landlord-documents/id-card.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'review_status' => 'pending',
        ]);

        $this->createProperty(
            landlord: $landlord,
            title: 'Pending Review Listing',
            status: 'pending_review',
            isVerified: false,
            isPublished: false,
        );
        $approvedUnpublishedProperty = $this->createProperty(
            landlord: $landlord,
            title: 'Approved Unpublished Listing',
            status: PublicPropertyVisibility::APPROVED_STATUS,
            isVerified: true,
            isPublished: false,
        );
        $this->createProperty(
            landlord: $landlord,
            title: 'Live Published Listing',
            status: PublicPropertyVisibility::APPROVED_STATUS,
            isVerified: true,
            isPublished: true,
        );

        $tenant = $this->createTenant('landlord-dashboard-tenant@example.com');
        $inspectionRequest = InspectionRequest::create([
            'property_id' => $approvedUnpublishedProperty->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Late morning',
            'message' => 'Please confirm access time.',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $response->assertOk();
        $response->assertSee('Needs attention now');
        $response->assertSee('Listing status summary');
        $response->assertSee('Inspection coordination');
        $response->assertSee('Listings waiting in review');
        $response->assertSee('Approved listings ready to publish');
        $response->assertSee('Pending Review Listing');
        $response->assertSee('Approved Unpublished Listing');
        $response->assertSee('Live Published Listing');
        $response->assertSee('Open inspection requests');
        $response->assertSee('Scheduled visits');
        $response->assertSee('Requested');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertSee('Workspace Menu');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
        $response->assertSee('href="'.route('landlord.properties').'"', false);
        $response->assertSee('href="'.route('landlord.properties.edit', $approvedUnpublishedProperty).'"', false);
        $response->assertSee('href="'.route('landlord.inspection-requests.index').'"', false);
        $response->assertSee('href="'.route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]).'"', false);
    }

    public function test_landlord_dashboard_renders_safely_when_landlord_documents_table_is_missing(): void
    {
        $landlord = $this->createLandlord();

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'business_name' => 'Safe Dashboard Homes',
            'verification_status' => 'pending',
        ]);

        Schema::dropIfExists('landlord_documents');

        $response = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $response->assertOk();
        $response->assertSee('Landlord Dashboard');
        $response->assertSee('Verification document data is not available yet in this environment.');
        $response->assertDontSee('Upload verification documents');
    }

    public function test_landlord_dashboard_renders_inspection_unavailable_state_when_tables_are_missing(): void
    {
        $landlord = $this->createLandlord();

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'pending',
        ]);

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $response->assertOk();
        $response->assertSee('Inspection coordination');
        $response->assertSee('Inspection request data is not available yet.');
    }

    public function test_landlord_inspection_request_index_shows_operational_summary(): void
    {
        $landlord = $this->createLandlord();
        $tenant = $this->createTenant('landlord-index-tenant@example.com');
        $property = $this->createProperty(
            landlord: $landlord,
            title: 'Landlord Index Property',
            status: PublicPropertyVisibility::APPROVED_STATUS,
            isVerified: true,
            isPublished: true,
        );

        InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ]);

        InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'scheduled',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'scheduled_at' => now()->addDays(4),
        ]);

        InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'completed',
            'preferred_date' => now()->subDays(1)->toDateString(),
            'outcome_type' => 'inspected',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Open requests');
        $response->assertSee('Scheduled requests');
        $response->assertSee('Closed requests');
        $response->assertSee('Landlord Index Property');
    }

    public function test_landlord_inspection_request_index_renders_unavailable_state_when_tables_are_missing(): void
    {
        $landlord = $this->createLandlord();

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Inspection Requests');
        $response->assertSee('Inspection request data is not available yet.');
    }

    public function test_tenant_can_access_their_own_inspection_request_index_and_detail(): void
    {
        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);

        $this->actingAs($tenant)->get(route('tenant.inspection-requests.index'))
            ->assertOk()
            ->assertSee($inspectionRequest->property->title)
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);

        $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', $inspectionRequest))
            ->assertOk()
            ->assertSee($inspectionRequest->property->title)
            ->assertSee('data-admin-shell-key="tenant"', false)
            ->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_tenant_inspection_request_index_shows_operational_summary(): void
    {
        $tenant = $this->createTenant();
        $requestedInspectionRequest = $this->createInspectionRequest($tenant);
        $scheduledInspectionRequest = $this->createInspectionRequest($tenant);
        $scheduledInspectionRequest->update([
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);
        $completedInspectionRequest = $this->createInspectionRequest($tenant);
        $completedInspectionRequest->update([
            'status' => 'completed',
            'outcome_type' => 'follow_up_needed',
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Open requests');
        $response->assertSee('Scheduled requests');
        $response->assertSee('Closed requests');
        $response->assertSee('Next visit:');
        $response->assertSee($requestedInspectionRequest->property->title);
        $response->assertSee($scheduledInspectionRequest->property->title);
        $response->assertSee($completedInspectionRequest->property->title);
    }

    public function test_tenant_inspection_request_index_renders_unavailable_state_when_tables_are_missing(): void
    {
        $tenant = $this->createTenant();

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($tenant)->get(route('tenant.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Inspection Requests');
        $response->assertSee('Inspection request data is not available yet.');
    }

    public function test_tenant_inspection_request_index_filter_still_works_with_operational_summary_present(): void
    {
        $tenant = $this->createTenant();
        $scheduledProperty = $this->createPublicProperty();
        $scheduledInspectionRequest = InspectionRequest::create([
            'property_id' => $scheduledProperty->id,
            'tenant_id' => $tenant->id,
            'status' => 'scheduled',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm the exact time once available.',
            'scheduled_at' => now()->addDay(),
        ]);
        $scheduledInspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'scheduled',
            'changed_by' => null,
            'notes' => null,
        ]);

        $requestedProperty = $this->createProperty(title: 'Tenant Requested Filter Property', isPublished: true);
        $requestedInspectionRequest = InspectionRequest::create([
            'property_id' => $requestedProperty->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'preferred_time_note' => 'Morning works best',
            'message' => 'Please confirm the exact time once available.',
        ]);
        $requestedInspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'requested',
            'changed_by' => null,
            'notes' => null,
        ]);

        $this->actingAs($tenant);

        Livewire::test(TenantInspectionRequestIndex::class)
            ->set('statusFilter', 'scheduled')
            ->assertSee($scheduledInspectionRequest->property->title)
            ->assertDontSee($requestedInspectionRequest->property->title)
            ->assertSee('Scheduled requests');
    }

    public function test_tenant_inspection_request_detail_renders_unavailable_state_when_tables_are_missing(): void
    {
        $tenant = $this->createTenant();

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', ['inspectionRequestId' => 1]));

        $response->assertOk();
        $response->assertSee('Inspection request detail data is not available yet.');
    }

    public function test_tenant_cannot_access_another_tenants_inspection_request_detail(): void
    {
        $ownerTenant = $this->createTenant();
        $otherTenant = $this->createTenant('other-tenant@example.com');
        $inspectionRequest = $this->createInspectionRequest($ownerTenant);

        $this->actingAs($otherTenant)
            ->get(route('tenant.inspection-requests.show', $inspectionRequest))
            ->assertNotFound();
    }

    public function test_tenant_can_see_outcome_visibility_when_available(): void
    {
        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);

        $inspectionRequest->update([
            'status' => 'completed',
            'outcome_type' => 'property_unavailable',
            'outcome_notes' => 'The property is no longer available, so we could not continue with the visit.',
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', $inspectionRequest));

        $response->assertOk();
        $response->assertSee('Property unavailable');
        $response->assertSee('The property is no longer available, so we could not continue with the visit.');
    }

    public function test_tenant_does_not_see_outcome_summary_on_non_completed_requests(): void
    {
        $tenant = $this->createTenant();
        $inspectionRequest = $this->createInspectionRequest($tenant);

        $inspectionRequest->update([
            'status' => 'scheduled',
            'outcome_type' => null,
            'outcome_notes' => null,
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', $inspectionRequest));

        $response->assertOk();
        $response->assertSee('Available after the visit is completed.');
        $response->assertDontSee('Outcome summary');
    }

    public function test_tenant_navigation_includes_inspection_requests_link(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee(route('tenant.inspection-requests.index'));
    }

    public function test_duplicate_open_inspection_request_is_prevented(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
        ]);

        $response = $this->actingAs($tenant)->from(route('properties.show', $property))->post(route('inspection-requests.store', $property), [
            'accepted_inspection_terms' => '1',
            'preferred_date' => now()->addDays(2)->toDateString(),
        ]);

        $response->assertRedirect(route('properties.show', $property));
        $response->assertSessionHasErrors('property');
        $this->assertSame(1, InspectionRequest::count());
    }

    public function test_tenant_must_accept_inspection_terms_before_submitting_request(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $response = $this->actingAs($tenant)
            ->from(route('properties.show', $property))
            ->post(route('inspection-requests.store', $property), [
                'preferred_date' => now()->addDays(3)->toDateString(),
                'preferred_time_note' => 'After 4pm',
            ]);

        $response->assertRedirect(route('properties.show', $property));
        $response->assertSessionHasErrors('accepted_inspection_terms');
        $this->assertDatabaseCount('inspection_requests', 0);
    }

    protected function createReviewer(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createTenant(?string $email = null): User
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
    }

    protected function createProperty(
        ?User $landlord = null,
        string $title = 'Verified Two Bedroom Flat in Alagbaka',
        string $status = PublicPropertyVisibility::APPROVED_STATUS,
        bool $isVerified = true,
        bool $isPublished = false,
    ): Property {
        $landlord ??= $this->createLandlord();

        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => $title,
            'property_type' => 'flat',
            'rent_amount' => 900000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'landmark' => 'Near Shoprite',
            'description' => 'A verified apartment in a central area.',
            'status' => $status,
            'is_verified' => $isVerified,
            'is_published' => $isPublished,
        ]);
    }

    protected function createPublicProperty(): Property
    {
        return $this->createProperty(isPublished: true);
    }

    protected function createInspectionRequest(?User $tenant = null): InspectionRequest
    {
        $tenant ??= $this->createTenant();
        $property = $this->createPublicProperty();

        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm the exact time once available.',
            'outcome_type' => null,
            'outcome_notes' => null,
        ]);

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'requested',
            'changed_by' => null,
            'notes' => null,
        ]);

        return $inspectionRequest;
    }

    protected function completedTermsGateSession(string $gate): array
    {
        $completedAt = now();
        $openedAt = $completedAt->copy()->subSeconds(11);

        return [
            'terms_gates.'.md5($gate) => [
                'opened_at' => $openedAt->toIso8601String(),
                'opened_at_ms' => $openedAt->getTimestampMs(),
                'completed_at' => $completedAt->toIso8601String(),
            ],
        ];
    }
}
