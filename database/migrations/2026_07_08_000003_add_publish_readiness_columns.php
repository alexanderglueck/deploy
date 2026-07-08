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
        Schema::table('workflows', function (Blueprint $table) {
            // Which pushed branch triggers this workflow: null = the
            // repository's default branch, '*' = any branch, otherwise an
            // exact branch name.
            $table->string('branch')->nullable()->default(null)->after('event');
        });

        Schema::table('deployments', function (Blueprint $table) {
            // The repository's default branch as reported by the webhook
            // payload, used for branch matching.
            $table->string('default_branch')->nullable()->default(null)->after('ref');

            // Set when this deployment was created by the retry button.
            $table->unsignedBigInteger('retry_of_id')->nullable()->default(null)->after('rollback_of_id');
            $table->foreign('retry_of_id')->references('id')->on('deployments')->nullOnDelete();

            // The user who triggered a manual deploy / rollback / retry
            // (null for webhook-triggered deployments).
            $table->unsignedBigInteger('triggered_by')->nullable()->default(null)->after('retry_of_id');
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('branch');
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->dropForeign(['retry_of_id']);
            $table->dropForeign(['triggered_by']);
            $table->dropColumn(['default_branch', 'retry_of_id', 'triggered_by']);
        });
    }
};
