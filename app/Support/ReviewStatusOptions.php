<?php

namespace App\Support;

class ReviewStatusOptions
{
    public static function landlordStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
        ];
    }

    public static function propertyStatuses(): array
    {
        return [
            'pending_review' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
        ];
    }

    public static function landlordStatusValues(): array
    {
        return array_keys(self::landlordStatuses());
    }

    public static function propertyStatusValues(): array
    {
        return array_keys(self::propertyStatuses());
    }
}
