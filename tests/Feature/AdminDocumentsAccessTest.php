<?php

namespace Tests\Feature;

use App\Livewire\Admin\Documents\Index as AdminDocumentIndex;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDocumentsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_documents_index(): void
    {
        $admin = $this->createReviewer('admin');

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Document review queue');
    }

    public function test_staff_can_access_documents_index(): void
    {
        $staff = $this->createReviewer('staff');

        $response = $this->actingAs($staff)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Document review queue');
    }

    public function test_landlord_cannot_access_admin_documents_index(): void
    {
        $landlord = $this->createLandlordUser();

        $this->actingAs($landlord)->get(route('admin.documents.index'))->assertForbidden();
    }

    public function test_documents_index_renders_unavailable_state_when_both_document_tables_are_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('landlord_documents');
        Schema::dropIfExists('property_documents');

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Document review data is not available yet in this environment.');
    }

    public function test_documents_index_still_renders_when_only_landlord_documents_table_exists(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'landlord-id.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/landlord-id.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        Schema::dropIfExists('property_documents');

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('landlord-id.pdf');
        $response->assertSee('Landlord');
    }

    public function test_documents_index_still_renders_when_only_property_documents_table_exists(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'ownership.pdf',
            'file_path' => "property-documents/{$property->id}/ownership.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        Schema::dropIfExists('landlord_documents');

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('ownership.pdf');
        $response->assertSee('Property');
    }

    public function test_documents_index_still_renders_when_landlord_supporting_table_is_missing_but_property_source_is_usable(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();
        $property = $this->createProperty();

        LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'landlord-side.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/landlord-side.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'property-side.pdf',
            'file_path' => "property-documents/{$property->id}/property-side.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('landlord_profiles');
        Schema::enableForeignKeyConstraints();

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('property-side.pdf');
        $response->assertSee('Property');
        $response->assertDontSee('landlord-side.pdf');
    }

    public function test_documents_index_still_renders_when_property_supporting_table_is_missing_but_landlord_source_is_usable(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();
        $property = $this->createProperty();

        LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'landlord-side.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/landlord-side.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'property-side.pdf',
            'file_path' => "property-documents/{$property->id}/property-side.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('properties');
        Schema::enableForeignKeyConstraints();

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('landlord-side.pdf');
        $response->assertSee('Landlord');
        $response->assertDontSee('property-side.pdf');
    }

    public function test_documents_index_renders_unavailable_state_when_neither_source_is_usable_due_to_missing_supporting_tables(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('landlord_profiles');
        Schema::dropIfExists('properties');
        Schema::enableForeignKeyConstraints();

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Document review data is not available yet in this environment.');
    }

    public function test_combined_documents_queue_search_and_filters_work_for_a_realistic_case(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordUser('docs-owner@example.com');
        $matchingLandlord->update([
            'name' => 'Amina Documents Owner',
        ]);
        $matchingLandlord->landlordProfile->update([
            'business_name' => 'Documents Match Homes',
        ]);

        LandlordDocument::create([
            'landlord_profile_id' => $matchingLandlord->landlordProfile->id,
            'document_type' => 'utility_bill',
            'original_name' => 'match-utility.pdf',
            'file_path' => "landlord-documents/{$matchingLandlord->landlordProfile->id}/match-utility.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $otherProperty = $this->createProperty(email: 'property-owner@example.com');

        PropertyDocument::create([
            'property_id' => $otherProperty->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'other-ownership.pdf',
            'file_path' => "property-documents/{$otherProperty->id}/other-ownership.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.documents.index', [
            'search' => 'Documents Match',
            'sourceFilter' => 'landlord',
            'statusFilter' => 'pending',
        ]));

        $response->assertOk();
        $response->assertSee('match-utility.pdf');
        $response->assertSee('Documents Match Homes');
        $response->assertDontSee('other-ownership.pdf');
    }

    public function test_admin_can_approve_a_landlord_document_from_the_admin_documents_queue(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        $document = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'review-me.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/review-me.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'landlord', $document->id, 'approved')
            ->assertSee('Document review status updated successfully.');

        $document->refresh();

        $this->assertSame('approved', $document->review_status);
        $this->assertSame($admin->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);
    }

    public function test_admin_can_reject_a_property_document_from_the_admin_documents_queue(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'reject-me.pdf',
            'file_path' => "property-documents/{$property->id}/reject-me.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'property', $document->id, 'rejected')
            ->assertSee('Document review status updated successfully.');

        $document->refresh();

        $this->assertSame('rejected', $document->review_status);
        $this->assertSame($admin->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);
    }

    public function test_admin_can_return_a_document_to_pending_from_the_admin_documents_queue(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'return-to-pending.pdf',
            'file_path' => "property-documents/{$property->id}/return-to-pending.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'property', $document->id, 'pending')
            ->assertSee('Document review status updated successfully.');

        $document->refresh();

        $this->assertSame('pending', $document->review_status);
        $this->assertNull($document->reviewed_by);
        $this->assertNull($document->reviewed_at);
    }

    public function test_document_review_action_still_works_when_reviewer_metadata_columns_do_not_exist(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        Schema::dropIfExists('landlord_documents');
        Schema::create('landlord_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_profile_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50)->index();
            $table->string('original_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 125);
            $table->unsignedBigInteger('file_size');
            $table->string('review_status', 25)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        $document = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'no-metadata-columns.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/no-metadata-columns.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'landlord', $document->id, 'approved')
            ->assertSee('Document review status updated successfully.');

        $document->refresh();

        $this->assertSame('approved', $document->review_status);
    }

    public function test_no_op_document_action_returns_soft_info_when_status_is_already_the_same(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        $document = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'already-approved.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/already-approved.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'approved',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'landlord', $document->id, 'approved')
            ->assertSee('That document already has the requested review status.');

        $document->refresh();

        $this->assertSame('approved', $document->review_status);
        $this->assertNull($document->reviewed_by);
        $this->assertNull($document->reviewed_at);
    }

    public function test_document_action_fails_softly_when_landlord_documents_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('landlord_documents');

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'landlord', 1, 'approved')
            ->assertSee('Landlord document actions are not available in this environment right now.');
    }

    public function test_document_action_fails_softly_when_property_documents_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('property_documents');

        $this->actingAs($admin);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'property', 1, 'approved')
            ->assertSee('Property document actions are not available in this environment right now.');
    }

    public function test_existing_documents_index_still_renders_correctly_after_row_actions_are_added(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'render-check.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/render-check.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('render-check.pdf');
        $response->assertSee('Approve');
        $response->assertSee('Reject');
        $response->assertSee('Return to Pending');
    }

    public function test_staff_can_perform_document_review_action(): void
    {
        $staff = $this->createReviewer('staff');
        $property = $this->createProperty();

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'staff-review.pdf',
            'file_path' => "property-documents/{$property->id}/staff-review.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($staff);

        Livewire::test(AdminDocumentIndex::class)
            ->call('updateDocumentStatus', 'property', $document->id, 'approved')
            ->assertSee('Document review status updated successfully.');

        $document->refresh();

        $this->assertSame('approved', $document->review_status);
        $this->assertSame($staff->id, $document->reviewed_by);
    }

    public function test_landlord_still_cannot_access_admin_documents_page_actions(): void
    {
        $landlord = $this->createLandlordUser();

        $this->actingAs($landlord)->get(route('admin.documents.index'))->assertForbidden();
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

    protected function createLandlordUser(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'pending',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        return $landlord;
    }

    protected function createLandlordProfile(?string $email = null): LandlordProfile
    {
        return $this->createLandlordUser($email)->landlordProfile;
    }

    protected function createProperty(
        ?User $landlord = null,
        ?string $email = null,
        string $status = 'pending_review',
    ): Property {
        $landlord ??= $this->createLandlordUser($email);

        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Documents Test Property',
            'property_type' => 'flat',
            'rent_amount' => 750000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => $status,
            'is_verified' => false,
            'is_published' => false,
        ]);
    }
}
