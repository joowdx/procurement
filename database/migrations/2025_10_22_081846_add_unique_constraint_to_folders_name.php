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
        Schema::table('folders', function (Blueprint $table) {
            // Add unique constraint on name and parent_id combination
            // This ensures no duplicate folder names within the same parent
            $table->unique(['name', 'parent_id'], 'folders_name_parent_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropUnique('folders_name_parent_id_unique');
        });
    }
};
