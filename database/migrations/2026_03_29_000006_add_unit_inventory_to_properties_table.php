<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedInteger('total_units')
                ->default(1)
                ->after('service_charge');
            $table->unsignedInteger('occupied_units')
                ->default(0)
                ->after('total_units');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'total_units',
                'occupied_units',
            ]);
        });
    }
};
