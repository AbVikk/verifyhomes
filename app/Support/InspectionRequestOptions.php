<?php

namespace App\Support;

class InspectionRequestOptions
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    public static function statuses(): array
    {
        return [
            self::STATUS_REQUESTED => 'Requested',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function values(): array
    {
        return array_keys(self::statuses());
    }

    public static function openStatuses(): array
    {
        return [
            self::STATUS_REQUESTED,
            self::STATUS_SCHEDULED,
        ];
    }

    public static function requiresScheduledAt(string $status): bool
    {
        return $status === self::STATUS_SCHEDULED;
    }

    public static function outcomes(): array
    {
        return [
            'inspected' => 'Inspected',
            'tenant_no_show' => 'Tenant did not show up',
            'landlord_unavailable' => 'Landlord unavailable',
            'property_unavailable' => 'Property unavailable',
            'follow_up_needed' => 'Follow-up needed',
            'cancelled_before_visit' => 'Cancelled before visit',
        ];
    }

    public static function outcomeValues(): array
    {
        return array_keys(self::outcomes());
    }

    public static function requiresOutcomeType(string $status): bool
    {
        return $status === self::STATUS_COMPLETED;
    }

    public static function isCompleted(string $status): bool
    {
        return $status === self::STATUS_COMPLETED;
    }
}
