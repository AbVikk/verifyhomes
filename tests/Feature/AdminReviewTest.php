<?php

namespace Tests\Feature;

use App\Livewire\Admin\Landlords\Index as AdminLandlordIndex;
use App\Livewire\Admin\Properties\Index as AdminPropertyIndex;
use App\Livewire\Admin\Landlords\Show as AdminLandlordShow;
use App\Livewire\Admin\Properties\Show as AdminPropertyShow;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_admin_can_access_landlord_review_index_and_detail(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        $this->actingAs($admin)->get(route('admin.landlords.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.landlords.show', $landlordProfile))->assertOk();
    }

    public function test_verified_staff_can_access_landlord_review_index_and_detail(): void
    {
        $staff = $this->createReviewer('staff');
        $landlordProfile = $this->createLandlordProfile();

        $this->actingAs($staff)->get(route('admin.landlords.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.landlords.show', $landlordProfile))->assertOk();
    }

    public function test_admin_landlord_index_search_and_status_filter_can_be_combined(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordUser('review-match@example.com');
        $matchingLandlord->update([
            'name' => 'Kemi Review Match',
            'phone' => '08012345678',
        ]);
        $matchingLandlord->landlordProfile->update([
            'business_name' => 'Review Match Homes',
            'verification_status' => 'under_review',
        ]);

        $nonMatchingLandlord = $this->createLandlordUser('other-landlord@example.com');
        $nonMatchingLandlord->update([
            'name' => 'Other Landlord',
        ]);
        $nonMatchingLandlord->landlordProfile->update([
            'business_name' => 'Other Homes',
            'verification_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.landlords.index', [
            'search' => 'Review Match',
            'statusFilter' => 'under_review',
        ]));

        $response->assertOk();
        $response->assertSee('Kemi Review Match');
        $response->assertSee('Review Match Homes');
        $response->assertDontSee('Other Landlord');
    }

    public function test_admin_can_bulk_approve_selected_landlords(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordProfile();
        $otherLandlord = $this->createLandlordProfile('other-bulk-approve@example.com');

        $this->actingAs($admin);

        Livewire::test(AdminLandlordIndex::class)
            ->set('selectedLandlordIds', [$matchingLandlord->id])
            ->call('bulkApprove');

        $matchingLandlord->refresh();
        $otherLandlord->refresh();

        $this->assertSame('approved', $matchingLandlord->verification_status);
        $this->assertNotNull($matchingLandlord->verified_at);
        $this->assertSame($admin->id, $matchingLandlord->verified_by);
        $this->assertDatabaseHas('landlord_status_histories', [
            'landlord_profile_id' => $matchingLandlord->id,
            'from_status' => 'pending',
            'to_status' => 'approved',
            'changed_by' => $admin->id,
            'notes' => 'Bulk action from landlord review queue.',
        ]);

        $this->assertSame('pending', $otherLandlord->verification_status);
        $this->assertNull($otherLandlord->verified_at);
    }

    public function test_admin_can_bulk_reject_selected_landlords(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordProfile();
        $matchingLandlord->update([
            'verification_status' => 'approved',
            'verified_at' => now()->subDay(),
            'verified_by' => $admin->id,
        ]);

        $otherLandlord = $this->createLandlordProfile('other-bulk-reject@example.com');
        $otherLandlord->update([
            'verification_status' => 'approved',
            'verified_at' => now()->subDay(),
            'verified_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminLandlordIndex::class)
            ->set('selectedLandlordIds', [$matchingLandlord->id])
            ->call('bulkReject');

        $matchingLandlord->refresh();
        $otherLandlord->refresh();

        $this->assertSame('rejected', $matchingLandlord->verification_status);
        $this->assertNull($matchingLandlord->verified_at);
        $this->assertNull($matchingLandlord->verified_by);
        $this->assertDatabaseHas('landlord_status_histories', [
            'landlord_profile_id' => $matchingLandlord->id,
            'from_status' => 'approved',
            'to_status' => 'rejected',
            'changed_by' => $admin->id,
            'notes' => 'Bulk action from landlord review queue.',
        ]);

        $this->assertSame('approved', $otherLandlord->verification_status);
        $this->assertNotNull($otherLandlord->verified_at);
    }

    public function test_admin_can_bulk_move_selected_landlords_to_under_review(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordProfile();
        $matchingLandlord->update([
            'verification_status' => 'approved',
            'verified_at' => now()->subDay(),
            'verified_by' => $admin->id,
        ]);

        $otherLandlord = $this->createLandlordProfile('other-under-review@example.com');

        $this->actingAs($admin);

        Livewire::test(AdminLandlordIndex::class)
            ->set('selectedLandlordIds', [$matchingLandlord->id])
            ->call('bulkMarkUnderReview');

        $matchingLandlord->refresh();
        $otherLandlord->refresh();

        $this->assertSame('under_review', $matchingLandlord->verification_status);
        $this->assertNull($matchingLandlord->verified_at);
        $this->assertNull($matchingLandlord->verified_by);
        $this->assertDatabaseHas('landlord_status_histories', [
            'landlord_profile_id' => $matchingLandlord->id,
            'from_status' => 'approved',
            'to_status' => 'under_review',
            'changed_by' => $admin->id,
            'notes' => 'Bulk action from landlord review queue.',
        ]);

        $this->assertSame('pending', $otherLandlord->verification_status);
    }

    public function test_landlord_review_index_still_renders_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('landlord_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.landlords.index'));

        $response->assertOk();
        $response->assertSee('Landlord Reviews');
    }

    public function test_landlord_bulk_actions_fail_softly_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordProfile();

        Schema::dropIfExists('landlord_status_histories');

        $this->actingAs($admin);

        Livewire::test(AdminLandlordIndex::class)
            ->set('selectedLandlordIds', [$matchingLandlord->id])
            ->call('bulkApprove')
            ->assertSee('Landlord bulk actions are unavailable until landlord history data is available in this environment.');

        $matchingLandlord->refresh();

        $this->assertSame('pending', $matchingLandlord->verification_status);
    }

    public function test_bulk_landlord_status_change_skips_no_op_history_rows(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordProfile();
        $matchingLandlord->update([
            'verification_status' => 'under_review',
            'verified_at' => null,
            'verified_by' => null,
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminLandlordIndex::class)
            ->set('selectedLandlordIds', [$matchingLandlord->id])
            ->call('bulkMarkUnderReview');

        $matchingLandlord->refresh();

        $this->assertSame('under_review', $matchingLandlord->verification_status);
        $this->assertDatabaseCount('landlord_status_histories', 0);
    }

    public function test_landlord_cannot_access_admin_review_pages(): void
    {
        $landlord = $this->createLandlordUser();
        $landlordProfile = $landlord->landlordProfile;

        $this->actingAs($landlord)->get(route('admin.landlords.index'))->assertForbidden();
        $this->actingAs($landlord)->get(route('admin.landlords.show', $landlordProfile))->assertForbidden();
    }

    public function test_admin_can_change_landlord_verification_status_and_history_row_is_created(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        $this->actingAs($admin);

        Livewire::test(AdminLandlordShow::class, ['landlordProfile' => $landlordProfile])
            ->set('reviewNotes', 'Documents checked and accepted.')
            ->call('changeStatus', 'approved');

        $landlordProfile->refresh();

        $this->assertSame('approved', $landlordProfile->verification_status);
        $this->assertNotNull($landlordProfile->verified_at);
        $this->assertSame($admin->id, $landlordProfile->verified_by);
        $this->assertDatabaseHas('landlord_status_histories', [
            'landlord_profile_id' => $landlordProfile->id,
            'from_status' => 'pending',
            'to_status' => 'approved',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_landlord_review_detail_renders_unavailable_state_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        Schema::dropIfExists('landlord_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.landlords.show', $landlordProfile));

        $response->assertOk();
        $response->assertSee('Landlord review detail is not fully available yet.');
    }

    public function test_landlord_review_action_fails_softly_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        Schema::dropIfExists('landlord_status_histories');

        $this->actingAs($admin);

        Livewire::test(AdminLandlordShow::class, ['landlordProfile' => $landlordProfile])
            ->set('reviewNotes', 'Documents checked and accepted.')
            ->call('changeStatus', 'approved')
            ->assertSee('Landlord review actions are unavailable until landlord history data is available in this environment.');

        $landlordProfile->refresh();

        $this->assertSame('pending', $landlordProfile->verification_status);
    }

    public function test_admin_can_change_property_status_and_history_row_is_created(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->set('reviewNotes', 'Property details verified.')
            ->call('changeStatus', 'approved');

        $property->refresh();

        $this->assertSame('approved', $property->status);
        $this->assertTrue($property->is_verified);
        $this->assertFalse($property->is_published);
        $this->assertSame($admin->id, $property->verified_by);
        $this->assertDatabaseHas('property_status_histories', [
            'property_id' => $property->id,
            'from_status' => 'pending_review',
            'to_status' => 'approved',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_admin_property_review_page_shows_caution_fee_service_charge_and_clickable_images(): void
    {
        Storage::fake('public');

        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();
        $property->update([
            'caution_fee' => 120000,
            'service_charge' => 35000,
        ]);

        Storage::disk('public')->put("property-images/{$property->id}/front-view.jpg", 'front-image');

        PropertyImage::create([
            'property_id' => $property->id,
            'image_path' => "property-images/{$property->id}/front-view.jpg",
            'sort_order' => 0,
            'is_cover' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.properties.show', $property));

        $response->assertOk();
        $response->assertSee('Caution fee');
        $response->assertSee(html_entity_decode('&#8358;').'120,000.00');
        $response->assertSee('Service charge');
        $response->assertSee(html_entity_decode('&#8358;').'35,000.00');
        $response->assertSee('target="_blank"', false);
        $response->assertSee('property-images/'.$property->id.'/front-view.jpg');
    }

    public function test_property_review_detail_renders_unavailable_state_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        Schema::dropIfExists('property_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.properties.show', $property));

        $response->assertOk();
        $response->assertSee('Property review detail is not fully available yet.');
    }

    public function test_property_review_action_fails_softly_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        Schema::dropIfExists('property_status_histories');

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->set('reviewNotes', 'Property details verified.')
            ->call('changeStatus', 'approved')
            ->assertSee('Property review actions are unavailable until property history data is available in this environment.');

        $property->refresh();

        $this->assertSame('pending_review', $property->status);
    }

    public function test_admin_can_publish_an_approved_verified_property(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty(status: 'approved', isVerified: true, isPublished: false);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->call('publish');

        $property->refresh();

        $this->assertTrue($property->is_published);
        $this->assertSame('approved', $property->status);
        $this->assertTrue($property->is_verified);
    }

    public function test_admin_can_unpublish_a_published_property(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty(status: 'approved', isVerified: true, isPublished: true);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->call('unpublish');

        $property->refresh();

        $this->assertFalse($property->is_published);
    }

    public function test_publishing_is_blocked_for_non_approved_or_non_verified_properties(): void
    {
        $admin = $this->createReviewer('admin');
        $pendingProperty = $this->createProperty(status: 'pending_review', isVerified: false, isPublished: false);
        $unverifiedApprovedProperty = $this->createProperty(email: 'approved-owner@example.com', status: 'approved', isVerified: false, isPublished: false);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $pendingProperty])
            ->call('publish')
            ->assertHasErrors(['publish']);

        Livewire::test(AdminPropertyShow::class, ['property' => $unverifiedApprovedProperty])
            ->call('publish')
            ->assertHasErrors(['publish']);

        $pendingProperty->refresh();
        $unverifiedApprovedProperty->refresh();

        $this->assertFalse($pendingProperty->is_published);
        $this->assertFalse($unverifiedApprovedProperty->is_published);
    }

    public function test_changing_a_published_property_away_from_approved_removes_public_visibility(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty(status: 'approved', isVerified: true, isPublished: true);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->call('changeStatus', 'rejected');

        $property->refresh();

        $this->assertSame('rejected', $property->status);
        $this->assertFalse($property->is_published);
        $this->assertFalse($property->isPubliclyVisible());
    }

    public function test_admin_can_access_download_a_landlord_private_document(): void
    {
        Storage::fake('local');

        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();
        $path = "landlord-documents/{$landlordProfile->id}/id-card.pdf";

        Storage::disk('local')->put($path, 'landlord-document');

        $document = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'id-card.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.landlords.documents.download', [$landlordProfile, $document]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_admin_can_access_download_a_property_private_document(): void
    {
        Storage::fake('local');

        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();
        $path = "property-documents/{$property->id}/ownership.pdf";

        Storage::disk('local')->put($path, 'property-document');

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'ownership.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.properties.documents.download', [$property, $document]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_missing_landlord_private_document_file_fails_softly(): void
    {
        Storage::fake('local');

        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();

        $document = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'missing-id-card.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/missing-id-card.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landlords.documents.download', [$landlordProfile, $document]))
            ->assertNotFound();
    }

    public function test_missing_property_private_document_file_fails_softly(): void
    {
        Storage::fake('local');

        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'missing-ownership.pdf',
            'file_path' => "property-documents/{$property->id}/missing-ownership.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.properties.documents.download', [$property, $document]))
            ->assertNotFound();
    }

    public function test_landlord_cannot_access_admin_only_private_document_routes(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlordUser();
        $landlordProfile = $landlord->landlordProfile;
        $property = $this->createProperty($landlord);

        $landlordDocument = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'id-card.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/id-card.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $propertyDocument = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'ownership.pdf',
            'file_path' => "property-documents/{$property->id}/ownership.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($landlord)->get(route('admin.landlords.documents.download', [$landlordProfile, $landlordDocument]))->assertForbidden();
        $this->actingAs($landlord)->get(route('admin.properties.documents.download', [$property, $propertyDocument]))->assertForbidden();
    }

    public function test_mismatched_landlord_document_access_is_blocked(): void
    {
        Storage::fake('local');

        $admin = $this->createReviewer('admin');
        $landlordProfile = $this->createLandlordProfile();
        $otherLandlordProfile = $this->createLandlordProfile('other-landlord@example.com');

        $document = LandlordDocument::create([
            'landlord_profile_id' => $landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'id-card.pdf',
            'file_path' => "landlord-documents/{$landlordProfile->id}/id-card.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landlords.documents.download', [$otherLandlordProfile, $document]))
            ->assertNotFound();
    }

    public function test_mismatched_property_document_access_is_blocked(): void
    {
        Storage::fake('local');

        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();
        $otherProperty = $this->createProperty(email: 'other-owner@example.com');

        $document = PropertyDocument::create([
            'property_id' => $property->id,
            'document_type' => 'ownership_proof',
            'original_name' => 'ownership.pdf',
            'file_path' => "property-documents/{$property->id}/ownership.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'review_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.properties.documents.download', [$otherProperty, $document]))
            ->assertNotFound();
    }

    public function test_approved_property_remains_unpublished(): void
    {
        $admin = $this->createReviewer('admin');
        $property = $this->createProperty();

        $this->actingAs($admin);

        Livewire::test(AdminPropertyShow::class, ['property' => $property])
            ->call('changeStatus', 'approved');

        $property->refresh();

        $this->assertSame('approved', $property->status);
        $this->assertTrue($property->is_verified);
        $this->assertFalse($property->is_published);
    }

    public function test_landlord_cannot_publish_or_unpublish_properties(): void
    {
        $landlord = $this->createLandlordUser();
        $property = $this->createProperty($landlord, status: 'approved', isVerified: true, isPublished: true);

        $this->actingAs($landlord)->get(route('admin.properties.show', $property))->assertForbidden();

        $property->refresh();

        $this->assertTrue($property->is_published);
    }

    public function test_admin_property_index_search_status_and_publish_filters_can_be_combined(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingLandlord = $this->createLandlordUser('match-owner@example.com');
        $matchingLandlord->update([
            'name' => 'Amina Match Owner',
        ]);

        $matchingProperty = Property::create([
            'landlord_id' => $matchingLandlord->id,
            'title' => 'Match Villa',
            'property_type' => 'duplex',
            'rent_amount' => 1250000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'landmark' => 'Near Civic Centre',
            'status' => 'approved',
            'is_verified' => true,
            'is_published' => false,
        ]);

        $publishedProperty = $this->createProperty(
            $this->createLandlordUser('published-owner@example.com'),
            status: 'approved',
            isVerified: true,
            isPublished: true,
        );

        $response = $this->actingAs($admin)->get(route('admin.properties.index', [
            'search' => 'Match',
            'statusFilter' => 'approved',
            'publishFilter' => 'approved_unpublished',
        ]));

        $response->assertOk();
        $response->assertSee($matchingProperty->title);
        $response->assertDontSee($publishedProperty->title);
    }

    public function test_admin_can_bulk_approve_selected_properties(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingProperty = $this->createProperty(status: 'pending_review', isVerified: false, isPublished: false);
        $otherProperty = $this->createProperty(email: 'other-owner@example.com', status: 'pending_review', isVerified: false, isPublished: false);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyIndex::class)
            ->set('selectedPropertyIds', [$matchingProperty->id])
            ->call('bulkApprove');

        $matchingProperty->refresh();
        $otherProperty->refresh();

        $this->assertSame('approved', $matchingProperty->status);
        $this->assertTrue($matchingProperty->is_verified);
        $this->assertFalse($matchingProperty->is_published);
        $this->assertSame($admin->id, $matchingProperty->verified_by);
        $this->assertDatabaseHas('property_status_histories', [
            'property_id' => $matchingProperty->id,
            'from_status' => 'pending_review',
            'to_status' => 'approved',
            'changed_by' => $admin->id,
        ]);

        $this->assertSame('pending_review', $otherProperty->status);
        $this->assertFalse($otherProperty->is_verified);
    }

    public function test_property_review_index_still_renders_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('property_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.properties.index'));

        $response->assertOk();
        $response->assertSee('Property Reviews');
    }

    public function test_property_bulk_actions_fail_softly_when_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingProperty = $this->createProperty(status: 'pending_review', isVerified: false, isPublished: false);

        Schema::dropIfExists('property_status_histories');

        $this->actingAs($admin);

        Livewire::test(AdminPropertyIndex::class)
            ->set('selectedPropertyIds', [$matchingProperty->id])
            ->call('bulkApprove')
            ->assertSee('Property bulk actions are unavailable until property history data is available in this environment.');

        $matchingProperty->refresh();

        $this->assertSame('pending_review', $matchingProperty->status);
        $this->assertFalse($matchingProperty->is_verified);
    }

    public function test_admin_can_bulk_reject_selected_properties_and_unpublish_them(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingProperty = $this->createProperty(status: 'approved', isVerified: true, isPublished: true);
        $otherProperty = $this->createProperty(email: 'reject-other@example.com', status: 'approved', isVerified: true, isPublished: true);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyIndex::class)
            ->set('selectedPropertyIds', [$matchingProperty->id])
            ->call('bulkReject');

        $matchingProperty->refresh();
        $otherProperty->refresh();

        $this->assertSame('rejected', $matchingProperty->status);
        $this->assertFalse($matchingProperty->is_published);
        $this->assertFalse($matchingProperty->is_verified);
        $this->assertNull($matchingProperty->verified_by);
        $this->assertDatabaseHas('property_status_histories', [
            'property_id' => $matchingProperty->id,
            'from_status' => 'approved',
            'to_status' => 'rejected',
            'changed_by' => $admin->id,
        ]);

        $this->assertSame('approved', $otherProperty->status);
        $this->assertTrue($otherProperty->is_published);
    }

    public function test_admin_can_bulk_unpublish_only_selected_properties(): void
    {
        $admin = $this->createReviewer('admin');
        $matchingProperty = $this->createProperty(status: 'approved', isVerified: true, isPublished: true);
        $otherProperty = $this->createProperty(email: 'live-other@example.com', status: 'approved', isVerified: true, isPublished: true);

        $this->actingAs($admin);

        Livewire::test(AdminPropertyIndex::class)
            ->set('selectedPropertyIds', [$matchingProperty->id])
            ->call('bulkUnpublish');

        $matchingProperty->refresh();
        $otherProperty->refresh();

        $this->assertFalse($matchingProperty->is_published);
        $this->assertTrue($otherProperty->is_published);
        $this->assertDatabaseCount('property_status_histories', 0);
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
        bool $isVerified = false,
        bool $isPublished = false,
    ): Property
    {
        $landlord ??= $this->createLandlordUser($email);

        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Two Bedroom Flat in Alagbaka',
            'property_type' => 'flat',
            'rent_amount' => 750000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => $status,
            'is_verified' => $isVerified,
            'is_published' => $isPublished,
        ]);
    }
}

