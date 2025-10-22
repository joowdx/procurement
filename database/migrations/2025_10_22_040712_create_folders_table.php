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
        Schema::create('folders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('path');
            $table->integer('depth')->default(0);
            $table->integer('order')->default(0);
            $table->ulid('created_by');
            $table->ulid('updated_by');
            $table->ulid('deactivated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->timestamp('deactivated_at')->nullable();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('deactivated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');

            $table->index('parent_id');
            $table->index('path');
            $table->index('created_by');
            
            // Unique constraint: folder names must be unique within the same parent
            // Enforced in migration: 2025_10_22_081846_add_unique_constraint_to_folders_name.php
            // $table->unique(['name', 'parent_id'], 'folders_name_parent_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
