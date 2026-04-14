<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\LandlordRegistration;
use App\Livewire\Auth\TenantRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Register as Tenant');
        $response->assertSee('Register as Landlord');
    }

    public function test_tenants_can_register_from_the_livewire_flow(): void
    {
        Livewire::test(TenantRegistration::class)
            ->set('name', 'Tenant User')
            ->set('email', 'tenant@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertSame('tenant@example.com', auth()->user()->email);
        $this->assertTrue(auth()->user()->hasRole('tenant'));
        $this->assertNotNull(auth()->user()->tenantProfile);
    }

    public function test_landlords_can_register_from_the_livewire_flow(): void
    {
        Livewire::test(LandlordRegistration::class)
            ->set('name', 'Landlord User')
            ->set('email', 'landlord@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertSame('landlord@example.com', auth()->user()->email);
        $this->assertTrue(auth()->user()->hasRole('landlord'));
        $this->assertNotNull(auth()->user()->landlordProfile);
        $this->assertSame('pending', auth()->user()->landlordProfile->verification_status);
    }
}
