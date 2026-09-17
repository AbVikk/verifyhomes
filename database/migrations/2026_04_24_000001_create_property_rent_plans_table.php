<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_rent_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('period_months');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['property_id', 'period_months']);
        });
    }

    public function down(): void { Schema::dropIfExists('property_rent_plans'); }
};
