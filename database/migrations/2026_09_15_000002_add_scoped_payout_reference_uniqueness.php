<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_settlements', function (Blueprint $table): void {
            $table->unique(['transaction_id', 'payout_reference'], 'landlord_settlements_transaction_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_settlements', function (Blueprint $table): void {
            $table->dropUnique('landlord_settlements_transaction_reference_unique');
        });
    }
};
