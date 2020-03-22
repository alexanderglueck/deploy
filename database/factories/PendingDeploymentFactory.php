<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\PendingDeployment;
use App\Project;
use Faker\Generator as Faker;

$factory->define(PendingDeployment::class, function (Faker $faker) {
    return [
        'event' => $faker->randomElement(['push']),
        'ref' => 'refs/heads/master',
        'repository' => 'jondoe/deploy',
        'project_id' => factory(Project::class)
    ];
});
