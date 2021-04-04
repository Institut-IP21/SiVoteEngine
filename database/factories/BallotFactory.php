<?php

namespace Database\Factories;

use App\Models\Ballot;
use Illuminate\Database\Eloquent\Factories\Factory;

class BallotFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ballot::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'id' => $this->faker->uuid(),
            'election_id' => $this->faker->uuid(),
            'title' => $this->faker->name(),
            'active' => false,
            'description' => $this->faker->paragraph(1),
            'created_at' => $this->faker->dateTime(),
            'email_subject' => $this->faker->sentence(3),
            'email_template' => $this->faker->paragraph(3),
        ];
    }
}
