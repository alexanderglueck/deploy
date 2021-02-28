<?php

namespace Database\Factories;

use App\Project;
use App\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'deploy_endpoint' => $this->faker->unique()->md5,
            'team_id' => Team::factory()
        ];
    }
}
