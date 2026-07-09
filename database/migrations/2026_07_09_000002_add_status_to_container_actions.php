<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actions run asynchronously now (M5), so the boolean outcome becomes
        // a lifecycle: queued → running → ok / failed.
        Schema::table('container_actions', function (Blueprint $table) {
            $table->string('status')->default('queued')->after('action');
        });

        DB::table('container_actions')->where('successful', true)->update(['status' => 'ok']);
        DB::table('container_actions')->where('successful', false)->update(['status' => 'failed']);

        Schema::table('container_actions', function (Blueprint $table) {
            $table->dropColumn('successful');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('container_actions', function (Blueprint $table) {
            $table->boolean('successful')->default(false)->after('action');
        });

        DB::table('container_actions')->where('status', 'ok')->update(['successful' => true]);

        Schema::table('container_actions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
