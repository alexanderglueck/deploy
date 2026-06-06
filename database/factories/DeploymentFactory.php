<?php

namespace Database\Factories;

use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeploymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Deployment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'event' => $this->faker->randomElement(['push']),
            'ref' => 'refs/heads/master',
            'repository' => 'jondoe/deploy',
            'project_id' => Project::factory(),
            'actions' => '#',
            'received_at' => null,
            'processed_at' => null,
            'deployed_at' => null,
            'canceled_at' => null,
        ];
    }
}
