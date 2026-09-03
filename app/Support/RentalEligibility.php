<?php

namespace App\Support;

use App\Models\Occupancy;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class RentalEligibility
{
    public const TRANSITION_WINDOW_DAYS = 60;

    /**
     * @return array{allowed: bool, reason: ?string, active: ?Occupancy, upcoming: ?Occupancy}
     */
    public function forTenant(User|int $tenant): array
    {
        if (! Schema::hasTable('occupancies')) {
            return ['allowed' => true, 'reason' => null, 'active' => null, 'upcoming' => null];
        }

        $tenantId = $tenant instanceof User ? $tenant->getKey() : $tenant;
        $active = Occupancy::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->latest('started_at')
            ->first();
        $upcoming = Occupancy::query()
            ->where('tenant_id', $tenantId)
            ->upcoming()
            ->latest('created_at')
            ->first();

        if ($upcoming) {
            return [
                'allowed' => false,
                'reason' => 'You already have your next rental secured.',
                'active' => $active,
                'upcoming' => $upcoming,
            ];
        }

        if (! $active) {
            return ['allowed' => true, 'reason' => null, 'active' => null, 'upcoming' => null];
        }

        $dueAt = $active->computedNextPaymentDueAt();
        $isWithinTransitionWindow = $dueAt && now()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false) <= self::TRANSITION_WINDOW_DAYS;

        if ($isWithinTransitionWindow) {
            return ['allowed' => true, 'reason' => null, 'active' => $active, 'upcoming' => null];
        }

        return [
            'allowed' => false,
            'reason' => 'You already have an active rental. You can start arranging your next rental when your current stay is within 60 days of ending, or after your move-out is approved.',
            'active' => $active,
            'upcoming' => null,
        ];
    }
}
