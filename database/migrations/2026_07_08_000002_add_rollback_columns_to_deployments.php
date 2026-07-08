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
        Schema::table('deployments', function (Blueprint $table) {
            // The SHA-tagged image a docker_deploy step produced (app:sha).
            // Enables instant rollback by retagging — as long as the image
            // still exists locally (the scheduler prunes old ones).
            $table->string('image')->nullable()->default(null)->after('commit_sha');
            $table->timestamp('image_available_at')->nullable()->default(null)->after('image');
            $table->timestamp('image_checked_at')->nullable()->default(null)->after('image_available_at');

            // Set when this deployment was created by the rollback button.
            $table->unsignedBigInteger('rollback_of_id')->nullable()->default(null)->after('image_checked_at');

            $table->foreign('rollback_of_id')
                ->references('id')
                ->on('deployments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropForeign(['rollback_of_id']);
            $table->dropColumn(['image', 'image_available_at', 'image_checked_at', 'rollback_of_id']);
        });
    }
};
