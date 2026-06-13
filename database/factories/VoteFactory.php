<?php

namespace Database\Factories;

use App\Models\Ballot;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
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
        // `values`/`cast_by` are Encryptable: the setter encrypts EVERY value,
        // so an explicit `null` default would store a non-null ciphertext and be
        // miscounted as a cast vote by Ballot::castVotes(). Omit them so an
        // unset value stays a genuine DB NULL (an issued-but-uncast code).
        return [
            'id' => $this->faker->uuid,
            'ballot_id' => Ballot::factory(),
        ];
    }

    /**
     * Associate the vote with a specific ballot.
     */
    public function forBallot(Ballot $ballot): static
    {
        return $this->state(fn (): array => ['ballot_id' => $ballot->id]);
    }

    /**
     * Set the vote values (component selections).
     */
    public function withValues(array $values): static
    {
        return $this->state(fn (): array => ['values' => $values]);
    }

    /**
     * Set the cast_by field (voter identifier for public ballots).
     */
    public function castBy(string $voter): static
    {
        return $this->state(fn (): array => ['cast_by' => $voter]);
    }
}
