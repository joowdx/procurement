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
        Schema::create('memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('workspace_id');
            $table->ulid('user_id');
            $table->string('role')->default('member');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->ulid('invited_by')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('workspace_id');
            $table->index('user_id');
            $table->index('invited_by');

            // Unique constraint
            $table->unique(['workspace_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
