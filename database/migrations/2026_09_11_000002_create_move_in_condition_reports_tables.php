<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('move_in_condition_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('occupancy_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('tenant_confirmed_at')->nullable();
            $table->text('tenant_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('move_in_condition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('move_in_condition_report_id')->constrained()->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('item_key', 80);
            $table->string('label', 120);
            $table->string('rating', 30)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->unique(['move_in_condition_report_id', 'item_key'], 'move_in_report_item_unique');
        });

        Schema::create('move_in_condition_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('move_in_condition_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('move_in_condition_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('move_in_condition_photos');
        Schema::dropIfExists('move_in_condition_items');
        Schema::dropIfExists('move_in_condition_reports');
    }
};
