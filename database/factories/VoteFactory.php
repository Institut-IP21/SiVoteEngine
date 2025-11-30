<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Vote::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'id' => $this->faker->uuid,
            'ballot_id' => Ballot::factory(),
            'values' => null,
            'cast_by' => null,
        ];
    }

    /**
     * Associate the vote with a specific ballot.
     */
    public function forBallot(Ballot $ballot): static
    {
        return $this->state(fn () => ['ballot_id' => $ballot->id]);
    }

    /**
     * Set the vote values (component selections).
     */
    public function withValues(array $values): static
    {
        return $this->state(fn () => ['values' => $values]);
    }

    /**
     * Set the cast_by field (voter identifier for public ballots).
     */
    public function castBy(string $voter): static
    {
        return $this->state(fn () => ['cast_by' => $voter]);
    }
}
