<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable()->after('closed_at');
            $table->foreignId('escalated_by')->nullable()->after('escalated_at')->constrained('users')->nullOnDelete();
            $table->string('escalation_category', 50)->nullable()->after('escalated_by');
            $table->text('escalation_note')->nullable()->after('escalation_category');
            $table->index('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropIndex(['escalated_at']);
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn(['escalated_at', 'escalation_category', 'escalation_note']);
        });
    }
};
