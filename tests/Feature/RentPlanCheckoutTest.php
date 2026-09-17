<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyRentPlan;
use App\Models\User;
use App\Support\PaymentCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentPlanCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_the_server_side_selected_plan_and_resets_a_different_pending_plan(): void
    {
        $tenant = User::factory()->create();
        $property = $this->property();
        $sixMonths = PropertyRentPlan::create(['property_id' => $property->id, 'period_months' => 6, 'amount' => 120000, 'is_active' => true]);
        $twelveMonths = PropertyRentPlan::create(['property_id' => $property->id, 'period_months' => 12, 'amount' => 200000, 'is_active' => true]);

        $checkout = app(PaymentCheckoutService::class);
        $first = $checkout->initiateRentPayment($property, $tenant, $twelveMonths->id);
        $same = $checkout->initiateRentPayment($property, $tenant, $twelveMonths->id);
        $replacement = $checkout->initiateRentPayment($property, $tenant, $sixMonths->id);

        $this->assertSame($first->id, $same->id);
        $this->assertSame('failed', $first->fresh()->status);
        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame('120000.00', $replacement->gross_amount);
        $this->assertSame(6, data_get($replacement->metadata, 'rental_period_months'));
        $this->assertSame($sixMonths->id, data_get($replacement->metadata, 'property_rent_plan_id'));
    }

    public function test_inactive_or_other_property_plan_cannot_be_used_and_legacy_property_still_works(): void
    {
        $tenant = User::factory()->create();
        $property = $this->property();
        $otherProperty = $this->property();
        $inactive = PropertyRentPlan::create(['property_id' => $property->id, 'period_months' => 6, 'amount' => 120000, 'is_active' => false]);
        $otherPlan = PropertyRentPlan::create(['property_id' => $otherProperty->id, 'period_months' => 6, 'amount' => 120000, 'is_active' => true]);
        $checkout = app(PaymentCheckoutService::class);

        $this->assertNull($checkout->initiateRentPayment($property, $tenant, $inactive->id));
        $this->assertNull($checkout->initiateRentPayment($property, $tenant, $otherPlan->id));

        $legacy = $checkout->initiateRentPayment($this->property(), $tenant);
        $this->assertSame('250000.00', $legacy->gross_amount);
        $this->assertSame(12, data_get($legacy->metadata, 'rental_period_months'));
    }

    private function property(): Property
    {
        return Property::create(['landlord_id' => User::factory()->create()->id, 'title' => 'Plan property '.fake()->uuid(), 'property_type' => 'flat', 'listing_intent' => 'for_rent', 'rent_amount' => 250000, 'lga' => 'Akure South', 'city' => 'Akure', 'state' => 'Ondo', 'area' => 'Alagbaka']);
    }
}
