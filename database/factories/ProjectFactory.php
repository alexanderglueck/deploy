<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Project;
use Faker\Generator as Faker;

$factory->define(Project::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'deploy_endpoint' => $faker->unique()->md5,
        'team_id' => function () {
            return factory(\App\Team::class)->create();
        }
    ];
});
