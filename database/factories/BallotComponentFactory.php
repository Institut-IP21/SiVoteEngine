<?php

namespace Database\Factories;

use App\Models\BallotComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class BallotComponentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BallotComponent::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(1),
            'type' => 'FirstPassThePost',
            'version' => 'v1',
            'options' => [$this->faker->name(), $this->faker->name(), $this->faker->name()],
        ];
    }
}
