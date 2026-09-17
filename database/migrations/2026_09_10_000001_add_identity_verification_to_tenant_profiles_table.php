<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_profiles', function (Blueprint $table): void {
            $table->string('verification_status', 25)->default('not_submitted')->index();
            $table->string('id_type', 50)->nullable();
            $table->text('id_number')->nullable();
            $table->string('id_document_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'id_type', 'id_number', 'id_document_path', 'selfie_path', 'submitted_at', 'verified_at', 'rejection_reason', 'admin_notes']);
        });
    }
};
