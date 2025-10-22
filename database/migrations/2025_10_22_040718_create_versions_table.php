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
        Schema::create('versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('file_id');
            $table->integer('number');
            $table->string('hash'); // SHA-256
            $table->string('disk'); // local, s3, external
            $table->text('path');
            $table->bigInteger('size');
            $table->unsignedBigInteger('downloads')->default(0);
            $table->json('metadata')->nullable();
            $table->ulid('created_by');
            $table->timestamp('created_at');

            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->index('file_id');
            $table->index('hash');
            $table->index('created_by');
            $table->unique(['file_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
