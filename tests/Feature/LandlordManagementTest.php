<?php

namespace Tests\Feature;

use App\Livewire\Admin\Landlords\Show as AdminLandlordShow;
use App\Livewire\Landlord\Documents as LandlordDocuments;
use App\Livewire\Landlord\Profile as LandlordProfilePage;
use App\Livewire\Landlord\Properties\Concerns\InteractsWithPropertyForm;
use App\Livewire\Landlord\Properties\Create as LandlordPropertyCreate;
use App\Livewire\Landlord\Properties\Edit as LandlordPropertyEdit;
use App\Livewire\Landlord\InspectionRequests\Show as LandlordInspectionRequestShow;
use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\Currency;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandlordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_landlord_can_access_landlord_profile_page(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('landlord.profile'));

        $response->assertOk();
        $response->assertSee('Landlord Profile');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertSee('Workspace Menu');
        $response->assertSee('Use Camera');
        $response->assertSee('href="'.route('landlord.profile').'"', false);
        $response->assertDontSee('href="'.route('profile.edit').'"', false);
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
        $response->assertSee('Profile Information');
        $response->assertSee('Update Password');
        $response->assertSee('Delete Account');
    }

    public function test_landlord_can_update_account_information_from_shell_profile(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->patch(route('profile.update'), [
            'name' => 'Landlord Account Updated',
            'email' => 'landlord.updated@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('landlord.profile'));

        $landlord->refresh();

        $this->assertSame('Landlord Account Updated', $landlord->name);
        $this->assertSame('landlord.updated@example.com', $landlord->email);
        $this->assertNull($landlord->email_verified_at);
    }

    public function test_landlord_can_update_password_from_shell_profile(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)
            ->from(route('landlord.profile'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-strong-password',
                'password_confirmation' => 'new-strong-password',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('landlord.profile'));

        $this->assertTrue(Hash::check('new-strong-password', $landlord->refresh()->password));
    }

    public function test_landlord_navigation_profile_links_use_the_landlord_shell_profile_route(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('landlord.profile').'"', false);
        $response->assertDontSee('href="'.route('profile.edit').'"', false);
    }

    public function test_verified_landlord_can_access_landlord_documents_page(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('landlord.documents'));

        $response->assertOk();
        $response->assertSee('Landlord Documents');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertSee('href="'.route('landlord.profile').'"', false);
        $response->assertDontSee('href="'.route('profile.edit').'"', false);
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_verified_landlord_can_access_landlord_properties_page(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('landlord.properties'));

        $response->assertOk();
        $response->assertSee('My Properties');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_landlord_property_create_page_uses_lga_select_and_naira_money_fields(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $response = $this->actingAs($landlord)->get(route('landlord.properties.create'));

        $response->assertOk();
        $response->assertSee('Listing purpose');
        $response->assertSee('For rent');
        $response->assertSee('For sale');
        $response->assertSee('For lease');
        $response->assertSee('Select an Ondo State LGA');
        $response->assertSee('Akure South');
        $response->assertSee('Use the exact local government area for the listing location.');
        $response->assertSee('&#8358;', false);
        $response->assertSee('Listing terms');
        $response->assertSee('admin-button admin-button-primary', false);
    }

    public function test_landlord_documents_page_renders_unavailable_state_when_landlord_documents_table_is_missing(): void
    {
        $landlord = $this->createLandlord();

        Schema::dropIfExists('landlord_documents');

        $response = $this->actingAs($landlord)->get(route('landlord.documents'));

        $response->assertOk();
        $response->assertSee('Landlord Documents');
        $response->assertSee('Verification document uploads are not available yet.');
        $response->assertSee('Verification document data is not available yet.');
    }

    public function test_landlord_properties_page_shows_operational_summary_and_direct_actions(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $pendingProperty = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Pending Review Listing',
            'property_type' => 'flat',
            'rent_amount' => 500000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Ijapo',
            'status' => 'pending_review',
            'is_verified' => false,
            'is_published' => false,
        ]);

        $approvedUnpublishedProperty = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Approved Unpublished Listing',
            'property_type' => 'duplex',
            'rent_amount' => 900000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => false,
        ]);

        $liveProperty = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Live Published Listing',
            'property_type' => 'bungalow',
            'rent_amount' => 1200000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.properties'));

        $response->assertOk();
        $response->assertSee('Total listings');
        $response->assertSee('Pending review');
        $response->assertSee('Approved, unpublished');
        $response->assertSee('Live published');
        $response->assertSee('Pending Review Listing');
        $response->assertSee('Approved Unpublished Listing');
        $response->assertSee('Live Published Listing');
        $response->assertSee('For rent');
        $response->assertSee('Rent amount: '.Currency::format(900000, 'NGN'), false);
        $response->assertSee('Rent amount: '.Currency::format(1200000, 'NGN'), false);
        $response->assertSee('href="'.route('landlord.properties.create').'"', false);
        $response->assertSee('href="'.route('landlord.properties.edit', $pendingProperty).'"', false);
        $response->assertSee('href="'.route('landlord.properties.edit', $approvedUnpublishedProperty).'"', false);
        $response->assertSee('href="'.route('landlord.properties.edit', $liveProperty).'"', false);
        $response->assertSee('href="'.route('properties.show', $liveProperty).'"', false);
    }

    public function test_landlord_properties_page_has_clear_first_listing_empty_state(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $response = $this->actingAs($landlord)->get(route('landlord.properties'));

        $response->assertOk();
        $response->assertSee('You have not created any properties yet.');
        $response->assertSee('Create your first listing to start the landlord review workflow.');
        $response->assertDontSee('No properties submitted yet.');
    }

    public function test_verified_landlord_can_access_landlord_inspection_requests_page(): void
    {
        $landlord = $this->createLandlord();
        $this->createInspectionRequestForLandlord($landlord);

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Inspection Requests');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
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

    public function test_landlord_inspection_request_index_still_renders_when_status_history_table_is_missing(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        Schema::dropIfExists('inspection_request_status_histories');

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee('Inspection Requests');
        $response->assertSee($inspectionRequest->property->title);
        $response->assertDontSee('Inspection request data is not available yet.');
    }

    public function test_landlord_inspection_request_detail_renders_unavailable_state_when_inspection_requests_table_is_missing(): void
    {
        $landlord = $this->createLandlord();

        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', ['inspectionRequestId' => 1]));

        $response->assertOk();
        $response->assertSee('Inspection request detail data is not available yet.');
    }

    public function test_landlord_inspection_request_detail_renders_unavailable_state_when_inspection_request_status_histories_table_is_missing(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        Schema::dropIfExists('inspection_request_status_histories');

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]));

        $response->assertOk();
        $response->assertSee('Inspection request detail data is not available yet.');
    }

    public function test_unverified_landlord_is_redirected_to_email_verification_notice_for_landlord_pages(): void
    {
        $landlord = $this->createLandlord(verified: false);

        $response = $this->actingAs($landlord)->get(route('landlord.profile'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_landlord_can_update_own_profile(): void
    {
        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        Livewire::test(LandlordProfilePage::class)
            ->set('accountPhone', '08011112222')
            ->set('businessName', 'Akure Homes')
            ->set('residentialAddress', 'No. 4 Ijapo Estate, Akure')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('whatsappNumber', '08033334444')
            ->set('occupationOrBusiness', 'Property manager')
            ->set('shortBioOrNotes', 'Focused on clean and reliable rentals.')
            ->call('save');

        $landlord->refresh();
        $profile = $landlord->landlordProfile()->first();

        $this->assertSame('08011112222', $landlord->phone);
        $this->assertSame('Akure Homes', $profile->business_name);
        $this->assertSame('No. 4 Ijapo Estate, Akure', $profile->address);
        $this->assertSame('08033334444', $profile->whatsapp_number);
    }

    public function test_landlord_can_save_profile_picture_upload(): void
    {
        Storage::fake('public');

        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        Livewire::test(LandlordProfilePage::class)
            ->set('profilePicture', UploadedFile::fake()->image('profile-avatar.jpg'))
            ->call('save');

        $landlord->refresh();

        $this->assertNotNull($landlord->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($landlord->avatar_path));
    }

    public function test_landlord_can_replace_and_remove_profile_picture(): void
    {
        Storage::fake('public');

        $landlord = $this->createLandlord();
        $oldPath = 'profile-pictures/existing-avatar.jpg';
        Storage::disk('public')->put($oldPath, 'old-avatar');
        $landlord->forceFill([
            'avatar_path' => $oldPath,
        ])->save();

        $this->actingAs($landlord);

        Livewire::test(LandlordProfilePage::class)
            ->set('profilePicture', UploadedFile::fake()->image('replacement-avatar.jpg'))
            ->call('save');

        $landlord->refresh();
        $replacementPath = $landlord->avatar_path;

        $this->assertNotNull($replacementPath);
        $this->assertNotSame($oldPath, $replacementPath);
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
        $this->assertTrue(Storage::disk('public')->exists($replacementPath));

        Livewire::test(LandlordProfilePage::class)
            ->call('removeProfilePicture');

        $landlord->refresh();

        $this->assertNull($landlord->avatar_path);
        $this->assertFalse(Storage::disk('public')->exists($replacementPath));
    }

    public function test_landlord_can_upload_a_landlord_verification_document(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        $file = UploadedFile::fake()->create('national-id.pdf', 400, 'application/pdf');

        Livewire::test(LandlordDocuments::class)
            ->set('documentType', 'national_id')
            ->set('document', $file)
            ->call('upload')
            ->assertRedirect(route('landlord.documents'));

        $document = $landlord->landlordProfile->documents()->first();

        $this->assertNotNull($document);
        $this->assertSame('national_id', $document->document_type);
        $this->assertSame('pending', $document->review_status);
        $this->assertNotNull($document->file_size);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));

        $response = $this->actingAs($landlord)->get(route('landlord.documents'));

        $response->assertOk();
        $response->assertSee('Verification document uploaded successfully.');
        $response->assertSee('national-id.pdf');
        $response->assertSee('Pending');
    }

    public function test_landlord_dashboard_document_blocker_clears_after_document_upload(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();

        $beforeResponse = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $beforeResponse->assertOk();
        $beforeResponse->assertSee('Upload verification documents');
        $beforeResponse->assertSee('Your verification queue stays blocked until at least one document is uploaded.');

        Livewire::actingAs($landlord)
            ->test(LandlordDocuments::class)
            ->set('documentType', 'national_id')
            ->set('document', UploadedFile::fake()->create('dashboard-id.pdf', 400, 'application/pdf'))
            ->call('upload')
            ->assertRedirect(route('landlord.documents'));

        $afterResponse = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $afterResponse->assertOk();
        $afterResponse->assertDontSee('Your verification queue stays blocked until at least one document is uploaded.');
        $afterResponse->assertSee('Latest status:');
        $afterResponse->assertSee('Pending');
    }

    public function test_invalid_landlord_document_type_is_rejected(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        Livewire::test(LandlordDocuments::class)
            ->set('documentType', 'not_real')
            ->set('document', UploadedFile::fake()->create('national-id.pdf', 400, 'application/pdf'))
            ->call('upload')
            ->assertHasErrors(['documentType']);
    }

    public function test_uploaded_landlord_document_appears_on_admin_landlord_review_page(): void
    {
        Storage::fake('local');

        $admin = $this->createAdmin();
        $landlord = $this->createLandlord();

        $this->actingAs($landlord);

        Livewire::test(LandlordDocuments::class)
            ->set('documentType', 'national_id')
            ->set('document', UploadedFile::fake()->create('review-page-id.pdf', 400, 'application/pdf'))
            ->call('upload')
            ->assertRedirect(route('landlord.documents'));

        $response = $this->actingAs($admin)->get(route('admin.landlords.show', $landlord->landlordProfile));

        $response->assertOk();
        $response->assertSee('review-page-id.pdf');
        $response->assertSee('National Id');
        $response->assertSee('Pending');
    }

    public function test_landlord_shared_profile_edit_route_redirects_to_landlord_shell_profile(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('profile.edit'));

        $response->assertRedirect(route('landlord.profile'));
    }

    public function test_landlord_verification_document_persistence_falls_back_to_stored_file_size_when_temp_metadata_lookup_fails(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();
        $profile = $landlord->landlordProfile;
        $fileContents = 'landlord verification fallback body';
        $upload = new class($fileContents)
        {
            public function __construct(private readonly string $contents)
            {
            }

            public function store(string $directory, string $disk): string
            {
                $path = $directory.'/verification-id.pdf';
                Storage::disk($disk)->put($path, $this->contents);

                return $path;
            }

            public function getClientOriginalName(): string
            {
                return 'verification-id.pdf';
            }

            public function getMimeType(): string
            {
                return 'application/pdf';
            }

            public function getClientMimeType(): string
            {
                return 'application/pdf';
            }

            public function getSize(): int
            {
                throw new \RuntimeException('Temporary upload metadata is unavailable.');
            }
        };

        $component = new class extends LandlordDocuments
        {
            public function persistVerificationDocumentForTest(LandlordProfile $profile, string $documentType, mixed $uploadedFile): void
            {
                $this->storeVerificationDocument($profile, $documentType, $uploadedFile);
            }
        };

        $component->persistVerificationDocumentForTest($profile, 'national_id', $upload);

        $document = $profile->documents()->sole();

        $this->assertSame(strlen($fileContents), $document->file_size);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    public function test_landlord_can_create_a_property(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);
        session($this->completedTermsGateSession('listing-terms:create'));

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Clean Two Bedroom Apartment in Alagbaka')
            ->set('listingIntent', 'for_rent')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '850000')
            ->set('cautionFee', '150000')
            ->set('serviceCharge', '25000')
            ->set('description', 'Spacious apartment close to the main road.')
            ->set('bedrooms', 2)
            ->set('bathrooms', 2)
            ->set('toilets', 2)
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Alagbaka')
            ->set('street', 'Oda Road')
            ->set('landmark', 'Near Shoprite')
            ->set('images', [UploadedFile::fake()->image('front-view.jpg')])
            ->set('propertyDocumentType', 'ownership_proof')
            ->set('documents', [UploadedFile::fake()->create('ownership.pdf', 300, 'application/pdf')])
            ->call('addDocumentBatch')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertRedirect(route('landlord.properties'));

        $property = Property::query()->first();

        $this->assertNotNull($property);
        $this->assertSame($landlord->id, $property->landlord_id);
        $this->assertSame('pending_review', $property->status);
        $this->assertSame('for_rent', $property->listing_intent);
        $this->assertSame('Akure South', $property->lga);
        $this->assertSame('Alagbaka', $property->area);
        $this->assertSame('Oda Road', $property->street);
        $this->assertSame('Near Shoprite', $property->landmark);
        $this->assertSame('150000.00', number_format((float) $property->caution_fee, 2, '.', ''));
        $this->assertSame('25000.00', number_format((float) $property->service_charge, 2, '.', ''));
        $this->assertCount(1, $property->images);
        $this->assertCount(1, $property->documents);
        $this->assertTrue(Storage::disk('public')->exists($property->images->first()->image_path));
        $this->assertTrue(Storage::disk('local')->exists($property->documents->first()->file_path));
    }

    public function test_self_contain_property_type_sets_sensible_room_defaults(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('propertyType', 'self_contain')
            ->assertSet('bedrooms', 1)
            ->assertSet('bathrooms', 1)
            ->assertSet('toilets', 1)
            ->assertSet('showsBedroomField', true);
    }

    public function test_shop_property_type_hides_bedroom_count_and_clears_bedrooms(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('bedrooms', 3)
            ->set('propertyType', 'shop')
            ->assertSet('bedrooms', null)
            ->assertSet('showsBedroomField', false);
    }

    public function test_landlord_can_update_property_with_valid_lga_and_address_fields(): void
    {
        $landlord = $this->createLandlord();
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Editable Property',
            'listing_intent' => 'for_rent',
            'property_type' => 'flat',
            'rent_amount' => 650000,
            'caution_fee' => 50000,
            'service_charge' => 15000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Ijapo',
            'street' => null,
            'landmark' => null,
            'status' => 'pending_review',
        ]);

        $this->actingAs($landlord);
        session($this->completedTermsGateSession('listing-terms:property:'.$property->getKey()));

        Livewire::test(LandlordPropertyEdit::class, ['property' => $property])
            ->set('title', 'Updated Editable Property')
            ->set('listingIntent', 'for_sale')
            ->set('lga', 'Akure North')
            ->set('propertyType', 'bungalow')
            ->set('area', 'Oba Ile')
            ->set('street', 'Airport Road')
            ->set('landmark', 'Near FUTA Junction')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertRedirect(route('landlord.properties'));

        $property->refresh();

        $this->assertSame('Updated Editable Property', $property->title);
        $this->assertSame('for_sale', $property->listing_intent);
        $this->assertSame('bungalow', $property->property_type);
        $this->assertSame('Akure North', $property->lga);
        $this->assertSame('Oba Ile', $property->area);
        $this->assertSame('Airport Road', $property->street);
        $this->assertSame('Near FUTA Junction', $property->landmark);
    }

    public function test_invalid_property_type_is_rejected(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Invalid Property Type Example')
            ->set('propertyType', 'castle')
            ->set('rentAmount', '400000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Ijapo')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertHasErrors(['propertyType']);
    }

    public function test_invalid_listing_intent_is_rejected(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Invalid Listing Intent Example')
            ->set('listingIntent', 'short_let')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '400000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Ijapo')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertHasErrors(['listingIntent']);
    }

    public function test_invalid_lga_is_rejected(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Invalid LGA Example')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '400000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Not A Real LGA')
            ->set('area', 'Ijapo')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertHasErrors(['lga']);
    }

    public function test_invalid_property_document_type_is_rejected(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Invalid Property Document Type Example')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '400000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Ijapo')
            ->set('propertyDocumentType', 'bad_document_type')
            ->set('documents', [UploadedFile::fake()->create('ownership.pdf', 300, 'application/pdf')])
            ->call('addDocumentBatch')
            ->assertHasErrors(['propertyDocumentType']);
    }

    public function test_selected_images_can_be_removed_before_save(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        $component = Livewire::test(LandlordPropertyCreate::class)
            ->set('images', [
                UploadedFile::fake()->image('front-view.jpg'),
                UploadedFile::fake()->image('living-room.jpg'),
            ])
            ->call('removeSelectedImage', 0);

        $this->assertCount(1, $component->instance()->images);
        $this->assertSame('living-room.jpg', $component->instance()->images[0]->getClientOriginalName());
    }

    public function test_selected_documents_can_be_removed_before_save(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        $component = Livewire::test(LandlordPropertyCreate::class)
            ->set('documents', [
                UploadedFile::fake()->create('ownership.pdf', 300, 'application/pdf'),
                UploadedFile::fake()->create('utility-bill.pdf', 200, 'application/pdf'),
            ])
            ->call('removeSelectedDocument', 0);

        $this->assertCount(1, $component->instance()->documents);
        $this->assertSame('utility-bill.pdf', $component->instance()->documents[0]->getClientOriginalName());
    }

    public function test_property_document_batches_save_under_their_selected_types(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);
        session($this->completedTermsGateSession('listing-terms:create'));

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Typed Document Batch Property')
            ->set('listingIntent', 'for_sale')
            ->set('propertyType', 'bungalow')
            ->set('rentAmount', '18000000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Alagbaka')
            ->set('propertyDocumentType', 'ownership_proof')
            ->set('documents', [UploadedFile::fake()->create('ownership-proof.pdf', 300, 'application/pdf')])
            ->call('addDocumentBatch')
            ->set('propertyDocumentType', 'utility_bill')
            ->set('documents', [UploadedFile::fake()->create('power-bill.pdf', 200, 'application/pdf')])
            ->call('addDocumentBatch')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertRedirect(route('landlord.properties'));

        $property = Property::query()->sole();

        $this->assertSame('for_sale', $property->listing_intent);
        $this->assertEqualsCanonicalizing(
            ['ownership_proof', 'utility_bill'],
            $property->documents()->pluck('document_type')->all()
        );
    }

    public function test_too_many_property_uploads_are_rejected(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        $images = collect(range(1, 11))
            ->map(fn (int $index) => UploadedFile::fake()->image("image-{$index}.jpg"))
            ->all();

        $documents = collect(range(1, 5))
            ->map(fn (int $index) => UploadedFile::fake()->create("document-{$index}.pdf", 200, 'application/pdf'))
            ->all();

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Too Many Uploads Example')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '400000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Ijapo')
            ->set('images', $images)
            ->set('documents', $documents)
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertHasErrors(['images']);
    }

    public function test_property_document_persistence_falls_back_to_stored_file_size_when_temp_metadata_lookup_fails(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Fallback Upload Property',
            'property_type' => 'flat',
            'rent_amount' => 500000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => 'pending_review',
        ]);

        $fileContents = 'fallback metadata size body';
        $storedFiles = [];
        $upload = new class($fileContents)
        {
            public function __construct(private readonly string $contents)
            {
            }

            public function store(string $directory, string $disk): string
            {
                $path = $directory.'/ownership-proof.pdf';
                Storage::disk($disk)->put($path, $this->contents);

                return $path;
            }

            public function getClientOriginalName(): string
            {
                return 'ownership-proof.pdf';
            }

            public function getMimeType(): string
            {
                return 'application/pdf';
            }

            public function getClientMimeType(): string
            {
                return 'application/pdf';
            }

            public function getSize(): int
            {
                throw new \RuntimeException('Temporary upload metadata is unavailable.');
            }
        };

        $formHarness = new class
        {
            use InteractsWithPropertyForm {
                storePropertyDocuments as public persistDocuments;
            }

            public array $documentBatches = [];
        };

        $formHarness->documentBatches = [[
            'type' => 'ownership_proof',
            'files' => [$upload],
        ]];
        $formHarness->persistDocuments($property, $storedFiles);

        $document = PropertyDocument::query()->sole();

        $this->assertSame(strlen($fileContents), $document->file_size);
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
    }

    public function test_landlord_cannot_access_another_landlords_property_edit_page(): void
    {
        $owner = $this->createLandlord();
        $otherLandlord = $this->createLandlord(email: 'other@example.com');

        $property = Property::create([
            'landlord_id' => $owner->id,
            'title' => 'Owner Property',
            'property_type' => 'flat',
            'rent_amount' => 500000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Ijapo',
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($otherLandlord)->get(route('landlord.properties.edit', $property));

        $response->assertForbidden();
    }

    public function test_landlord_can_access_their_own_inspection_request_detail(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', $inspectionRequest));

        $response->assertOk();
        $response->assertSee($inspectionRequest->property->title);
        $response->assertSee($inspectionRequest->tenant->name);
        $response->assertDontSee($inspectionRequest->tenant->email);
        $response->assertSee('Waiting for scheduling');
        $response->assertSee('Note for admin');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_landlord_can_see_safe_outcome_visibility_without_tenant_contact_details(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        $inspectionRequest->update([
            'status' => 'completed',
            'outcome_type' => 'follow_up_needed',
            'outcome_notes' => 'A follow-up visit is needed after access is confirmed.',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', $inspectionRequest));

        $response->assertOk();
        $response->assertSee('Follow-up needed');
        $response->assertSee('A follow-up visit is needed after access is confirmed.');
        $response->assertDontSee($inspectionRequest->tenant->email);
    }

    public function test_landlord_does_not_see_outcome_summary_on_non_completed_requests(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        $inspectionRequest->update([
            'status' => 'scheduled',
            'outcome_type' => null,
            'outcome_notes' => null,
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', $inspectionRequest));

        $response->assertOk();
        $response->assertSee('Shown after admin records the result.');
        $response->assertDontSee('Outcome notes');
        $response->assertDontSee($inspectionRequest->tenant->email);
    }

    public function test_landlord_cannot_access_another_landlords_inspection_request_detail(): void
    {
        $owner = $this->createLandlord();
        $otherLandlord = $this->createLandlord(email: 'other-owner@example.com');
        $inspectionRequest = $this->createInspectionRequestForLandlord($owner);

        $response = $this->actingAs($otherLandlord)->get(route('landlord.inspection-requests.show', $inspectionRequest));

        $response->assertNotFound();
    }

    public function test_landlord_inspection_request_index_only_shows_their_own_requests(): void
    {
        $landlord = $this->createLandlord();
        $otherLandlord = $this->createLandlord(email: 'other-list@example.com');

        $ownRequest = $this->createInspectionRequestForLandlord($landlord);
        $otherRequest = $this->createInspectionRequestForLandlord($otherLandlord, propertyTitle: 'Other Landlord Listing');

        $response = $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'));

        $response->assertOk();
        $response->assertSee($ownRequest->property->title);
        $response->assertDontSee($otherRequest->property->title);
    }

    public function test_landlord_can_save_a_coordination_note_on_their_inspection_request(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('landlordNote', 'The gate will be open after 2pm and keys are with the caretaker.')
            ->call('saveLandlordNote');

        $inspectionRequest->refresh();

        $this->assertSame('The gate will be open after 2pm and keys are with the caretaker.', $inspectionRequest->landlord_note);
    }

    public function test_landlord_note_saving_fails_safely_when_inspection_requests_table_is_missing(): void
    {
        $landlord = $this->createLandlord();

        Schema::dropIfExists('inspection_requests');

        $this->actingAs($landlord);

        Livewire::test(LandlordInspectionRequestShow::class, ['inspectionRequestId' => 1])
            ->set('landlordNote', 'This should not persist.')
            ->call('saveLandlordNote')
            ->assertSee('Inspection request actions are not available yet in this environment.');
    }

    public function test_landlord_note_saving_fails_safely_when_inspection_request_status_histories_table_is_missing(): void
    {
        $landlord = $this->createLandlord();
        $inspectionRequest = $this->createInspectionRequestForLandlord($landlord);

        Schema::dropIfExists('inspection_request_status_histories');

        $this->actingAs($landlord);

        Livewire::test(LandlordInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('landlordNote', 'This should not persist.')
            ->call('saveLandlordNote')
            ->assertSee('Inspection request actions are not available yet in this environment.');
    }

    public function test_landlord_navigation_includes_inspection_requests_link(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('landlord.profile'));

        $response->assertOk();
        $response->assertSee(route('landlord.inspection-requests.index'));
    }

    public function test_admin_verification_status_changes_reflect_on_landlord_pages(): void
    {
        $admin = $this->createAdmin();
        $landlord = $this->createLandlord();
        $landlordProfile = $landlord->landlordProfile;

        $this->actingAs($admin);

        Livewire::test(AdminLandlordShow::class, ['landlordProfile' => $landlordProfile])
            ->set('reviewNotes', 'Verification is approved.')
            ->call('changeStatus', 'approved');

        $dashboardResponse = $this->actingAs($landlord)->get(route('landlord.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Approved');
        $dashboardResponse->assertSee('href="'.route('landlord.properties.create').'"', false);

        $profileResponse = $this->actingAs($landlord)->get(route('landlord.profile'));
        $profileResponse->assertOk();
        $profileResponse->assertSee('Approved');

        $this->actingAs($admin);

        Livewire::test(AdminLandlordShow::class, ['landlordProfile' => $landlordProfile->fresh()])
            ->set('reviewNotes', 'Verification is suspended.')
            ->call('changeStatus', 'suspended');

        $suspendedDashboardResponse = $this->actingAs($landlord)->get(route('landlord.dashboard'));
        $suspendedDashboardResponse->assertOk();
        $suspendedDashboardResponse->assertSee('Suspended');
        $suspendedDashboardResponse->assertDontSee('href="'.route('landlord.properties.create').'"', false);
        $suspendedDashboardResponse->assertSee('Property creation is unavailable until the admin team restores your verification status.');

        $propertiesResponse = $this->actingAs($landlord)->get(route('landlord.properties'));
        $propertiesResponse->assertOk();
        $propertiesResponse->assertSee('Complete Verification');
        $propertiesResponse->assertSee('Property creation is unavailable until the admin team restores your verification status.');
        $propertiesResponse->assertDontSee('href="'.route('landlord.properties.create').'"', false);
    }

    public function test_unverified_landlord_cannot_create_a_property(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $landlord = $this->createLandlord();

        $createPageResponse = $this->actingAs($landlord)->get(route('landlord.properties.create'));

        $createPageResponse->assertOk();
        $createPageResponse->assertSee('Property creation is locked until your landlord verification is approved.');
        $createPageResponse->assertSee('Your landlord verification must be approved before you can create a property.');

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Blocked Property Submission')
            ->set('listingIntent', 'for_rent')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '850000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Alagbaka')
            ->set('hasAcceptedListingTerms', true)
            ->call('save')
            ->assertSee('Your landlord verification must be approved before you can create a property.');

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_landlord_listing_terms_must_be_accepted_before_saving_property(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyCreate::class)
            ->set('title', 'Terms Required Listing')
            ->set('listingIntent', 'for_rent')
            ->set('propertyType', 'flat')
            ->set('rentAmount', '850000')
            ->set('city', 'Akure')
            ->set('state', 'Ondo')
            ->set('lga', 'Akure South')
            ->set('area', 'Alagbaka')
            ->call('save')
            ->assertHasErrors(['hasAcceptedListingTerms']);

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_landlord_listing_terms_must_be_accepted_before_updating_property(): void
    {
        $landlord = $this->createLandlord();
        $this->approveLandlord($landlord);

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Editable Terms Listing',
            'listing_intent' => 'for_rent',
            'property_type' => 'flat',
            'rent_amount' => 650000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Ijapo',
            'status' => 'pending_review',
        ]);

        $this->actingAs($landlord);

        Livewire::test(LandlordPropertyEdit::class, ['property' => $property])
            ->set('title', 'Updated Without Terms')
            ->call('save')
            ->assertHasErrors(['hasAcceptedListingTerms']);

        $property->refresh();

        $this->assertSame('Editable Terms Listing', $property->title);
    }

    protected function createLandlord(?string $email = null, bool $verified = true): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => $verified ? now() : null,
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

    protected function createAdmin(): User
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $admin->assignRole('admin');

        return $admin;
    }

    protected function approveLandlord(User $landlord): void
    {
        $landlord->landlordProfile()->update([
            'verification_status' => 'approved',
            'verified_at' => now(),
            'verified_by' => null,
        ]);
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

    protected function createInspectionRequestForLandlord(User $landlord, ?User $tenant = null, string $propertyTitle = 'Landlord Inspection Listing'): InspectionRequest
    {
        $tenant ??= $this->createTenant();

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => $propertyTitle,
            'property_type' => 'flat',
            'rent_amount' => 700000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => 'approved',
            'is_verified' => true,
        ]);

        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Late morning preferred',
            'message' => 'Please confirm if the compound is accessible.',
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
