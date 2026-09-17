<?php

namespace Tests\Feature;

use App\Livewire\Landlord\MoveInConditionReports\Edit as LandlordReportEdit;
use App\Livewire\Tenant\MoveInConditionReports\Show as TenantReportShow;
use App\Models\MoveInConditionReport;
use App\Models\Occupancy;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\TenancyAgreement;
use App\Models\User;
use App\Support\MoveInConditionReportService;
use App\Support\PublicPropertyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MoveInConditionReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); foreach (['admin', 'staff', 'tenant', 'landlord'] as $role) Role::findOrCreate($role, 'web'); }

    public function test_completed_agreement_is_required_and_landlord_can_start_a_draft_for_own_occupancy(): void
    {
        $landlord = $this->user('landlord'); $tenant = $this->user('tenant'); $occupancy = $this->occupancy($tenant, $this->property($landlord));
        $this->actingAs($landlord);
        Livewire::test(LandlordReportEdit::class, ['occupancy' => $occupancy])->assertStatus(422);
        $this->completeAgreement($occupancy);
        Livewire::test(LandlordReportEdit::class, ['occupancy' => $occupancy])->assertHasNoErrors();
        $report = MoveInConditionReport::query()->sole();
        $this->assertSame('draft', $report->status); $this->assertSame($tenant->id, $report->tenant_id); $this->assertSame($landlord->id, $report->landlord_id); $this->assertSame(25, $report->items()->count());
        $other = $this->user('landlord'); $this->actingAs($other)->get(route('landlord.move-in-reports.edit', $occupancy))->assertNotFound();
    }

    public function test_landlord_saves_evidence_submits_and_cannot_edit_submitted_report(): void
    {
        Storage::fake('local'); Mail::fake(); $landlord = $this->user('landlord'); $tenant = $this->user('tenant'); $occupancy = $this->occupancy($tenant, $this->property($landlord)); $this->completeAgreement($occupancy);
        $this->actingAs($landlord); $component = Livewire::test(LandlordReportEdit::class, ['occupancy' => $occupancy]); $report = MoveInConditionReport::query()->sole();
        foreach ($report->items as $item) $component->set("items.{$item->id}.rating", 'good');
        $component->set('photo', UploadedFile::fake()->image('wall.jpg'))->call('addPhoto')->call('submitToTenant')->assertHasNoErrors();
        $report->refresh(); $this->assertSame('awaiting_tenant', $report->status); $this->assertNotNull($report->submitted_at); $this->assertSame(1, $report->photos()->count()); Storage::disk('local')->assertExists($report->photos()->sole()->file_path);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $tenant->id, 'event_key' => 'move-in-report-ready:'.$report->id]);
        Livewire::test(LandlordReportEdit::class, ['occupancy' => $occupancy])->call('saveDraft')->assertStatus(422);
    }

    public function test_tenant_can_confirm_or_request_changes_with_separate_evidence_and_scoping_is_strict(): void
    {
        Storage::fake('local'); Mail::fake(); $landlord = $this->user('landlord'); $tenant = $this->user('tenant'); $report = $this->submittedReport($tenant, $landlord);
        $other = $this->user('tenant'); $this->actingAs($other)->get(route('tenant.move-in-reports.show', $report))->assertNotFound();
        $this->actingAs($tenant); Livewire::test(TenantReportShow::class, ['report' => $report])->set('tenantNotes', 'The bedroom window crack is missing from the report.')->set('photo', UploadedFile::fake()->image('crack.jpg'))->call('requestChanges')->assertHasNoErrors();
        $report->refresh(); $this->assertSame('changes_requested', $report->status); $this->assertSame('tenant', $report->photos()->latest()->first()->source); $this->assertDatabaseHas('user_notifications', ['user_id' => $landlord->id, 'event_key' => 'move-in-report-changes-requested:'.$report->id]);
        $report->update(['status' => 'awaiting_tenant', 'tenant_notes' => null]); Livewire::test(TenantReportShow::class, ['report' => $report])->set('confirmed', true)->call('confirm')->assertHasNoErrors();
        $report->refresh(); $this->assertSame('completed', $report->status); $this->assertNotNull($report->tenant_confirmed_at); $this->assertDatabaseHas('user_notifications', ['user_id' => $landlord->id, 'event_key' => 'move-in-report-confirmed:'.$report->id]);
    }

    public function test_completed_report_is_immutable_and_private_photo_route_is_role_scoped(): void
    {
        Storage::fake('local'); $landlord = $this->user('landlord'); $tenant = $this->user('tenant'); $report = $this->submittedReport($tenant, $landlord); $photo = $report->photos()->create(['uploaded_by_id' => $landlord->id, 'source' => 'landlord', 'file_path' => 'move-in-condition-reports/test.jpg', 'original_name' => 'test.jpg']); Storage::disk('local')->put($photo->file_path, 'photo'); $report->update(['status' => 'completed', 'tenant_confirmed_at' => now()]);
        try { $report->status = 'draft'; $report->save(); $this->fail('Completed report should be immutable.'); } catch (\LogicException) { $this->assertSame('completed', $report->fresh()->status); }
        $this->actingAs($tenant)->get(route('tenant.move-in-condition-photos.show', $photo))->assertOk(); $this->actingAs($this->user('tenant'))->get(route('tenant.move-in-condition-photos.show', $photo))->assertNotFound();
    }

    private function submittedReport(User $tenant, User $landlord): MoveInConditionReport { $occupancy = $this->occupancy($tenant, $this->property($landlord)); $this->completeAgreement($occupancy); $report = app(MoveInConditionReportService::class)->createForOccupancy($occupancy->load(['property', 'tenancyAgreement'])); foreach ($report->items as $item) $item->update(['rating' => 'good']); $report->update(['status' => 'awaiting_tenant', 'submitted_at' => now()]); return $report->fresh(['items', 'photos', 'property', 'landlord']); }
    private function completeAgreement(Occupancy $occupancy): void { TenancyAgreement::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $occupancy->tenant_id, 'landlord_id' => $occupancy->property->landlord_id, 'property_id' => $occupancy->property_id, 'status' => 'completed', 'agreement_snapshot' => [], 'tenant_accepted_at' => now(), 'landlord_accepted_at' => now(), 'completed_at' => now()]); $occupancy->load('tenancyAgreement'); }
    private function user(string $role): User { $user = User::factory()->create(['email_verified_at' => now()]); $user->assignRole($role); if ($role === 'tenant') TenantProfile::create(['user_id' => $user->id]); return $user; }
    private function property(User $landlord): Property { return Property::create(['landlord_id' => $landlord->id, 'title' => 'Move-in Property', 'property_type' => 'flat', 'listing_intent' => 'for_rent', 'pricing_model' => 'tenant_price', 'pricing_input_amount' => 850000, 'rent_amount' => 850000, 'landlord_net_amount' => 680000, 'platform_fee_percentage' => 20, 'total_units' => 2, 'occupied_units' => 0, 'lga' => 'Akure South', 'city' => 'Akure', 'state' => 'Ondo', 'area' => 'Alagbaka', 'status' => PublicPropertyVisibility::APPROVED_STATUS, 'is_verified' => true, 'is_published' => true]); }
    private function occupancy(User $tenant, Property $property): Occupancy { return Occupancy::create(['property_id' => $property->id, 'tenant_id' => $tenant->id, 'status' => 'active', 'units' => 1, 'payment_cycle_months' => 12, 'started_at' => now(), 'last_payment_at' => now(), 'next_payment_due_at' => now()->addYear()]); }
}
