<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('invited_at')->nullable()->after('must_change_password');
            $table->timestamp('activated_at')->nullable()->after('invited_at');
            $table->timestamp('suspended_at')->nullable()->after('activated_at');
            $table->timestamp('last_login_at')->nullable()->after('suspended_at');
            $table->string('activation_token_hash')->nullable()->after('last_login_at');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['must_change_password', 'invited_at', 'activated_at', 'suspended_at', 'last_login_at', 'activation_token_hash', 'activation_expires_at']);
        });
    }
};
