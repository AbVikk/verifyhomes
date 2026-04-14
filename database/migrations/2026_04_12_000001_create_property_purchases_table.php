<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('purchase_type', 30);
            $table->string('status', 30)->default('confirmed');
            $table->unsignedInteger('units')->default(1);
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->string('currency', 10)->default('NGN');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            $table->unique('payment_transaction_id');
            $table->index(['property_id', 'buyer_id']);
            $table->index(['status', 'purchase_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_purchases');
    }
};
