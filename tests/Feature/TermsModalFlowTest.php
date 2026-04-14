<?php

namespace Tests\Feature;

use App\Models\InspectionRequest;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TermsModalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_property_terms_flow_renders_modal_checkbox_and_hidden_form_sync(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord(), [
            'title' => 'Modal Terms Property',
        ]);

        $response = $this->actingAs($tenant)->get(route('properties.show', $property));

        $response->assertOk();
        $response->assertSee('Open the terms to read and accept them in the modal.');
        $response->assertSee('data-terms-gate-hidden-input', false);
        $response->assertSee('data-terms-gate-modal-content="inspection-request:property:'.$property->id.'"', false);
        $response->assertSee('data-terms-gate-modal-warning', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-terms-gate-checkbox'));
    }

    public function test_tenant_payment_terms_flow_renders_modal_checkbox_and_hidden_form_sync(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord('tenant-payment-landlord@example.com'));
        $inspectionRequest = $this->createInspectionRequest($tenant, $property);

        $response = $this->actingAs($tenant)->get(route('tenant.inspection-requests.show', [
            'inspectionRequestId' => $inspectionRequest->getKey(),
        ]));

        $response->assertOk();
        $response->assertSee('Open the terms to read and accept them in the modal.');
        $response->assertSee('data-terms-gate-hidden-input', false);
        $response->assertSee('data-terms-gate-modal-content="inspection-payment:request:'.$inspectionRequest->id.'"', false);
        $response->assertSee('data-terms-gate-modal-warning', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-terms-gate-checkbox'));
    }

    public function test_landlord_create_and_edit_terms_flows_render_modal_checkbox_and_hidden_form_sync(): void
    {
        $landlord = $this->createLandlord('landlord-terms@example.com');
        $property = $this->createProperty($landlord, [
            'title' => 'Landlord Edit Terms Property',
        ]);

        $createResponse = $this->actingAs($landlord)->get(route('landlord.properties.create'));

        $createResponse->assertOk();
        $createResponse->assertSee('Open the terms to read and accept them in the modal.');
        $createResponse->assertSee('data-terms-gate-hidden-input', false);
        $createResponse->assertSee('data-terms-gate-modal-content="listing-terms:create"', false);
        $createResponse->assertSee('data-terms-gate-modal-warning', false);
        $this->assertSame(1, substr_count($createResponse->getContent(), 'data-terms-gate-checkbox'));

        $editResponse = $this->actingAs($landlord)->get(route('landlord.properties.edit', $property));

        $editResponse->assertOk();
        $editResponse->assertSee('Open the terms to read and accept them in the modal.');
        $editResponse->assertSee('data-terms-gate-hidden-input', false);
        $editResponse->assertSee('data-terms-gate-modal-content="listing-terms:property:'.$property->id.'"', false);
        $editResponse->assertSee('data-terms-gate-modal-warning', false);
        $this->assertSame(1, substr_count($editResponse->getContent(), 'data-terms-gate-checkbox'));
    }

    public function test_terms_gate_completion_succeeds_once_the_full_ten_seconds_have_elapsed(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord('threshold-landlord@example.com'));
        $gate = 'inspection-request:property:'.$property->id;
        $now = Carbon::create(2026, 3, 31, 12, 0, 10, 'UTC');

        Carbon::setTestNow($now);

        $response = $this->actingAs($tenant)
            ->withSession([
                $this->termsGateSessionKey($gate) => [
                    'opened_at' => $now->copy()->subMilliseconds(10000)->toIso8601String(),
                    'opened_at_ms' => $now->copy()->subMilliseconds(10000)->getTimestampMs(),
                    'completed_at' => null,
                ],
            ])
            ->postJson(route('terms-gates.complete'), ['gate' => $gate]);

        $response->assertOk()->assertJson([
            'gate' => $gate,
            'seconds_remaining' => 0,
            'completed' => true,
        ]);

        Carbon::setTestNow();
    }

    public function test_terms_gate_completion_still_rejects_true_early_attempts(): void
    {
        $tenant = $this->createTenant();
        $property = $this->createProperty($this->createLandlord('early-landlord@example.com'));
        $gate = 'inspection-request:property:'.$property->id;
        $now = Carbon::create(2026, 3, 31, 12, 0, 10, 'UTC');

        Carbon::setTestNow($now);

        $response = $this->actingAs($tenant)
            ->withSession([
                $this->termsGateSessionKey($gate) => [
                    'opened_at' => $now->copy()->subMilliseconds(9999)->toIso8601String(),
                    'opened_at_ms' => $now->copy()->subMilliseconds(9999)->getTimestampMs(),
                    'completed_at' => null,
                ],
            ])
            ->postJson(route('terms-gates.complete'), ['gate' => $gate]);

        $response->assertStatus(422)->assertJson([
            'message' => 'Please read the terms before continuing.',
            'seconds_remaining' => 1,
        ]);

        Carbon::setTestNow();
    }

    protected function createRoleUser(string $role, ?string $email = null): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createTenant(?string $email = null): User
    {
        $tenant = $this->createRoleUser('tenant', $email);

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        return $tenant;
    }

    protected function createLandlord(?string $email = null): User
    {
        $landlord = $this->createRoleUser('landlord', $email);

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'approved',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        return $landlord;
    }

    protected function createProperty(User $landlord, array $overrides = []): Property
    {
        return Property::create(array_merge([
            'landlord_id' => $landlord->id,
            'title' => 'Terms Modal Property',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'pricing_model' => 'tenant_price',
            'pricing_input_amount' => 1000000,
            'rent_amount' => 1000000,
            'landlord_net_amount' => 800000,
            'platform_fee_percentage' => 20,
            'total_units' => 1,
            'occupied_units' => 0,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'description' => 'Terms modal test property.',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides));
    }

    protected function createInspectionRequest(User $tenant, Property $property): InspectionRequest
    {
        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Morning',
            'message' => 'Please confirm access.',
        ]);

        $inspectionRequest->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'requested',
            'changed_by' => null,
            'notes' => null,
        ]);

        return $inspectionRequest;
    }

    protected function termsGateSessionKey(string $gate): string
    {
        return 'terms_gates.'.md5($gate);
    }
}
