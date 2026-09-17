<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenancy_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('occupancy_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('awaiting_tenant')->index();
            $table->json('agreement_snapshot');
            $table->timestamp('tenant_accepted_at')->nullable();
            $table->timestamp('landlord_accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancy_agreements');
    }
};
