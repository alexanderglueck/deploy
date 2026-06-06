<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use App\Support\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class WorkflowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Workflow::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'project_id' => Project::factory(),
            'server_id' => Server::factory(),
            'event' => Event::PUSH,
            'actions' => '#',
        ];
    }

    public function project()
    {
        return $this->state(function (array $attributes) {
            $teamAttribute['team_id'] = Project::find($attributes['project_id'])->team_id;

            return [
                'server_id' => Server::factory()->create($teamAttribute),
            ];
        });
    }
}
