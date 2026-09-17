<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class TenantVerification
{
    public static function isVerified(User $user): bool
    {
        return Schema::hasTable('tenant_profiles')
            && $user->tenantProfile?->verification_status === 'verified';
    }
}
