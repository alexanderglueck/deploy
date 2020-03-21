<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Team;
use App\TeamMembership;
use App\User;
use Faker\Generator as Faker;

$factory->define(TeamMembership::class, function (Faker $faker) {
    return [
        'user_id' => factory(User::class),
        'team_id' => factory(Team::class)
    ];
});
