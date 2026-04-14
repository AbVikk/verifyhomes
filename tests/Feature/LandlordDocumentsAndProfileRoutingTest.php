<?php

namespace Tests\Feature;

use App\Livewire\Landlord\Documents as LandlordDocuments;
use App\Models\LandlordProfile;
use App\Models\User;
use App\Support\UploadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandlordDocumentsAndProfileRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_landlord_document_upload_creates_a_row_and_appears_in_history(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();

        Livewire::actingAs($landlord)
            ->test(LandlordDocuments::class)
            ->set('documentType', 'national_id')
            ->set('document', UploadedFile::fake()->create('national-id.pdf', 500, 'application/pdf'))
            ->call('upload')
            ->assertRedirect(route('landlord.documents'));

        $this->assertDatabaseHas('landlord_documents', [
            'landlord_profile_id' => $landlord->landlordProfile->id,
            'document_type' => 'national_id',
            'original_name' => 'national-id.pdf',
            'review_status' => 'pending',
        ]);

        $response = $this->actingAs($landlord)->get(route('landlord.documents'));

        $response->assertOk();
        $response->assertSee('Verification document uploaded successfully.');
        $response->assertSee('national-id.pdf');
        $response->assertSee('Pending');
        $response->assertDontSee('No verification documents uploaded yet.');
    }

    public function test_oversized_landlord_document_upload_fails_clearly(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();
        $limitKilobytes = UploadConfiguration::effectiveMaxUploadKilobytes(5120);
        $oversizedFile = UploadedFile::fake()->create('oversized-id.pdf', $limitKilobytes + 128, 'application/pdf');
        $expectedLimitLabel = UploadConfiguration::formatKilobytes($limitKilobytes);

        $response = $this->actingAs($landlord)->get(route('landlord.documents'));

        $response->assertOk();
        $response->assertSee("Current server upload limit: {$expectedLimitLabel}. Files above this limit are rejected before upload completes.");

        Livewire::actingAs($landlord)
            ->test(LandlordDocuments::class)
            ->set('documentType', 'national_id')
            ->set('document', $oversizedFile)
            ->call('upload')
            ->assertHasErrors(['document'])
            ->assertSee("The selected document exceeds the current server upload limit of {$expectedLimitLabel}.");

        $this->assertDatabaseMissing('landlord_documents', [
            'landlord_profile_id' => $landlord->landlordProfile->id,
            'original_name' => 'oversized-id.pdf',
        ]);
    }

    public function test_missing_landlord_documents_table_still_fails_softly(): void
    {
        $landlord = $this->createLandlord();

        Schema::dropIfExists('landlord_documents');

        Livewire::actingAs($landlord)
            ->test(LandlordDocuments::class)
            ->call('upload')
            ->assertSee('Verification document uploads are not available yet in this environment.');
    }

    public function test_invalid_landlord_document_type_is_rejected(): void
    {
        Storage::fake('local');

        $landlord = $this->createLandlord();

        Livewire::actingAs($landlord)
            ->test(LandlordDocuments::class)
            ->set('documentType', 'not_real')
            ->set('document', UploadedFile::fake()->create('national-id.pdf', 500, 'application/pdf'))
            ->call('upload')
            ->assertHasErrors(['documentType']);
    }

    public function test_landlord_shell_profile_links_all_point_to_the_landlord_profile_route(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('landlord.documents'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('profile.edit').'"', false);

        $content = $response->getContent();

        $this->assertGreaterThanOrEqual(
            3,
            substr_count($content, 'href="'.route('landlord.profile').'"'),
            'Expected the landlord shell nav, footer profile button, and topbar dropdown profile link to use the landlord profile route.',
        );
    }

    public function test_landlord_shared_profile_route_redirects_to_the_landlord_shell_profile_page(): void
    {
        $landlord = $this->createLandlord();

        $response = $this->actingAs($landlord)->get(route('profile.edit'));

        $response->assertRedirect(route('landlord.profile'));
    }

    protected function createLandlord(?string $email = null): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'pending',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        return $landlord;
    }
}
