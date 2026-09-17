<?php

namespace Tests\Feature;

use App\Models\LandlordProfile;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\LandlordSettlementReminderGenerator;
use App\Support\LandlordSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandlordSettlementReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); foreach (['admin','landlord'] as $role) Role::findOrCreate($role, 'web'); }

    public function test_checkpoints_are_internal_deduplicated_and_use_remaining_balance(): void
    {
        [$admin, $transaction] = $this->transaction(3);
        app(LandlordSettlementService::class)->record($transaction->id, $admin, 200000, 'PARTIAL');
        $generator = app(LandlordSettlementReminderGenerator::class);
        $this->assertSame(1, $generator->generate(now()));
        $this->assertSame(0, $generator->generate(now()));
        $notification = UserNotification::query()->where('category', 'settlement_reminder')->firstOrFail();
        $this->assertStringContainsString('112,000', $notification->body);
        $this->assertStringNotContainsString('0123456789', $notification->body);
        $this->assertSame($admin->id, $notification->user_id);
    }

    public function test_full_legacy_inspection_and_unverified_payments_do_not_remind(): void
    {
        [$admin, $full] = $this->transaction(7);
        app(LandlordSettlementService::class)->record($full->id, $admin, 312000, 'FULL');
        [, $legacy] = $this->transaction(7); $legacy->update(['landlord_settlement_status' => 'recorded_paid']);
        [, $inspection] = $this->transaction(7, 'inspection_booking_fee', 0);
        [, $failed] = $this->transaction(7); $failed->update(['status' => 'failed']);
        $this->assertSame(0, app(LandlordSettlementReminderGenerator::class)->generate(now()));
        $this->assertSame(0, UserNotification::query()->where('category', 'settlement_reminder')->count());
    }

    public function test_reversal_reenters_attention_with_a_new_checkpoint(): void
    {
        [$admin, $transaction] = $this->transaction(7);
        $settlement = app(LandlordSettlementService::class)->record($transaction->id, $admin, 312000, 'REV');
        app(LandlordSettlementService::class)->reverse($settlement->id, $admin, 'Correction');
        $this->assertSame(1, app(LandlordSettlementReminderGenerator::class)->generate(now()));
    }

    private function transaction(int $days, string $type = 'rent_payment', int $net = 312000): array
    {
        $admin = User::factory()->create(); $admin->assignRole('admin');
        $landlord = User::factory()->create(); $landlord->assignRole('landlord'); LandlordProfile::create(['user_id'=>$landlord->id]);
        $property = Property::create(['landlord_id'=>$landlord->id,'title'=>'Reminder Home','property_type'=>'flat','rent_amount'=>390000,'lga'=>'Akure South','area'=>'Alagbaka']);
        return [$admin, PaymentTransaction::create(['reference'=>'reminder-'.uniqid(),'property_id'=>$property->id,'transaction_type'=>$type,'currency'=>'NGN','status'=>'paid','gross_amount'=>390000,'platform_fee_percentage'=>20,'platform_fee_amount'=>78000,'net_amount'=>$net,'paid_at'=>now()->subDays($days)])];
    }
}
