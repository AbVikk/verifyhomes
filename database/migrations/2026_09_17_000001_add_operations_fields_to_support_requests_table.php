<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->string('priority', 20)->default('normal')->after('status')->index();
            $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('support_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('previous_value')->nullable();
            $table->string('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['support_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_request_events');

        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['priority', 'assigned_at']);
        });
    }
};
