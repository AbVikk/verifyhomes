<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occupancy_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupancy_id')->constrained('occupancies')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->string('category');
            $table->text('description');
            $table->string('status')->default('open');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['occupancy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupancy_complaints');
    }
};
