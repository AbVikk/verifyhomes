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
        Schema::create('landlord_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landlord_profile_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 25)->nullable();
            $table->string('to_status', 25);
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_status_histories');
    }
};
