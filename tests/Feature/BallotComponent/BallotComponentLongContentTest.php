<?php

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Faker\Provider\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression for the 2026-10-11 prod 500: user text columns were
 * VARCHAR(255) with no max validation. Options are stored as JSON with non-ASCII escaped (`č` ->
 * `č`, 6 chars), so ~13 Slovenian candidate names overflowed MySQL's
 * column ("Data too long for column 'options'") and the builder could not
 * save the question. SQLite does not enforce VARCHAR length, hence the
 * explicit column-type assertion alongside the end-to-end save.
 */
class BallotComponentLongContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every user-entered text column the builder writes, none capped at 255.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function userTextColumns(): array
    {
        return [
            'component title' => ['ballot_components', 'title'],
            'component description' => ['ballot_components', 'description'],
            'component options' => ['ballot_components', 'options'],
            'ballot title' => ['ballots', 'title'],
            'ballot email subject' => ['ballots', 'email_subject'],
            'ballot result email subject' => ['ballots', 'result_email_subject'],
            'election title' => ['elections', 'title'],
        ];
    }

    #[DataProvider('userTextColumns')]
    public function test_user_text_column_is_not_length_capped(string $table, string $column): void
    {
        $this->assertSame('text', Schema::getColumnType($table, $column));
    }

    public function test_long_candidate_list_with_diacritics_saves_intact(): void
    {
        $owner = Uuid::uuid();
        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);
        $e = Election::factory()->state(['owner' => $owner])
            ->has(Ballot::factory()->state(['active' => false]))
            ->create();
        $b = $e->ballots[0];

        $options = array_map(fn (int $i) => "Kandidatka Čžšćđ Bavdaž Feratović $i", range(1, 40));
        $description = trim(str_repeat('Vrstni red naj bo moški-ženska-moški-ženska. ', 20));

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Potrjevanje kandidatne liste',
            'description' => $description,
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => $options,
        ])->assertSuccessful();

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertGreaterThan(255, strlen((string) json_encode($options)));
        $this->assertSame($options, $component->options);
        $this->assertSame($description, $component->description);
    }
}
