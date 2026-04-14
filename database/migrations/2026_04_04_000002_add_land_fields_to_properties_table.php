<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('land_size', 12, 2)->nullable()->after('property_type');
            $table->string('land_size_unit', 20)->nullable()->after('land_size');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['land_size', 'land_size_unit']);
        });
    }
};
