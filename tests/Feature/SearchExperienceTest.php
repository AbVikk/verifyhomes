<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SearchExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('tenant', 'web');
        Role::findOrCreate('landlord', 'web');
    }

    public function test_admin_search_returns_matching_property(): void
    {
        $admin = $this->createRoleUser('admin');
        $landlord = $this->createRoleUser('landlord');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Searchable Listing',
            'property_type' => 'flat',
            'listing_intent' => 'for_rent',
            'rent_amount' => 650000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => PublicPropertyVisibility::APPROVED_STATUS,
            'is_verified' => true,
            'is_published' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.search', ['q' => 'Searchable']));

        $response->assertOk();
        $response->assertSee('Matching listings');
        $response->assertSee($property->title);
    }

    public function test_landlord_search_returns_own_listing(): void
    {
        $landlord = $this->createRoleUser('landlord');

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Landlord Search Listing',
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
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.search', ['q' => 'Landlord Search']));

        $response->assertOk();
        $response->assertSee('Matching listings');
        $response->assertSee($property->title);
    }

    protected function createRoleUser(string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
