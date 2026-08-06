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
        Schema::table('projects', function (Blueprint $table) {
            // Git host for this project's repository. Empty means the
            // installation default (deploy.git_base). Needed because the clone
            // URL is built server-side from the repository name, so a project
            // hosted somewhere else than the rest of the fleet -- a GitLab
            // repository on an otherwise GitHub-based install -- has no way to
            // be cloned at all.
            $table->string('git_base')->nullable()->default(null)->after('default_branch');

            // Username paired with the token in the clone URL. Host-specific:
            // GitHub expects x-access-token, GitLab expects oauth2.
            $table->string('git_token_user')->nullable()->default(null)->after('git_base');

            // Encrypted at rest via the model cast, like webhook_secret.
            $table->text('git_token')->nullable()->default(null)->after('git_token_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['git_base', 'git_token_user', 'git_token']);
        });
    }
};
