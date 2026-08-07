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
        Schema::create('project_variables', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->nullable()->unique();
            $table->unsignedBigInteger('project_id');
            $table->string('key');
            // Encrypted at rest via the model cast, like the project's webhook
            // secret and git token: a variable is as likely to hold a password
            // as a hostname, and the API never gives it back either way.
            $table->text('value')->nullable();
            // Exported into every step's shell, and additionally passed to
            // `docker build` when this is set. Kept opt-in because build args
            // are recorded in the image's `docker history` -- fine for VITE_*
            // values that ship in the bundle anyway, wrong for a token.
            $table->boolean('build_arg')->default(false);
            // Replaced with [masked] in stored step output.
            $table->boolean('masked')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'key']);

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_variables');
    }
};
