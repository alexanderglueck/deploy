<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use phpseclib3\Crypt\EC;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // GitHub repository ("owner/name") matched against incoming webhook
            // payloads. Optional: projects without it accept any repository.
            $table->string('repository')->nullable()->default(null)->after('name');

            // Per-project secret used to verify webhook signatures. Stored
            // encrypted (see the `encrypted` cast on the model).
            $table->text('webhook_secret')->nullable()->default(null)->after('deploy_endpoint');
        });

        Schema::table('servers', function (Blueprint $table) {
            // 'local' servers execute on the host the app runs on; 'ssh'
            // servers are remote machines reached with the per-server keypair.
            $table->string('type')->default('ssh')->after('name');
            $table->text('private_key')->nullable()->default(null)->after('setup_at');
            $table->text('public_key')->nullable()->default(null)->after('private_key');

            // Local servers have no address.
            $table->string('ip')->nullable()->default(null)->change();
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->string('commit_sha')->nullable()->default(null)->after('repository');
            $table->timestamp('failed_at')->nullable()->default(null)->after('deployed_at');
        });

        // Existing projects predate webhook signing; give each one a secret so
        // the new verification middleware can be pointed at them immediately.
        // Crypt::encryptString matches what the `encrypted` model cast expects.
        DB::table('projects')->whereNull('webhook_secret')->pluck('id')->each(function ($id) {
            DB::table('projects')->where('id', $id)->update([
                'webhook_secret' => Crypt::encryptString(Str::random(40)),
            ]);
        });

        // Existing servers used a single shared keypair (storage/app/temp_id_rsa),
        // which is gone. Give each its own key and clear setup_at so the UI asks
        // for the new public key to be installed before the next deployment.
        DB::table('servers')->whereNull('private_key')->pluck('id')->each(function ($id) {
            $key = EC::createKey('Ed25519');

            DB::table('servers')->where('id', $id)->update([
                'private_key' => Crypt::encryptString($key->toString('OpenSSH')),
                'public_key' => $key->getPublicKey()->toString('OpenSSH', ['comment' => 'deploy']),
                'setup_at' => null,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['repository', 'webhook_secret']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['type', 'private_key', 'public_key']);
        });

        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['commit_sha', 'failed_at']);
        });
    }
};
