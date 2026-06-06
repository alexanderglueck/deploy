<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeploymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('event');
            $table->string('ref');
            $table->string('repository');
            $table->text('actions')->nullable()->default(null);
            $table->timestamp('received_at')->nullable()->default(null);
            $table->timestamp('processed_at')->nullable()->default(null);
            $table->timestamp('deployed_at')->nullable()->default(null);
            $table->timestamp('canceled_at')->nullable()->default(null);
            $table->timestamps();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deployments');
    }
}
