<?php

namespace Database\Factories;

use App\Team;
use App\TeamMembership;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamMembershipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TeamMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory()
        ];
    }
}
