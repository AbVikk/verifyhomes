<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->string('event_key')->nullable()->after('category');
            $table->unique(['user_id', 'event_key'], 'user_notifications_user_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropUnique('user_notifications_user_event_unique');
            $table->dropColumn('event_key');
        });
    }
};
