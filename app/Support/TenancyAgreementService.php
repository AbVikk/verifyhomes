<?php

namespace App\Support;

use App\Models\Occupancy;
use App\Models\TenancyAgreement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenancyAgreementService
{
    public function createForOccupancy(Occupancy $occupancy): ?TenancyAgreement
    {
        if (! Schema::hasTable('tenancy_agreements') || ! $occupancy->tenant_id || ! $occupancy->property?->landlord_id) {
            return null;
        }

        return TenancyAgreement::query()->firstOrCreate(['occupancy_id' => $occupancy->id], [
            'tenant_id' => $occupancy->tenant_id,
            'landlord_id' => $occupancy->property->landlord_id,
            'property_id' => $occupancy->property_id,
            'status' => 'awaiting_tenant',
            'agreement_snapshot' => [
                'tenant_name' => $occupancy->tenant?->name,
                'landlord_name' => $occupancy->property->landlord?->name,
                'property_title' => $occupancy->property->title,
                'property_address' => collect([$occupancy->property->address_text, $occupancy->property->area, $occupancy->property->city, $occupancy->property->state])->filter()->implode(', '),
                'rent_amount' => $occupancy->paymentTransaction?->gross_amount,
                'currency' => $occupancy->paymentTransaction?->currency ?? 'NGN',
                'rental_period' => $occupancy->rentalPeriodLabel(),
                'tenancy_start_date' => $occupancy->started_at?->toDateString(),
                'tenancy_end_or_due_date' => $occupancy->computedNextPaymentDueAt()?->toDateString(),
                'caution_fee' => $occupancy->property->caution_fee,
                'service_charge' => $occupancy->property->service_charge,
                'property_terms' => $occupancy->property->property_terms,
                'general_terms' => [
                    'Use the property responsibly and keep it in a reasonable condition.',
                    'Report maintenance concerns through the VerifyHomes workspace promptly.',
                    'Settle agreed rent, caution fee, and service charge obligations when due.',
                    'Coordinate move-out and any dispute through VerifyHomes before leaving the property.',
                ],
                'created_date' => now()->toDateString(),
            ],
        ]);
    }

    /**
     * @return array{0: TenancyAgreement, 1: bool, 2: bool}
     */
    public function acceptForTenant(TenancyAgreement $agreement, User $tenant): array
    {
        return $this->accept($agreement, $tenant, 'tenant');
    }

    /**
     * @return array{0: TenancyAgreement, 1: bool, 2: bool}
     */
    public function acceptForLandlord(TenancyAgreement $agreement, User $landlord): array
    {
        return $this->accept($agreement, $landlord, 'landlord');
    }

    /**
     * @return array{0: TenancyAgreement, 1: bool, 2: bool}
     */
    private function accept(TenancyAgreement $agreement, User $actor, string $party): array
    {
        return DB::transaction(function () use ($agreement, $actor, $party): array {
            $lockedAgreement = TenancyAgreement::query()->lockForUpdate()->findOrFail($agreement->getKey());

            abort_unless(
                $party === 'tenant'
                    ? $lockedAgreement->tenant_id === $actor->getKey()
                    : $lockedAgreement->landlord_id === $actor->getKey(),
                403,
            );

            if ($lockedAgreement->completed_at) {
                return [$lockedAgreement, false, false];
            }

            $timestampColumn = $party.'_accepted_at';
            $wasAccepted = blank($lockedAgreement->{$timestampColumn});

            if ($wasAccepted) {
                $lockedAgreement->{$timestampColumn} = now();
            }

            $completedNow = false;

            if ($lockedAgreement->tenant_accepted_at && $lockedAgreement->landlord_accepted_at) {
                $completedNow = blank($lockedAgreement->completed_at);
                $lockedAgreement->status = 'completed';
                $lockedAgreement->completed_at ??= now();
            } else {
                $lockedAgreement->status = $lockedAgreement->tenant_accepted_at
                    ? 'awaiting_landlord'
                    : 'awaiting_tenant';
            }

            if ($wasAccepted || $completedNow || $lockedAgreement->isDirty('status')) {
                $lockedAgreement->save();
            }

            return [$lockedAgreement->fresh(), $wasAccepted, $completedNow];
        });
    }
}
