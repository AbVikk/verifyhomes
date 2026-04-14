<?php

namespace Tests\Feature;

use App\Livewire\PublicProperties\Show as PublicPropertyShow;
use App\Models\InspectionRequest;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantSavedListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_saved_listings_page_surfaces_next_step_actions_for_saved_properties(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);

        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'scheduled',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm access.',
            'scheduled_at' => now()->addDay(),
        ]);

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'scheduled',
            'changed_by' => null,
            'notes' => null,
        ]);

        $paymentTransaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $tenant->id,
            'property_id' => $property->id,
            'inspection_request_id' => $inspectionRequest->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'paid',
            'provider' => 'stub',
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.saved-listings.index'));

        $response->assertOk();
        $response->assertSee('Your shortlisted properties');
        $response->assertSee('Publicly listed');
        $response->assertSee('Public listing live');
        $response->assertSee('Scheduled request active');
        $response->assertSee('Payment verified');
        $response->assertSee('View Request');
        $response->assertSee('View Payment');
        $response->assertSee($paymentTransaction->reference);
        $response->assertSee('href="'.route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]).'"', false);
        $response->assertSee('href="'.route('tenant.payments.index', ['reference' => $paymentTransaction->reference]).'"', false);
    }

    public function test_tenant_saved_listings_page_shows_unavailable_saved_listing_message(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);
        $property->update([
            'is_published' => false,
        ]);

        $response = $this->actingAs($tenant)->get(route('tenant.saved-listings.index'));

        $response->assertOk();
        $response->assertSee('No longer public');
        $response->assertSee('This listing is no longer available on the public property pages, but your saved history remains here until you remove it.');
    }

    public function test_fully_occupied_saved_listing_does_not_show_conflicting_public_availability_copy(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $property->update([
            'total_units' => 1,
            'occupied_units' => 1,
        ]);

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);

        $response = $this->actingAs($tenant)->get(route('tenant.saved-listings.index'));

        $response->assertOk();
        $response->assertSee('Fully occupied');
        $response->assertDontSee('Public listing live');
        $response->assertDontSee('Still available');
    }

    public function test_tenant_can_save_and_unsave_a_public_property(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $this->actingAs($tenant);

        Livewire::test(PublicPropertyShow::class, ['property' => $property])
            ->call('toggleSavedProperty')
            ->assertSet('isSavedByCurrentTenant', true)
            ->assertSee('Remove from saved');

        $this->assertDatabaseHas('saved_properties', [
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);

        Livewire::test(PublicPropertyShow::class, ['property' => $property])
            ->call('toggleSavedProperty')
            ->assertSet('isSavedByCurrentTenant', false)
            ->assertSee('Save listing');

        $this->assertDatabaseMissing('saved_properties', [
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
        ]);
    }

    public function test_tenant_property_detail_page_shows_saved_listing_action_and_reflects_saved_state(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $initialResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $initialResponse->assertOk();
        $initialResponse->assertSee('Save listing');
        $initialResponse->assertSee('Not saved yet');

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);

        $savedResponse = $this->actingAs($tenant)->get(route('properties.show', $property));

        $savedResponse->assertOk();
        $savedResponse->assertSee('Remove from saved');
        $savedResponse->assertSee('Saved to your shortlist');
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

    protected function createLandlord(): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
    }

    protected function createPublicProperty(): Property
    {
        return Property::create([
            'landlord_id' => $this->createLandlord()->id,
            'title' => 'Saved Listing Test Property',
            'property_type' => 'flat',
            'rent_amount' => 850000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'landmark' => 'Near Shoprite',
            'description' => 'A saved listing foundation test property.',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);
    }
}
