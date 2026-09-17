<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landlord_settlements')) {
            Schema::create('landlord_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->decimal('gross_amount_snapshot', 12, 2);
            $table->decimal('platform_fee_snapshot', 12, 2);
            $table->decimal('landlord_entitlement_snapshot', 12, 2);
            $table->decimal('payout_amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 20)->default('paid');
            $table->string('payout_reference')->nullable();
            $table->string('payout_method', 80)->nullable();
            $table->text('note')->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->string('masked_account_number', 12)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['transaction_id', 'status']);
            $table->index(['landlord_id', 'status']);
            });
        }

        if (! Schema::hasTable('landlord_settlement_events')) {
            Schema::create('landlord_settlement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('landlord_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 30);
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['landlord_settlement_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_settlement_events');
        Schema::dropIfExists('landlord_settlements');
    }
};
