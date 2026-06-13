<?php

namespace Database\Factories;

use App\Models\BallotComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BallotComponent>
 */
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
            'id' => $this->faker->uuid(),
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(1),
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => [$this->faker->name(), $this->faker->name(), $this->faker->name()],
        ];
    }

    /**
     * Persist a YesNo component carrying a `pass_threshold` in its settings.
     *
     * @param int|float|string $threshold
     * @return $this
     */
    public function withPassThreshold($threshold): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'YesNo',
            'options' => ['yes', 'no'],
            'settings' => ['pass_threshold' => $threshold],
        ]);
    }
}
