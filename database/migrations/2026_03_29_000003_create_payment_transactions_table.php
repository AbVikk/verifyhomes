<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('payer_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('inspection_request_id')->nullable();
            $table->string('transaction_type', 50);
            $table->string('provider', 50)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 25)->default('pending');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('platform_fee_percentage', 5, 2);
            $table->decimal('platform_fee_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('payer_id', 'pt_payer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('property_id', 'pt_property_fk')
                ->references('id')
                ->on('properties')
                ->nullOnDelete();

            $table->foreign('inspection_request_id', 'pt_inspection_fk')
                ->references('id')
                ->on('inspection_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
