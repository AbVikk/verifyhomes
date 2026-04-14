<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_profiles', function (Blueprint $table) {
            $table->string('bank_name', 150)->nullable()->after('short_bio_or_notes');
            $table->string('account_name', 150)->nullable()->after('bank_name');
            $table->string('account_number', 30)->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name',
                'account_name',
                'account_number',
            ]);
        });
    }
};
