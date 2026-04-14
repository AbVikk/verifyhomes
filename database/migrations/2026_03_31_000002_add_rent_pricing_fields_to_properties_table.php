<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('pricing_model', 40)
                ->default('tenant_price')
                ->after('listing_intent');
            $table->decimal('pricing_input_amount', 12, 2)
                ->default(0)
                ->after('rent_amount');
            $table->decimal('landlord_net_amount', 12, 2)
                ->default(0)
                ->after('pricing_input_amount');
            $table->decimal('platform_fee_percentage', 5, 2)
                ->default(0)
                ->after('landlord_net_amount');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'pricing_model',
                'pricing_input_amount',
                'landlord_net_amount',
                'platform_fee_percentage',
            ]);
        });
    }
};
