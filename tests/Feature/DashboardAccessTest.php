<?php

namespace Tests\Feature;

use App\Livewire\Admin\Audit\Index as AdminAuditIndex;
use App\Models\InspectionRequest;
use App\Models\InspectionRequestStatusHistory;
use App\Models\LandlordProfile;
use App\Models\LandlordStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_tenant_is_redirected_to_email_verification_notice(): void
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $tenant->assignRole('tenant');

        $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verified_tenant_can_access_their_dashboard(): void
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create();
        $tenant->assignRole('tenant');

        $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

        $response->assertOk();
        $response->assertSee('Tenant Dashboard');
        $response->assertSee('data-admin-shell-key="tenant"', false);
        $response->assertSee('Workspace Menu');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_verified_landlord_can_access_their_dashboard_in_the_shared_dashboard_shell(): void
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $landlord->assignRole('landlord');

        $response = $this->actingAs($landlord)->get(route('landlord.dashboard'));

        $response->assertOk();
        $response->assertSee('Landlord Dashboard');
        $response->assertSee('data-admin-shell-key="landlord"', false);
        $response->assertSee('Workspace Menu');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
    }

    public function test_landlord_cannot_access_tenant_dashboard(): void
    {
        Role::findOrCreate('tenant', 'web');
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create();
        $landlord->assignRole('landlord');

        $response = $this->actingAs($landlord)->get(route('tenant.dashboard'));

        $response->assertForbidden();
    }

    public function test_admin_dashboard_renders_when_inspection_requests_table_is_missing(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        Schema::dropIfExists('inspection_request_status_histories');
        Schema::dropIfExists('inspection_requests');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Admin Dashboard');
        $response->assertSee('Open inspection requests');
        $response->assertSee('Closed inspection requests');
        $response->assertSee('Inspection request data is not available yet.');
        $response->assertSee('Inspection outcome data is not available yet.');
    }

    public function test_admin_dashboard_renders_when_property_status_history_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('property_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Recent review activity');
    }

    public function test_recent_activity_still_renders_when_one_history_source_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');
        $landlord = $this->createLandlord();

        $landlordProfile = LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'under_review',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        LandlordStatusHistory::create([
            'landlord_profile_id' => $landlordProfile->id,
            'from_status' => 'pending',
            'to_status' => 'under_review',
            'changed_by' => $admin->id,
            'notes' => 'Landlord documents are in review.',
        ]);

        Schema::dropIfExists('property_status_histories');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Landlord review');
        $response->assertSee($landlord->name);
    }

    public function test_admin_dashboard_renders_navigation_only_once(): void
    {
        Role::findOrCreate('admin', 'web');

        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
        $response->assertSee('Operations Menu');
        $response->assertSee('Documents');
        $response->assertSee('Landlords');
        $response->assertSee('Tenants');
        $response->assertSee('Properties');
        $response->assertSee('Inspection Requests');
        $response->assertSee('Audit');
    }

    public function test_admin_dashboard_shows_platform_fee_summary_from_paid_transactions(): void
    {
        $admin = $this->createReviewer('admin');
        $landlord = $this->createLandlord();
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Payment-backed Listing',
            'property_type' => 'flat',
            'rent_amount' => 850000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => 'approved',
            'is_verified' => true,
        ]);

        PaymentTransaction::create([
            'reference' => 'txn-paid-001',
            'payer_id' => $landlord->id,
            'property_id' => $property->id,
            'transaction_type' => 'property_listing_fee',
            'currency' => 'NGN',
            'status' => 'paid',
            'gross_amount' => 100000,
            'platform_fee_percentage' => 10,
            'platform_fee_amount' => 10000,
            'net_amount' => 90000,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Admin earnings from payment transactions');
        $response->assertSee('Paid transactions');
        $response->assertSee('Gross paid volume');
        $response->assertSee('₦100,000.00');
        $response->assertSee('Platform fee earned');
        $response->assertSee('₦10,000.00');
        $response->assertSee('Net after platform fee');
        $response->assertSee('₦90,000.00');
    }

    public function test_admin_can_access_audit_page(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('Audit log');
    }

    public function test_admin_audit_page_shows_no_data_yet_state_when_audit_table_is_empty(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('No audit entries have been logged yet.');
        $response->assertSee('This list will populate automatically once audit activity starts being recorded in this environment.');
    }

    public function test_admin_audit_page_search_filters_rows_correctly(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'National ID',
                'description' => 'Approved landlord identity document.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'property_rejected',
                'actor_name' => 'Amina Staff',
                'actor_email' => 'amina@example.com',
                'target_label' => 'Duplex Listing',
                'description' => 'Rejected property listing details.',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'search' => 'Kemi Reviewer',
        ]));

        $response->assertOk();
        $response->assertSee('Document Approved');
        $response->assertSee('Kemi Reviewer');
        $response->assertDontSee('Rejected property listing details.');
        $response->assertDontSee('Amina Staff');
    }

    public function test_admin_audit_page_shows_no_matches_state_when_filters_exclude_existing_audit_entries(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            'action' => 'document_approved',
            'actor_name' => 'Kemi Reviewer',
            'actor_email' => 'kemi@example.com',
            'target_label' => 'National ID',
            'description' => 'Approved landlord identity document.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'search' => 'no-match-term',
        ]));

        $response->assertOk();
        $response->assertSee('No audit entries match the current search or filter.');
        $response->assertSee('Try a broader search, adjust the action filter, or widen the selected date range.');
        $response->assertDontSee('No audit entries have been logged yet.');
    }

    public function test_admin_audit_page_still_renders_when_actor_and_subject_display_data_are_missing_or_blank(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => '',
                'actor_name' => '',
                'actor_email' => null,
                'target_label' => '',
                'description' => 'Audit row with degraded display data.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('Audit Entry');
        $response->assertSee('Unknown user');
        $response->assertSee('Not available');
        $response->assertSee('Audit row with degraded display data.');
    }

    public function test_admin_audit_page_renders_long_descriptions_with_compact_show_more_treatment(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        $description = 'This audit entry has a long description that should stay readable on the index page while still allowing admins to inspect the full context without leaving the queue.';

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_reviewed',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Landlord profile',
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('Show more');
        $response->assertSee($description);
    }

    public function test_admin_audit_page_exposes_both_relative_and_exact_logged_timestamps_when_available(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_reviewed',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Landlord profile',
                'description' => 'Reviewed landlord verification record.',
                'created_at' => '2026-03-26 09:15:00',
                'updated_at' => '2026-03-26 09:15:00',
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('days ago');
        $response->assertSee('2026-03-26 09:15:00');
    }

    public function test_admin_audit_page_supports_newest_first_and_oldest_first_logged_time_sorting(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'older_entry',
                'actor_name' => 'Amina Staff',
                'actor_email' => 'amina@example.com',
                'target_label' => 'Older target',
                'description' => 'Older audit entry.',
                'created_at' => '2026-03-20 09:00:00',
                'updated_at' => '2026-03-20 09:00:00',
            ],
            [
                'action' => 'newer_entry',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Newer target',
                'description' => 'Newer audit entry.',
                'created_at' => '2026-03-27 09:00:00',
                'updated_at' => '2026-03-27 09:00:00',
            ],
        ]);

        $newestFirst = $this->actingAs($admin)->get(route('admin.audit.index'));
        $oldestFirst = $this->actingAs($admin)->get(route('admin.audit.index', [
            'sortDirection' => 'asc',
        ]));

        $newestFirst->assertOk();
        $newestFirst->assertSeeInOrder(['Newer Entry', 'Older Entry']);

        $oldestFirst->assertOk();
        $oldestFirst->assertSeeInOrder(['Older Entry', 'Newer Entry']);
    }

    public function test_admin_audit_page_per_page_can_be_changed_to_25_and_50(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        foreach (range(1, 30) as $entry) {
            DB::table('audit_logs')->insert([
                'action' => 'document_reviewed',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Audit target',
                'description' => "Bulk audit entry {$entry}.",
                'created_at' => now()->subMinutes(31 - $entry),
                'updated_at' => now()->subMinutes(31 - $entry),
            ]);
        }

        $perPageTwentyFive = $this->actingAs($admin)->get(route('admin.audit.index', [
            'perPage' => '25',
        ]));

        $perPageFifty = $this->actingAs($admin)->get(route('admin.audit.index', [
            'perPage' => '50',
        ]));

        $perPageTwentyFive->assertOk();
        $perPageTwentyFive->assertSee('Bulk audit entry 30.');
        $perPageTwentyFive->assertSee('Bulk audit entry 6.');
        $perPageTwentyFive->assertDontSee('Bulk audit entry 5.');

        $perPageFifty->assertOk();
        $perPageFifty->assertSee('Bulk audit entry 30.');
        $perPageFifty->assertSee('Bulk audit entry 1.');
    }

    public function test_admin_audit_page_search_and_action_filter_can_be_combined_correctly(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'National ID',
                'description' => 'Approved landlord identity document.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'document_rejected',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Utility Bill',
                'description' => 'Rejected landlord utility bill.',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'action' => 'property_rejected',
                'actor_name' => 'Amina Staff',
                'actor_email' => 'amina@example.com',
                'target_label' => 'Duplex Listing',
                'description' => 'Rejected property listing details.',
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'search' => 'Kemi Reviewer',
            'actionFilter' => 'document_approved',
        ]));

        $response->assertOk();
        $response->assertSee('Document Approved');
        $response->assertSee('Kemi Reviewer');
        $response->assertDontSee('Rejected landlord utility bill.');
        $response->assertDontSee('Rejected property listing details.');
        $response->assertDontSee('Amina Staff');
    }

    public function test_admin_audit_page_search_action_filter_and_date_range_can_be_combined_correctly(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'National ID',
                'description' => 'Approved landlord identity document.',
                'created_at' => '2026-03-20 09:00:00',
                'updated_at' => '2026-03-20 09:00:00',
            ],
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Utility Bill',
                'description' => 'Approved landlord utility bill.',
                'created_at' => '2026-03-26 09:00:00',
                'updated_at' => '2026-03-26 09:00:00',
            ],
            [
                'action' => 'property_rejected',
                'actor_name' => 'Amina Staff',
                'actor_email' => 'amina@example.com',
                'target_label' => 'Duplex Listing',
                'description' => 'Rejected property listing details.',
                'created_at' => '2026-03-26 12:00:00',
                'updated_at' => '2026-03-26 12:00:00',
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'search' => 'Kemi Reviewer',
            'actionFilter' => 'document_approved',
            'fromDate' => '2026-03-25',
            'toDate' => '2026-03-27',
        ]));

        $response->assertOk();
        $response->assertSee('Approved landlord utility bill.');
        $response->assertSee('Kemi Reviewer');
        $response->assertDontSee('Approved landlord identity document.');
        $response->assertDontSee('Rejected property listing details.');
        $response->assertDontSee('Amina Staff');
    }

    public function test_admin_audit_page_sort_works_together_with_existing_filters(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'National ID',
                'description' => 'Older matching entry.',
                'created_at' => '2026-03-25 09:00:00',
                'updated_at' => '2026-03-25 09:00:00',
            ],
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Utility Bill',
                'description' => 'Newer matching entry.',
                'created_at' => '2026-03-26 09:00:00',
                'updated_at' => '2026-03-26 09:00:00',
            ],
            [
                'action' => 'property_rejected',
                'actor_name' => 'Amina Staff',
                'actor_email' => 'amina@example.com',
                'target_label' => 'Duplex Listing',
                'description' => 'Non-matching entry.',
                'created_at' => '2026-03-26 12:00:00',
                'updated_at' => '2026-03-26 12:00:00',
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'search' => 'Kemi Reviewer',
            'actionFilter' => 'document_approved',
            'fromDate' => '2026-03-25',
            'toDate' => '2026-03-27',
            'sortDirection' => 'asc',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Older matching entry.', 'Newer matching entry.']);
        $response->assertDontSee('Non-matching entry.');
    }

    public function test_admin_audit_page_per_page_works_together_with_existing_filters_and_sorting(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        foreach (range(1, 30) as $entry) {
            DB::table('audit_logs')->insert([
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Filtered target',
                'description' => "Filtered audit entry {$entry}.",
                'created_at' => now()->subDays(31 - $entry),
                'updated_at' => now()->subDays(31 - $entry),
            ]);
        }

        DB::table('audit_logs')->insert([
            'action' => 'property_rejected',
            'actor_name' => 'Amina Staff',
            'actor_email' => 'amina@example.com',
            'target_label' => 'Non matching target',
            'description' => 'Non-matching entry.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'search' => 'Kemi Reviewer',
            'actionFilter' => 'document_approved',
            'fromDate' => now()->subDays(31)->toDateString(),
            'toDate' => now()->toDateString(),
            'sortDirection' => 'asc',
            'perPage' => '25',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Filtered audit entry 1.', 'Filtered audit entry 25.']);
        $response->assertDontSee('Filtered audit entry 26.');
        $response->assertDontSee('Non-matching entry.');
    }

    public function test_admin_audit_page_invalid_date_range_is_normalized_gracefully(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        DB::table('audit_logs')->insert([
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'National ID',
                'description' => 'Inside normalized range.',
                'created_at' => '2026-03-26 09:00:00',
                'updated_at' => '2026-03-26 09:00:00',
            ],
            [
                'action' => 'document_approved',
                'actor_name' => 'Kemi Reviewer',
                'actor_email' => 'kemi@example.com',
                'target_label' => 'Utility Bill',
                'description' => 'Outside normalized range.',
                'created_at' => '2026-03-20 09:00:00',
                'updated_at' => '2026-03-20 09:00:00',
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminAuditIndex::class)
            ->set('fromDate', '2026-03-27')
            ->set('toDate', '2026-03-25')
            ->assertSet('fromDate', '2026-03-25')
            ->assertSet('toDate', '2026-03-27');

        $response = $this->actingAs($admin)->get(route('admin.audit.index', [
            'fromDate' => '2026-03-27',
            'toDate' => '2026-03-25',
        ]));

        $response->assertOk();
        $response->assertSee('Inside normalized range.');
        $response->assertDontSee('Outside normalized range.');
    }

    public function test_admin_audit_page_reset_clears_all_filters_back_to_default_state(): void
    {
        $admin = $this->createReviewer('admin');

        $this->createAuditLogsTable();

        $this->actingAs($admin);

        Livewire::test(AdminAuditIndex::class)
            ->set('search', 'Kemi Reviewer')
            ->set('actionFilter', 'document_approved')
            ->set('fromDate', '2026-03-25')
            ->set('toDate', '2026-03-27')
            ->set('sortDirection', 'asc')
            ->set('perPage', '50')
            ->set('paginators.page', 2)
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('actionFilter', 'all')
            ->assertSet('fromDate', '')
            ->assertSet('toDate', '')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', '10')
            ->assertSet('paginators.page', 1);
    }

    public function test_admin_audit_page_marks_sidebar_link_active(): void
    {
        $admin = $this->createReviewer('admin');

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.audit.index').'"', false);
        $response->assertSee('title="Audit"', false);
        $response->assertSee('bg-sky-300/10 text-white', false);
    }

    public function test_admin_documents_page_marks_sidebar_link_active(): void
    {
        $admin = $this->createReviewer('admin');

        $response = $this->actingAs($admin)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.documents.index').'"', false);
        $response->assertSee('title="Documents"', false);
        $response->assertSee('bg-sky-300/10 text-white', false);
    }

    public function test_admin_tenants_page_marks_sidebar_link_active(): void
    {
        $admin = $this->createReviewer('admin');
        $tenant = $this->createTenant();

        TenantProfile::create([
            'user_id' => $tenant->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tenants.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.tenants.index').'"', false);
        $response->assertSee('title="Tenants"', false);
        $response->assertSee('bg-sky-300/10 text-white', false);
    }

    public function test_admin_audit_page_renders_safely_when_audit_table_is_missing(): void
    {
        $admin = $this->createReviewer('admin');

        Schema::dropIfExists('audit_logs');

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk();
        $response->assertSee('Audit log');
        $response->assertSee('Audit logging is not available yet.');
    }

    public function test_admin_profile_page_uses_admin_shell(): void
    {
        $admin = $this->createReviewer('admin');

        $response = $this->actingAs($admin)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('VerifyHomes Admin');
        $response->assertDontSee('<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">', false);
        $response->assertSee('Profile Information');
        $response->assertSee('Update Password');
        $response->assertSee('Delete Account');
    }

    public function test_admin_profile_update_redirects_back_to_admin_profile_page(): void
    {
        $admin = $this->createReviewer('admin');

        $response = $this->actingAs($admin)->patch(route('profile.update'), [
            'name' => 'Admin Updated',
            'email' => 'admin.updated@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('profile.edit'));
    }

    public function test_role_aware_dashboard_route_redirects_authenticated_users_into_their_product_shell(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant)->get(route('dashboard'));

        $response->assertRedirect(route('tenant.dashboard'));
    }

    public function test_admin_dashboard_renders_operational_panels_with_real_data(): void
    {
        $admin = $this->createReviewer('admin');
        $landlord = $this->createLandlord();
        $tenant = $this->createTenant();

        $landlordProfile = LandlordProfile::create([
            'user_id' => $landlord->id,
            'verification_status' => 'under_review',
            'city' => 'Akure',
            'state' => 'Ondo',
        ]);

        $property = Property::create([
            'landlord_id' => $landlord->id,
            'title' => 'Operations Test Listing',
            'property_type' => 'flat',
            'rent_amount' => 650000,
            'lga' => 'Akure South',
            'city' => 'Akure',
            'state' => 'Ondo',
            'area' => 'Alagbaka',
            'status' => 'approved',
            'is_verified' => true,
            'is_published' => false,
        ]);

        $inspectionRequest = InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'completed',
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_time_note' => 'Late morning',
            'message' => 'Please confirm access timing.',
            'outcome_type' => 'follow_up_needed',
            'outcome_notes' => 'Tenant wants a second visit after checking budget.',
        ]);
        InspectionRequest::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'status' => 'requested',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_note' => 'Early afternoon',
            'message' => 'Please confirm availability for another visit.',
        ]);

        LandlordStatusHistory::create([
            'landlord_profile_id' => $landlordProfile->id,
            'from_status' => 'pending',
            'to_status' => 'under_review',
            'changed_by' => $admin->id,
            'notes' => 'Documents queued for admin review.',
        ]);

        PropertyStatusHistory::create([
            'property_id' => $property->id,
            'from_status' => 'pending_review',
            'to_status' => 'approved',
            'changed_by' => $admin->id,
            'notes' => 'Listing details were verified and approved.',
        ]);

        InspectionRequestStatusHistory::create([
            'inspection_request_id' => $inspectionRequest->id,
            'from_status' => 'scheduled',
            'to_status' => 'completed',
            'changed_by' => $admin->id,
            'notes' => 'Inspection wrapped with follow-up required.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Inspection activity overview');
        $response->assertSee('Publish readiness overview');
        $response->assertSee('Recent review activity');
        $response->assertSee('Landlord verification pipeline');
        $response->assertSee('Recent inspection outcomes');
        $response->assertSee('Top operational signals');
        $response->assertSee('Needs attention now');
        $response->assertSee('Landlord reviews waiting for a decision');
        $response->assertSee('Approved listings waiting to go live');
        $response->assertSee('Inspection requests need coordination');
        $response->assertSee('Operations Test Listing');
        $response->assertSee('Follow-up needed');
    }

    protected function createReviewer(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function createLandlord(): User
    {
        Role::findOrCreate('landlord', 'web');

        $landlord = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $landlord->assignRole('landlord');

        return $landlord;
    }

    protected function createTenant(): User
    {
        Role::findOrCreate('tenant', 'web');

        $tenant = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $tenant->assignRole('tenant');

        return $tenant;
    }

    protected function createAuditLogsTable(): void
    {
        Schema::dropIfExists('audit_logs');

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('action')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('target_label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
}
