<?php

namespace Tests\Feature;

use App\Livewire\Tenant\Profile as TenantProfilePage;
use App\Livewire\Tenant\Verification as TenantVerificationPage;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantProfileCameraPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('landlord', 'web');
        Role::findOrCreate('tenant', 'web');
    }

    public function test_tenant_can_save_an_avatar_without_changing_the_private_kyc_selfie(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $tenant = $this->tenant();
        $profile = $tenant->tenantProfile;
        $profile->update(['selfie_path' => 'tenant-verification/'.$profile->id.'/existing-selfie.jpg']);
        Storage::disk('local')->put($profile->selfie_path, 'private-selfie');

        $this->actingAs($tenant);

        Livewire::test(TenantProfilePage::class)
            ->set('profilePicture', UploadedFile::fake()->image('captured-profile.jpg'))
            ->call('saveProfilePicture');

        $tenant->refresh();

        $this->assertNotNull($tenant->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($tenant->avatar_path));
        $this->assertSame('tenant-verification/'.$profile->id.'/existing-selfie.jpg', $profile->fresh()->selfie_path);
        $this->assertTrue(Storage::disk('local')->exists($profile->fresh()->selfie_path));

        Livewire::test(TenantProfilePage::class)->call('removeProfilePicture');

        $this->assertNull($tenant->fresh()->avatar_path);
        $this->assertTrue(Storage::disk('local')->exists($profile->fresh()->selfie_path));
    }

    public function test_tenant_selfie_submission_uses_private_storage_and_never_sets_an_avatar(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant();
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($tenant);

        Livewire::test(TenantVerificationPage::class)
            ->set('idType', 'nin')
            ->set('idNumber', '12345678901')
            ->set('idDocument', UploadedFile::fake()->create('id-card.pdf', 200, 'application/pdf'))
            ->set('selfie', UploadedFile::fake()->image('captured-selfie.jpg'))
            ->call('submit');

        $profile = $tenant->tenantProfile()->firstOrFail();

        $this->assertNull($tenant->fresh()->avatar_path);
        $this->assertNotNull($profile->selfie_path);
        $this->assertTrue(Storage::disk('local')->exists($profile->selfie_path));
        $this->assertFalse(Storage::disk('public')->exists($profile->selfie_path));

        $landlord = User::factory()->create(['email_verified_at' => now()]);
        $landlord->assignRole('landlord');

        $this->actingAs($landlord)
            ->get(route('admin.tenants.verification.download', [$profile, 'selfie']))
            ->assertForbidden();
    }

    private function tenant(): User
    {
        $tenant = User::factory()->create(['email_verified_at' => now()]);
        $tenant->assignRole('tenant');
        TenantProfile::create(['user_id' => $tenant->id, 'verification_status' => 'unverified']);

        return $tenant->fresh(['tenantProfile']);
    }
}
