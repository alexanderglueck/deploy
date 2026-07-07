<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->nullable()->unique();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedInteger('position');
            $table->string('type');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('workflows')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Schema::create('deployment_steps', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->nullable()->unique();
            $table->unsignedBigInteger('deployment_id');
            $table->unsignedInteger('position');
            $table->string('type');
            $table->json('config')->nullable();
            $table->string('status')->default('pending');
            $table->integer('exit_code')->nullable()->default(null);
            $table->longText('output')->nullable()->default(null);
            $table->timestamp('started_at')->nullable()->default(null);
            $table->timestamp('finished_at')->nullable()->default(null);
            $table->timestamps();

            $table->foreign('deployment_id')
                ->references('id')
                ->on('deployments')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        // Steps replace the free-form actions script.
        Schema::table('workflows', function (Blueprint $table) {
            $table->text('actions')->nullable()->default(null)->change();
        });

        // Turn each legacy actions script into a single inline_script step.
        DB::table('workflows')->whereNotNull('actions')->get()->each(function ($workflow) {
            $exists = DB::table('workflow_steps')->where('workflow_id', $workflow->id)->exists();

            if (! $exists && trim($workflow->actions) !== '') {
                DB::table('workflow_steps')->insert([
                    'ulid' => strtolower((string) Str::ulid()),
                    'workflow_id' => $workflow->id,
                    'position' => 1,
                    'type' => 'inline_script',
                    'config' => json_encode(['script' => $workflow->actions]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployment_steps');
        Schema::dropIfExists('workflow_steps');
    }
};
