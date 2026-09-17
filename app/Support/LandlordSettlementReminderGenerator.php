<?php

namespace App\Support;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class LandlordSettlementReminderGenerator
{
    private const CHECKPOINTS = [3, 7, 14];

    public function generate(?Carbon $now = null): int
    {
        if (! Schema::hasTable('payment_transactions') || ! Schema::hasTable('user_notifications')) return 0;
        $now ??= now(); $created = 0; $service = app(LandlordSettlementService::class);
        PaymentTransaction::query()->where('status', 'paid')->whereIn('transaction_type', LandlordSettlementService::ELIGIBLE_TYPES)
            ->with(['property.landlord'])->withSum(['landlordSettlements as paid_to_date' => fn ($query) => $query->where('status', 'paid')], 'payout_amount')
            ->get()->each(function (PaymentTransaction $transaction) use ($now, $service, &$created): void {
                $attention = $service->attention($transaction, $now);
                if (! $attention || ! in_array($attention['days'], self::CHECKPOINTS, true)) return;
                $type = str($transaction->transaction_type)->replace('_', ' ')->headline()->toString();
                foreach (User::role('admin')->get() as $admin) {
                    $created += app(WorkflowNotifier::class)->notify($admin, "landlord-settlement:{$transaction->id}:{$attention['days']}-day", $attention['days'] >= 7 ? 'Landlord payout overdue' : 'Landlord payout needs attention', "{$type} payout for ".($transaction->property?->landlord?->name ?? 'landlord').': '.Currency::format($attention['outstanding'], $transaction->currency)." remains outstanding after {$attention['days']} days.", route('admin.settlements.index'), 'settlement_reminder', 'View settlements') ? 1 : 0;
                }
            });
        return $created;
    }
}
