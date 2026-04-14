<?php

namespace Tests\Feature;

use App\Livewire\Landlord\InspectionRequests\Show as LandlordInspectionRequestShow;
use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PaymentTransactionRecorder;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandlordWorkspaceActionabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_dashboard_surfaces_clear_next_actions_and_follow_through_links(): void
    {
        $landlord = $this->createLandlord();

        $pendingProperty = $this->createProperty($landlord, [
            'title' => 'Pending Review Listing',
            'status' => 'pending_review',
            'is_verified' => false,
            'is_published' => false,
        ]);

        $liveProperty = $this->createProperty($landlord, [
            'title' => 'Live Listing',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);

        $inspectionRequest = $this->createInspectionRequest($landlord, $liveProperty, [
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $response->assertOk();
        $response->assertSee('What to do next');
        $response->assertSee('Follow through on the latest inspection request');
        $response->assertSee('Manage Inspection Requests');
        $response->assertSee('Pending Review Listing');
        $response->assertSee('Live Listing');
        $response->assertSee('Admin has scheduled the visit. Keep access and the property ready.');
        $response->assertSee('href="'.route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]).'"', false);
        $response->assertSee('href="'.route('landlord.properties.edit', $pendingProperty).'"', false);
    }

    public function test_landlord_properties_queue_surfaces_listing_state_and_next_actions_more_clearly(): void
    {
        $landlord = $this->createLandlord();

        $pendingProperty = $this->createProperty($landlord, [
            'title' => 'Queue Pending Listing',
            'rent_amount' => 900000,
            'status' => 'pending_review',
            'is_verified' => false,
            'is_published' => false,
        ]);

        $liveProperty = $this->createProperty($landlord, [
            'title' => 'Queue Live Listing',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);

        $this->createInspectionRequest($landlord, $liveProperty, [
            'status' => 'requested',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.properties'));

        $response->assertOk();
        $response->assertSee('Needs attention');
        $response->assertSee('Queue Pending Listing');
        $response->assertSee('Queue Live Listing');
        $response->assertSee('No images yet');
        $response->assertSee('No documents yet');
        $response->assertSee('1 open request');
        $response->assertSee('Next step');
        $response->assertSee('This listing is waiting for admin review.');
        $response->assertSee('Tenant activity is already open on this listing. Review the request queue and keep access details current.');
        $response->assertSee('900,000.00');
        $response->assertSee('href="'.route('landlord.inspection-requests.index').'"', false);
        $response->assertSee('href="'.route('properties.show', $liveProperty).'"', false);
        $response->assertSee('href="'.route('landlord.properties.edit', $pendingProperty).'"', false);
    }

    public function test_landlord_inspection_request_pages_surface_next_step_and_payment_awareness(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord, [
            'title' => 'Inspection Flow Listing',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);
        $inspectionRequest = $this->createInspectionRequest($landlord, $property, [
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        $transaction = PaymentTransactionRecorder::createPending([
            'payer_id' => $inspectionRequest->tenant_id,
            'property_id' => $property->id,
            'inspection_request_id' => $inspectionRequest->id,
            'transaction_type' => 'inspection_booking_fee',
            'gross_amount' => 5000,
            'status' => 'paid',
            'provider' => 'stub',
        ]);

        $indexResponse = $this->actingAs($landlord)->get(route('landlord.inspection-requests.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Booking fee verified');
        $indexResponse->assertSee('Visit booked. Fee confirmed. Keep access ready.');
        $indexResponse->assertSee($transaction->reference);

        $showResponse = $this->actingAs($landlord)->get(route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]));

        $showResponse->assertOk();
        $showResponse->assertSee('Next step');
        $showResponse->assertSee('Payment status');
        $showResponse->assertSee('Booking fee verified. Admin can move the request forward.');
        $showResponse->assertSee('Share access details or readiness notes with admin.');
        $showResponse->assertSee($transaction->reference);
        $showResponse->assertSee('Stub Gateway');
        $showResponse->assertSee('href="'.route('landlord.properties.edit', $property).'"', false);
    }

    public function test_landlord_can_still_save_a_coordination_note_after_follow_through_ui_upgrade(): void
    {
        $landlord = $this->createLandlord();
        $property = $this->createProperty($landlord, [
            'title' => 'Coordination Note Listing',
        ]);
        $inspectionRequest = $this->createInspectionRequest($landlord, $property);

        $this->actingAs($landlord);

        Livewire::test(LandlordInspectionRequestShow::class, ['inspectionRequest' => $inspectionRequest])
            ->set('landlordNote', 'Gate access is with the caretaker before 4pm.')
            ->call('saveLandlordNote');

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $inspectionRequest->id,
            'landlord_note' => 'Gate access is with the caretaker before 4pm.',
        ]);
    }

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? 'landlord@example.com',
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'approved',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

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

    protected function createProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Landlord Workspace Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 750000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    protected function createInspectionRequest(User $landlord, ?Property $property = null, array $overrides = []): InspectionRequest
    {
        $property ??= $this->createProperty($landlord);
        $tenant = $this->createTenant();

        $inspectionRequest = InspectionRequest::create(array_merge([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Afternoon works best',
            'message' => 'Please confirm access.',
        ], $overrides));

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => $inspectionRequest->status,
            'changed_by' => null,
            'notes' => null,
        ]);

        return $inspectionRequest;
    }
}
