<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantPropertyDiscoveryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_browse_page_shows_saved_state_and_request_actions_on_property_cards(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $tenant->savedProperties()->syncWithoutDetaching([$property->id]);

        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'preferred_time_note' => 'Morning works best',
            'message' => 'Please confirm access.',
        ]);

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'requested',
            'changed_by' => null,
            'notes' => null,
        ]);

        $response = $this->actingAs($tenant)->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee('Saved');
        $response->assertSee('Requested request active');
        $response->assertSee('Remove Saved');
        $response->assertSee('View Request');
        $response->assertSee('href="'.route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]).'"', false);
    }

    public function test_tenant_property_detail_page_surfaces_request_and_payment_context_more_clearly(): void
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

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk();
        $response->assertSee('Saved to your shortlist');
        $response->assertSee('View request');
        $response->assertSee('Your status');
        $response->assertSee('Inspection payment: Paid via Stub Gateway.');
        $response->assertSee('Inspection payment history');
        $response->assertSee($paymentTransaction->reference);
        $response->assertSee('href="'.route('tenant.payments.index', ['reference' => $paymentTransaction->reference]).'"', false);
    }

    public function test_tenant_property_detail_page_still_allows_new_request_when_none_exists(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createPublicProperty();

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk();
        $response->assertSee('Send request');
        $response->assertSee('Send your request here. VerifyHomes will handle scheduling.');
        $response->assertSee('Payment updates show here after checkout starts.');
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
            'title' => 'Tenant Discovery Property',
            'property_type' => 'flat',
            'rent_amount' => 780000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'landmark' => 'Near Shoprite',
            'description' => 'A tenant discovery journey test property.',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);
    }
}
