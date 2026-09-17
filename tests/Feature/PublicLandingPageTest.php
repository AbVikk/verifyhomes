<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_the_public_promise_and_valid_primary_calls_to_action(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Find verified homes.')
            ->assertSee('Inspect first.')
            ->assertSee('Pay safely.')
            ->assertSee('href="'.route('properties.index').'"', false)
            ->assertSee('href="'.route('register.landlord').'"', false)
            ->assertSee('href="'.route('support').'"', false)
            ->assertSee('href="'.route('terms').'"', false)
            ->assertSee('href="'.route('privacy').'"', false)
            ->assertSee(asset('assets/images/Untitled design(1).png'), false)
            ->assertSee(asset('assets/images/Untitled design(2).png'), false)
            ->assertSee('Apartment courtyard with balconies')
            ->assertSee('Modern apartment building framed by trees');
    }

    public function test_home_page_features_only_publicly_visible_properties(): void
    {
        $visibleProperty = $this->createProperty('Public Featured Home', true, true, 'approved');
        $unpublishedProperty = $this->createProperty('Unpublished Home', true, false, 'approved');
        $unverifiedProperty = $this->createProperty('Unverified Home', false, true, 'approved');
        $pendingProperty = $this->createProperty('Pending Home', false, false, 'pending_review');

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee($visibleProperty->title)
            ->assertSee('href="'.route('properties.show', $visibleProperty).'"', false)
            ->assertDontSee($unpublishedProperty->title)
            ->assertDontSee($unverifiedProperty->title)
            ->assertDontSee($pendingProperty->title);
    }

    public function test_home_page_has_a_polished_empty_featured_state_when_no_public_properties_exist(): void
    {
        $this->createProperty('Private Listing', true, false, 'approved');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Verified homes are being prepared')
            ->assertDontSee('Private Listing');
    }

    public function test_support_page_is_publicly_available(): void
    {
        $this->get(route('support'))
            ->assertOk()
            ->assertSee('VerifyHomes support')
            ->assertSee('How can we help?')
            ->assertSee('Account & verification')
            ->assertSee('Property inspections')
            ->assertSee('Landlord payouts')
            ->assertSee('How do I contact VerifyHomes support?')
            ->assertSee('How support works')
            ->assertSee('Sign in to contact support')
            ->assertSee('href="'.route('login', ['support' => 1]).'"', false);
    }

    public function test_authenticated_tenants_and_landlords_see_only_their_own_support_calls_to_action(): void
    {
        $tenant = $this->roleUser('tenant');
        $landlord = $this->roleUser('landlord');

        $this->actingAs($tenant)->get(route('support'))
            ->assertSee('Open my support requests')
            ->assertSee('href="'.route('tenant.support.index').'"', false)
            ->assertSee('href="'.route('tenant.support.create').'"', false)
            ->assertDontSee(route('landlord.support.index'), false);

        $this->actingAs($landlord)->get(route('support'))
            ->assertSee('Open my support requests')
            ->assertSee('href="'.route('landlord.support.index').'"', false)
            ->assertSee('href="'.route('landlord.support.create').'"', false)
            ->assertDontSee(route('tenant.support.index'), false);
    }

    public function test_terms_and_privacy_pages_are_publicly_available(): void
    {
        $this->get(route('terms'))->assertOk()->assertSee('Terms');
        $this->get(route('privacy'))->assertOk()->assertSee('Privacy');
    }

    private function createProperty(string $title, bool $isVerified, bool $isPublished, string $status): Property
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create();
        $landlord->assignRole('landlord');

        return Property::create([
            'landlord_id' => $landlord->id,
            'title' => $title,
            'description' => 'A property for public landing page coverage.',
            'listing_intent' => 'for_rent',
            'pricing_model' => 'tenant_facing_rent',
            'property_type' => 'flat',
            'rent_amount' => 450000,
            'pricing_input_amount' => 450000,
            'landlord_net_amount' => 360000,
            'platform_fee_percentage' => 20,
            'total_units' => 1,
            'occupied_units' => 0,
            'reserved_units' => 0,
            'bedrooms' => 2,
            'bathrooms' => 2,
            'toilets' => 2,
            'state' => 'Ondo',
            'lga' => 'Akure South',
            'city' => 'Akure',
            'area' => 'Alagbaka',
            'address_text' => 'Alagbaka, Akure',
            'is_verified' => $isVerified,
            'is_published' => $isPublished,
            'status' => $status,
        ]);
    }

    private function roleUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }
}
