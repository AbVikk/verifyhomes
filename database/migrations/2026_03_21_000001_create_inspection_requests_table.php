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
        Schema::create('inspection_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 25)->default('requested')->index();
            $table->date('preferred_date')->nullable()->index();
            $table->string('preferred_time_note', 125)->nullable();
            $table->text('message')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('created_by_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['property_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_requests');
    }
};
