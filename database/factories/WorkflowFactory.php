<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Project;
use App\Server;
use App\Workflow;
use Faker\Generator as Faker;

$factory->define(Workflow::class, function (Faker $faker, $attributes) {
    $teamAttribute = [];

    if (isset($attributes['project_id']) && ! isset($attributes['server_id'])) {
        // Create a server belonging to the projects team
        $teamAttribute['team_id'] = Project::find($attributes['project_id'])->team_id;
    }

    if (isset($attributes['server_id']) && ! isset($attributes['project_id'])) {
        // Create a project belonging to the servers team
        $teamAttribute['team_id'] = Server::find($attributes['server_id'])->team_id;
    }

    return [
        'project_id' => function () use ($teamAttribute) {
            return factory(Project::class)->create($teamAttribute)->id;
        },
        'server_id' => function () use ($teamAttribute) {
            return factory(Server::class)->create($teamAttribute)->id;
        },
        'event' => 1,
        'actions' => ''
    ];
});
