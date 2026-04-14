<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;

class PublicPropertyVisibility
{
    public const APPROVED_STATUS = 'approved';

    public static function apply(Builder $query): Builder
    {
        return $query
            ->where('status', self::APPROVED_STATUS)
            ->where('is_verified', true)
            ->where('is_published', true);
    }

    public static function matches(Property $property): bool
    {
        return $property->status === self::APPROVED_STATUS
            && $property->is_verified === true
            && $property->is_published === true;
    }

    public static function canBePublished(Property $property): bool
    {
        return $property->status === self::APPROVED_STATUS
            && $property->is_verified === true;
    }
}
