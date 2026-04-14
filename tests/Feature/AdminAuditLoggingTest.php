<?php

namespace Tests\Feature;

use App\Livewire\Admin\Documents\Index as AdminDocumentIndex;
use App\Livewire\Admin\InspectionRequests\Show as AdminInspectionRequestShow;
use App\Livewire\Admin\Landlords\Show as AdminLandlordShow;
use App\Livewire\Admin\Properties\Show as AdminPropertyShow;
use App\Models\InspectionRequest;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_status_change_creates_audit_log_entry(): void
    {
        $admin = $this->createReviewer('admin');
        $landlord = $this->createLandlord();
        $profile = LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'pending',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminLandlordShow::class, ['landlordProfile' => $profile])
            ->set('reviewNotes', 'Documents look complete.')
            ->call('changeStatus', 'approved');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'landlord_status_changed',
            'actor_id' => $admin->id,
            'target_id' => $profile->id,
        ]);
    }

    public function test_property_review_and_publish_actions_create_audit_log_entries(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->set('reviewNotes', 'Approved after verification.')
            ->call('changeStatus', 'approved')
            ->call('publish');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'property_status_changed',
            'actor_id' => $admin->id,
            'target_id' => $property->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'property_published',
            'actor_id' => $admin->id,
            'target_id' => $property->id,
        ]);
    }

    public function test_document_review_action_creates_audit_log_entry(): void
    {
        $admin = $this->createReviewer('staff');
        $landlord = $this->createLandlord();
        $profile = LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'under_review',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);
        $document = LandlordDocument::create([
            'landlord_profile_id' => $profile->id,
            'document_type' => 'national_id',
            'original_name' => 'tenant-id.pdf',
            'file_path' => 'landlord-documents/'.$profile->id.'/tenant-id.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1200,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'landlord', $document->id, 'approved');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document_status_changed',
            'actor_id' => $admin->id,
            'target_id' => $document->id,
        ]);
    }

    public function test_inspection_request_actions_create_audit_log_entries(): void
    {
        $admin = $this->createReviewer('admin');
        $tenant = $this->createTenant();
        $property = $this->createProperty(isPublished: true);
        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Late morning',
            'message' => 'Please confirm access.',
        ]);
        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'requested',
            'changed_by' => null,
            'notes' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('scheduledAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('adminNotes', 'Scheduled with tenant.')
            ->call('changeStatus', 'scheduled')
            ->set('adminNotes', 'Caretaker now has the keys ready.')
            ->call('saveCoordinationNotes');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inspection_request_status_changed',
            'actor_id' => $admin->id,
            'target_id' => $inspectionRequest->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inspection_request_notes_saved',
            'actor_id' => $admin->id,
            'target_id' => $inspectionRequest->id,
        ]);
    }

    public function test_admin_audit_page_shows_real_log_context_and_target_type_filter(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->set('reviewNotes', 'Approved after verification.')
            ->call('changeStatus', 'approved');

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'targetTypeFilter' => Property::class,
        ]));

        $response->assertOk();
        $response->assertSee('Review the live admin and staff workflow trail here.');
        $response->assertSee('Filter by subject type');
        $response->assertSee('Property Status Changed');
        $response->assertSee($admin->name);
        $response->assertSee($property->title);
        $response->assertSee('Property');
        $response->assertSee('Status: Pending Review -> Approved');
        $response->assertSee('Notes: Approved after verification.');
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

    protected function createLandlord(): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
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

    protected function createProperty(bool $isPublished = false): Property
    {
        return Property::create([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Audited Property Listing',
            'property_type' => 'flat',
            'rent_amount' => 900000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => 'pending_review',
            'is_verified' => false,
            'is_published' => $isPublished,
        ]);
    }
}
