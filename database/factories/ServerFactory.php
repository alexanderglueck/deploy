<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Server;
use App\Team;
use Faker\Generator as Faker;

$factory->define(Server::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'user' => $faker->userName,
        'ip' => $faker->ipv4,
        'team_id' => factory(Team::class)
    ];
});
