<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Deployment;
use App\Log;
use Faker\Generator as Faker;

$factory->define(Log::class, function (Faker $faker) {
    return [
        'deployment_id' => factory(Deployment::class),
        'log' => $faker->sentence
    ];
});
