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
        Schema::create('inspection_request_status_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('inspection_request_id');
            $table->string('from_status', 25)->nullable();
            $table->string('to_status', 25);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('inspection_request_id', 'irsh_request_fk')
                ->references('id')
                ->on('inspection_requests')
                ->cascadeOnDelete();

            $table->foreign('changed_by', 'irsh_changed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_request_status_histories');
    }
};