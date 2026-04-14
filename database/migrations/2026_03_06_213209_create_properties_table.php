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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();

            $table->string('title', 180);
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();

            $table->string('property_type', 50)->index();
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('caution_fee', 12, 2)->nullable();
            $table->decimal('service_charge', 12, 2)->nullable();

            $table->unsignedInteger('bedrooms')->nullable();
            $table->unsignedInteger('bathrooms')->nullable();
            $table->unsignedInteger('toilets')->nullable();

            $table->string('state', 100)->default('Ondo')->index();
            $table->string('lga', 100)->index();
            $table->string('city', 100)->default('Akure')->index();
            $table->string('area', 125)->index();
            $table->string('street', 180)->nullable();
            $table->string('landmark', 180)->nullable();
            $table->string('address_text', 255)->nullable();

            $table->string('youtube_url', 255)->nullable();

            $table->boolean('is_verified')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();

            $table->string('status', 30)->default('pending_review')->index();

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('physically_verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};