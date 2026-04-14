<?php

namespace Database\Seeders;

use App\Models\LandlordProfile;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LandlordAndTenantLoginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $landlord = User::firstOrCreate(
            ['email' => 'landlord@verifyhomes.test'],
            [
                'name' => 'VerifyHomes Landlord',
                'phone' => '08000000001',
                'status' => 'active',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        if (! $landlord->hasRole('landlord')) {
            $landlord->assignRole('landlord');
        }

        LandlordProfile::firstOrCreate(
            ['user_id' => $landlord->id],
            [
                'business_name' => 'VerifyHomes Landlord Account',
                'verification_status' => 'pending',
            ]
        );

        $tenant = User::firstOrCreate(
            ['email' => 'tenant@verifyhomes.test'],
            [
                'name' => 'VerifyHomes Tenant',
                'phone' => '08000000002',
                'status' => 'active',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        if (! $tenant->hasRole('tenant')) {
            $tenant->assignRole('tenant');
        }

        TenantProfile::firstOrCreate(
            ['user_id' => $tenant->id],
            []
        );
    }
}
