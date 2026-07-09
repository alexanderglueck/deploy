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
        // Audit trail for Docker dashboard lifecycle actions: who
        // started/stopped/restarted which container, when, and how it went.
        // Also the natural home for job status once actions become queued
        // and broadcast (M5 realtime).
        Schema::create('container_actions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('container');
            $table->string('action');
            $table->boolean('successful');
            $table->text('output')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_actions');
    }
};
