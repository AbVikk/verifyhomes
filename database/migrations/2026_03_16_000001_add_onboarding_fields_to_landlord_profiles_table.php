<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('landlord_profiles', function (Blueprint $table) {
            $table->string('city', 100)->default('Akure')->after('address');
            $table->string('state', 100)->default('Ondo')->after('city');
            $table->string('whatsapp_number', 25)->nullable()->after('phone');
            $table->string('occupation_or_business', 125)->nullable()->after('business_name');
            $table->text('short_bio_or_notes')->nullable()->after('occupation_or_business');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landlord_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'city',
                'state',
                'whatsapp_number',
                'occupation_or_business',
                'short_bio_or_notes',
            ]);
        });
    }
};
