<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Server::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'type' => Server::TYPE_SSH,
            'user' => $this->faker->userName,
            'ip' => $this->faker->ipv4,
            'port' => 22,
            'setup_at' => Carbon::now(),
            'team_id' => Team::factory(),
        ];
    }

    public function local()
    {
        // user/port keep their column defaults; they are meaningless for
        // local servers but NOT NULL in the schema.
        return $this->state([
            'type' => Server::TYPE_LOCAL,
            'ip' => null,
        ]);
    }
}
