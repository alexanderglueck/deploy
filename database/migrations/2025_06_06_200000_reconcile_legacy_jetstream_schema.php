<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades a database that was created by the original Laravel 8 schema
 * (custom `teams` + `team_memberships`) to the Jetstream-with-teams schema.
 *
 * It is fully idempotent and a no-op on a fresh install (where the columns
 * already exist and `team_memberships` was never created), so the same
 * `php artisan migrate` works everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Bring the users table up to the Jetstream shape.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'current_team_id')) {
                $table->foreignId('current_team_id')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path', 2048)->nullable()->after('current_team_id');
            }
        });

        // 2. Bring the teams table up to the Jetstream shape.
        Schema::table('teams', function (Blueprint $table) {
            if (! Schema::hasColumn('teams', 'user_id')) {
                // Kept nullable so legacy teams without a clear owner don't break.
                $table->foreignId('user_id')->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('teams', 'personal_team')) {
                // Every legacy team was an auto-created personal team.
                $table->boolean('personal_team')->default(true)->after('name');
            }
        });

        // 3. Migrate the legacy team_memberships pivot into Jetstream's model.
        if (Schema::hasTable('team_memberships')) {
            $memberships = DB::table('team_memberships')->orderBy('id')->get();

            // The earliest member of a team becomes its Jetstream owner.
            $ownerByTeam = [];
            foreach ($memberships as $membership) {
                $ownerByTeam[$membership->team_id] ??= $membership->user_id;
            }

            foreach ($ownerByTeam as $teamId => $userId) {
                DB::table('teams')->where('id', $teamId)->whereNull('user_id')
                    ->update(['user_id' => $userId]);

                // Point the owner's "current team" at the team they own.
                DB::table('users')->where('id', $userId)->whereNull('current_team_id')
                    ->update(['current_team_id' => $teamId]);
            }

            // Non-owner members become team_user rows (owners live on teams.user_id).
            foreach ($memberships as $membership) {
                if ($membership->user_id === ($ownerByTeam[$membership->team_id] ?? null)) {
                    continue;
                }

                $alreadyLinked = DB::table('team_user')
                    ->where('team_id', $membership->team_id)
                    ->where('user_id', $membership->user_id)
                    ->exists();

                if (! $alreadyLinked) {
                    DB::table('team_user')->insert([
                        'team_id' => $membership->team_id,
                        'user_id' => $membership->user_id,
                        'role' => 'admin',
                        'created_at' => $membership->created_at ?? now(),
                        'updated_at' => $membership->updated_at ?? now(),
                    ]);
                }
            }

            Schema::dropIfExists('team_memberships');
        }

        // 4. Fallback: any team still without an owner (no membership rows at all)
        //    is assigned to the team's first project/server owner is unknowable, so
        //    leave user_id null — Jetstream tolerates it, and the team simply has no
        //    actions until reassigned.
    }

    public function down(): void
    {
        // This is a one-way data migration; we only reverse the added columns.
        if (Schema::hasColumn('teams', 'personal_team')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('personal_team');
            });
        }
        if (Schema::hasColumn('teams', 'user_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
        if (Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('profile_photo_path');
            });
        }
        if (Schema::hasColumn('users', 'current_team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('current_team_id');
            });
        }
    }
};
