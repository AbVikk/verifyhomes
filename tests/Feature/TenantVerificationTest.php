<?php

namespace Tests\Feature;

use App\Livewire\Tenant\Verification as TenantVerificationPage;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('staff', 'web');
        Role::findOrCreate('landlord', 'web');
        Role::findOrCreate('tenant', 'web');
    }

    public function test_review_step_masks_id_and_does_not_persist_before_final_submit(): void
    {
        $tenant = $this->tenant();
        $this->actingAs($tenant);

        Livewire::test(TenantVerificationPage::class)
            ->set('idType', 'nin')
            ->set('idNumber', '12345678901')
            ->set('idDocument', UploadedFile::fake()->image('id.jpg'))
            ->set('selfie', UploadedFile::fake()->image('selfie.jpg'))
            ->call('review')
            ->assertSet('reviewing', true)
            ->assertSee('*******8901')
            ->assertDontSee('12345678901');

        $this->assertSame('unverified', $tenant->tenantProfile->fresh()->verification_status);
        $this->assertNull($tenant->tenantProfile->fresh()->submitted_at);
    }

    public function test_final_submit_persists_private_files_only_after_review(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant();
        $this->actingAs($tenant);

        Livewire::test(TenantVerificationPage::class)
            ->set('idType', 'nin')
            ->set('idNumber', '12345678901')
            ->set('idDocument', UploadedFile::fake()->image('id.jpg'))
            ->set('selfie', UploadedFile::fake()->image('selfie.jpg'))
            ->call('review')
            ->call('submit');

        $profile = $tenant->tenantProfile->fresh();
        $this->assertSame('pending', $profile->verification_status);
        $this->assertTrue(Storage::disk('local')->exists($profile->id_document_path));
        $this->assertTrue(Storage::disk('local')->exists($profile->selfie_path));
        $this->assertNull($tenant->fresh()->avatar_path);
    }

    public function test_admin_review_uses_controlled_previews_without_raw_paths(): void
    {
        Storage::fake('local');
        $profile = $this->tenant()->tenantProfile;
        $profile->update(['verification_status' => 'pending', 'id_type' => 'nin', 'id_number' => '12345678901', 'id_document_path' => 'tenant-verification/id.jpg', 'selfie_path' => 'tenant-verification/selfie.jpg', 'submitted_at' => now()]);
        Storage::disk('local')->put($profile->id_document_path, UploadedFile::fake()->image('id.jpg')->getContent());
        Storage::disk('local')->put($profile->selfie_path, UploadedFile::fake()->image('selfie.jpg')->getContent());
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.tenants.show', $profile));

        $response->assertOk()
            ->assertSee('Verification selfie')
            ->assertSee(route('admin.tenants.verification.preview', [$profile, 'id']))
            ->assertSee(route('admin.tenants.verification.preview', [$profile, 'selfie']))
            ->assertDontSee('tenant-verification/id.jpg')
            ->assertDontSee('tenant-verification/selfie.jpg');

        $this->actingAs($this->userWithRole('landlord'))->get(route('admin.tenants.verification.preview', [$profile, 'selfie']))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.tenants.verification.preview', [$profile, 'selfie']))->assertForbidden();
    }

    private function tenant(): User
    {
        $tenant = $this->userWithRole('tenant');
        TenantProfile::create(['user_id' => $tenant->id, 'verification_status' => 'unverified']);

        return $tenant->fresh(['tenantProfile']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }
}
