<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Server;
use App\Team;
use Carbon\Carbon;
use Faker\Generator as Faker;

$factory->define(Server::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'user' => $faker->userName,
        'ip' => $faker->ipv4,
        'port' => 22,
        'setup_at' => Carbon::now(),
        'team_id' => factory(Team::class)
    ];
});
