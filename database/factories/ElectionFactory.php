<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Election::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'id' => $this->faker->uuid(),
            'title' => $this->faker->name(),
            'description' => $this->faker->paragraph(1),
            'owner' => $this->faker->uuid(),
            'created_at' => $this->faker->dateTime(),
            'updated_at' => null,
            'abstainable' => true,
        ];
    }
}
