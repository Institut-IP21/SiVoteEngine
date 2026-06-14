<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Tests\TestCase;

/**
 * Ballot-secrecy invariant for the HasUuidV4 trait.
 *
 * Vote ids MUST be *random* v4 UUIDs. If someone ever swaps the trait for
 * Laravel's native ordered HasUuids (time/sequence based), these tests fail —
 * because ordered ids leak the chronological order votes were cast in, which
 * would break secret-ballot guarantees. This is regression armor for that.
 */
class VoteSecrecyTest extends TestCase
{
    use RefreshDatabase;

    public function test_vote_id_is_a_valid_v4_uuid(): void
    {
        $ballot = Ballot::factory()->create(['election_id' => Election::factory()->create()->id]);

        // Create WITHOUT an explicit id so the trait generates it.
        $vote = Vote::create(['ballot_id' => $ballot->id]);

        $this->assertTrue(RamseyUuid::isValid($vote->id));
        $this->assertSame(4, RamseyUuid::fromString($vote->id)->getFields()->getVersion());
    }

    public function test_generated_vote_ids_are_not_ordered_by_creation(): void
    {
        $ballot = Ballot::factory()->create(['election_id' => Election::factory()->create()->id]);

        $idsInCreationOrder = [];
        for ($i = 0; $i < 50; $i++) {
            $idsInCreationOrder[] = Vote::create(['ballot_id' => $ballot->id])->id;
        }

        $sorted = $idsInCreationOrder;
        sort($sorted);

        // A time/sequence-ordered UUID would make the lexically-sorted list equal
        // the creation order. Random v4 ids make that astronomically unlikely.
        $this->assertNotSame(
            $idsInCreationOrder,
            $sorted,
            'Vote ids sort into creation order — the id scheme is leaking cast order.'
        );
    }

    public function test_explicitly_supplied_id_is_preserved(): void
    {
        // `id` is not fillable, so a pre-set key must be assigned on the instance
        // (this is the path the factory uses). The trait's empty() guard must then
        // leave it untouched rather than overwrite it.
        $ballot = Ballot::factory()->create(['election_id' => Election::factory()->create()->id]);
        $fixed = RamseyUuid::uuid4()->toString();

        $vote = new Vote();
        $vote->id = $fixed;
        $vote->ballot_id = $ballot->id;
        $vote->save();

        $this->assertSame($fixed, $vote->fresh()->id);
    }

    public function test_trait_also_governs_ballot_ids(): void
    {
        // The same secrecy-critical trait backs Ballot. Force the trait to mint the
        // id (id => null) instead of the factory's Faker default, then confirm v4.
        $ballot = Ballot::factory()->create([
            'id' => null,
            'election_id' => Election::factory()->create()->id,
        ]);

        $this->assertSame(4, RamseyUuid::fromString($ballot->id)->getFields()->getVersion());
    }
}
