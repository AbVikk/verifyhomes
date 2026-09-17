<?php

namespace App\Support;

use App\Models\LandlordSettlement;
use App\Models\LandlordSettlementEvent;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LandlordSettlementService
{
    public const ELIGIBLE_TYPES = ['rent_payment', 'house_purchase_payment', 'land_purchase_payment', 'purchase_payment'];

    public function record(int $transactionId, User $actor, string|float|int $amount, ?string $reference = null, ?string $method = null, ?string $note = null): LandlordSettlement
    {
        $settlement = DB::transaction(function () use ($transactionId, $actor, $amount, $reference, $method, $note): LandlordSettlement {
            $transaction = PaymentTransaction::query()->with(['property.landlord.landlordProfile'])->lockForUpdate()->findOrFail($transactionId);
            $this->ensureEligible($transaction);
            if ($this->isLegacySettled($transaction)) {
                throw ValidationException::withMessages(['settlement' => 'This legacy payment is already marked as paid out. Detailed payout history is unavailable.']);
            }
            $entitlement = $this->entitlement($transaction);
            $paid = $this->paidToDate($transaction->getKey(), true);
            $payout = $this->money($amount);

            if (filled($reference) && LandlordSettlement::query()
                ->where('transaction_id', $transaction->getKey())
                ->where('payout_reference', trim($reference))
                ->exists()) {
                throw ValidationException::withMessages(['payoutReference' => 'This payout reference is already recorded for this transaction.']);
            }

            if ($this->toCents($payout) <= 0 || $this->toCents($payout) > ($this->toCents($entitlement) - $this->toCents($paid))) {
                throw ValidationException::withMessages(['payoutAmount' => 'The payout amount must be greater than zero and cannot exceed the outstanding landlord entitlement.']);
            }

            $profile = $transaction->property?->landlord?->landlordProfile;
            $settlement = LandlordSettlement::create([
                'transaction_id' => $transaction->getKey(), 'landlord_id' => $transaction->property->landlord_id,
                'property_id' => $transaction->property_id, 'gross_amount_snapshot' => $transaction->gross_amount,
                'platform_fee_snapshot' => $transaction->platform_fee_amount, 'landlord_entitlement_snapshot' => $entitlement,
                'payout_amount' => $payout, 'currency' => $transaction->currency, 'status' => 'paid',
                'payout_reference' => blank($reference) ? null : trim($reference), 'payout_method' => blank($method) ? null : $method,
                'note' => blank($note) ? null : $note, 'bank_name' => $profile?->bank_name, 'account_name' => $profile?->account_name,
                'masked_account_number' => $this->maskAccount($profile?->account_number), 'recorded_by' => $actor->getKey(), 'recorded_at' => now(),
            ]);
            $this->event($settlement, $actor, 'payout_recorded', null, ['amount' => $payout, 'reference' => $settlement->payout_reference], $note);
            $this->syncLegacyStatus($transaction, $actor);

            return $settlement;
        });

        $this->notify($settlement);

        return $settlement;
    }

    public function reverse(int $settlementId, User $actor, string $reason): LandlordSettlement
    {
        return DB::transaction(function () use ($settlementId, $actor, $reason): LandlordSettlement {
            $settlement = LandlordSettlement::query()->lockForUpdate()->with('transaction')->findOrFail($settlementId);
            if ($settlement->status === 'reversed') {
                throw ValidationException::withMessages(['settlement' => 'This payout has already been reversed.']);
            }
            $settlement->update(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $actor->getKey()]);
            $this->event($settlement, $actor, 'payout_reversed', ['status' => 'paid'], ['status' => 'reversed'], $reason);
            $this->syncLegacyStatus($settlement->transaction);

            return $settlement;
        });
    }

    public function entitlement(PaymentTransaction $transaction): float { return $this->eligible($transaction) ? $this->money($transaction->net_amount) : 0.0; }
    public function paidToDate(int $transactionId, bool $locked = false): float
    {
        $query = LandlordSettlement::query()->where('transaction_id', $transactionId)->where('status', 'paid');
        if ($locked) { $query->lockForUpdate(); }
        return $this->money($query->sum('payout_amount'));
    }
    public function outstanding(PaymentTransaction $transaction): float
    {
        if ($this->isLegacySettled($transaction)) return 0.0;
        return $this->fromCents(max(0, $this->toCents($this->entitlement($transaction)) - $this->toCents($this->paidToDate($transaction->getKey()))));
    }
    public function status(PaymentTransaction $transaction): string
    {
        if ($this->isLegacySettled($transaction)) return 'legacy_paid';
        $entitlement = $this->entitlement($transaction);
        if ($entitlement <= 0) return 'no_payout_required';
        $paid = $this->paidToDate($transaction->getKey());
        if ($paid <= 0) return 'pending';
        return $paid >= $entitlement ? 'paid' : 'partially_paid';
    }

    public function attention(PaymentTransaction $transaction, ?\Carbon\CarbonInterface $now = null): ?array
    {
        if ($this->outstanding($transaction) <= 0 || $this->isLegacySettled($transaction)) return null;
        $age = max(0, (int) $transaction->paid_at?->copy()->startOfDay()->diffInDays(($now ?? now())->copy()->startOfDay()));
        $label = $age >= 7 ? 'Overdue' : ($age >= 3 ? 'Needs attention' : ($age >= 1 ? 'Due soon' : 'Awaiting payout'));
        return ['days' => $age, 'label' => $label, 'overdue' => $age >= 7, 'outstanding' => $this->outstanding($transaction)];
    }

    public function overview(): array
    {
        $transactions = PaymentTransaction::query()->where('status', 'paid')->whereIn('transaction_type', self::ELIGIBLE_TYPES)
            ->withSum(['landlordSettlements as paid_to_date' => fn ($query) => $query->where('status', 'paid')], 'payout_amount')->get();
        $outstanding = 0.0; $count = 0; $overdue = 0; $oldest = 0;
        foreach ($transactions as $transaction) {
            $attention = $this->attention($transaction);
            if (! $attention) continue;
            $outstanding += $attention['outstanding']; $count++;
            $overdue += $attention['overdue'] ? 1 : 0; $oldest = max($oldest, $attention['days']);
        }
        return compact('outstanding', 'count', 'overdue', 'oldest');
    }

    private function ensureEligible(PaymentTransaction $transaction): void
    {
        if (! $this->eligible($transaction)) throw ValidationException::withMessages(['settlement' => 'This payment does not have a landlord payout entitlement.']);
    }
    private function eligible(PaymentTransaction $transaction): bool
    {
        return $transaction->status === 'paid' && in_array($transaction->transaction_type, self::ELIGIBLE_TYPES, true) && $transaction->property?->landlord_id && $this->money($transaction->net_amount) > 0;
    }
    public function isLegacySettled(PaymentTransaction $transaction): bool
    {
        return $transaction->landlord_settlement_status === 'recorded_paid'
            && ! LandlordSettlement::query()->where('transaction_id', $transaction->getKey())->exists();
    }
    private function syncLegacyStatus(PaymentTransaction $transaction, ?User $actor = null): void
    {
        $status = $this->status($transaction);
        $transaction->update([
            'landlord_settlement_status' => $status === 'paid' ? 'recorded_paid' : ($status === 'no_payout_required' ? 'no_payout_required' : 'awaiting_payout'),
            'landlord_settled_at' => $status === 'paid' ? now() : null,
            'landlord_settled_by' => $status === 'paid' ? ($actor?->getKey() ?? $transaction->landlord_settled_by) : null,
        ]);
    }
    private function event(LandlordSettlement $settlement, User $actor, string $type, ?array $previous, ?array $new, ?string $note): void
    {
        LandlordSettlementEvent::create(['landlord_settlement_id' => $settlement->getKey(), 'actor_id' => $actor->getKey(), 'event_type' => $type, 'previous_values' => $previous, 'new_values' => $new, 'note' => $note, 'occurred_at' => now()]);
    }
    private function notify(LandlordSettlement $settlement): void
    {
        $settlement->loadMissing('landlord', 'property', 'transaction');
        $outstanding = $this->outstanding($settlement->transaction);
        $body = 'Your payout of '.Currency::format($settlement->payout_amount, $settlement->currency).' for '.($settlement->property?->title ?? 'your property').' has been recorded.';
        if ($outstanding > 0) $body .= ' '.Currency::format($outstanding, $settlement->currency).' remains outstanding.';
        app(WorkflowNotifier::class)->notify($settlement->landlord, 'landlord-settlement-'.$settlement->getKey(), 'Landlord payout recorded', $body, route('landlord.payments.index'), 'payment', 'View payments');
    }
    private function money(string|float|int|null $amount): float { return $this->fromCents($this->toCents($amount)); }
    private function toCents(string|float|int|null $amount): int { return (int) round(((float) $amount) * 100); }
    private function fromCents(int $amount): float { return $amount / 100; }
    private function maskAccount(?string $account): ?string { return filled($account) ? '****'.substr(preg_replace('/\D/', '', $account), -4) : null; }
}
