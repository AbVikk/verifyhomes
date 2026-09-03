<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->text('property_terms')->nullable()->after('description');
        });

        Schema::table('inspection_requests', function (Blueprint $table): void {
            $table->string('schedule_response', 25)->nullable()->after('scheduled_at');
            $table->text('schedule_response_notes')->nullable()->after('schedule_response');
            $table->timestamp('schedule_responded_at')->nullable()->after('schedule_response_notes');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->string('landlord_settlement_status', 25)->nullable()->after('paid_at');
            $table->timestamp('landlord_settled_at')->nullable()->after('landlord_settlement_status');
            $table->unsignedBigInteger('landlord_settled_by')->nullable()->after('landlord_settled_at');
            $table->string('landlord_settlement_reference')->nullable()->after('landlord_settled_by');
            $table->text('landlord_settlement_notes')->nullable()->after('landlord_settlement_reference');
            $table->foreign('landlord_settled_by', 'pt_landlord_settled_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropForeign('pt_landlord_settled_by_fk');
            $table->dropColumn([
                'landlord_settlement_status',
                'landlord_settled_at',
                'landlord_settled_by',
                'landlord_settlement_reference',
                'landlord_settlement_notes',
            ]);
        });

        Schema::table('inspection_requests', function (Blueprint $table): void {
            $table->dropColumn(['schedule_response', 'schedule_response_notes', 'schedule_responded_at']);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn('property_terms');
        });
    }
};
