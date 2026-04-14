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
        Schema::create('landlord_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name', 125)->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('address', 255)->nullable();

            $table->string('id_type', 50)->nullable();
            $table->string('id_number', 125)->nullable();
            $table->string('id_document_path', 255)->nullable();
            $table->string('selfie_path', 255)->nullable();

            $table->string('verification_status', 25)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_profiles');
    }
}; 