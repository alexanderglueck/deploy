<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Deployment;
use App\Project;
use Faker\Generator as Faker;

$factory->define(Deployment::class, function (Faker $faker) {
    return [
        'event' => $faker->randomElement(['push']),
        'ref' => 'refs/heads/master',
        'repository' => 'jondoe/deploy',
        'project_id' => factory(Project::class),
        'actions' => '#',
        'received_at' => null,
        'processed_at' => null,
        'deployed_at' => null,
        'canceled_at' => null,
    ];
});
