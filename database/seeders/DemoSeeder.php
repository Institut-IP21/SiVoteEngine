<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Dev demo data — engine side. Reads the manifest written by web_app's DemoSeeder
 * (the orchestrator that owns the spec + every shared UUID) and builds the matching
 * elections, ballots, ballot components, and votes in the engine database.
 *
 * Run AFTER web_app's DemoSeeder (seed-demo.sh enforces the order). The apps are
 * linked only by the team UUID = `owner`.
 *
 * Ballot state → stored columns:
 *   empty  : active=false, finished=false, no components
 *   ready  : active=false, finished=false, has components
 *   open   : active=true,  finished=false
 *   closed : active=false, finished=true   (results_sent lives in web_app)
 */
class DemoSeeder extends Seeder
{
    private const MANIFEST = '/tmp/evote-demo-manifest.json';

    public function run(): void
    {
        if (! File::exists(self::MANIFEST)) {
            $this->command->error('Manifest ' . self::MANIFEST . ' not found — run web_app DemoSeeder first.');

            return;
        }

        /** @var array{team_uuid:string,elections:array<int,array<string,mixed>>} $manifest */
        $manifest = json_decode((string) File::get(self::MANIFEST), true);
        $owner = (string) $manifest['team_uuid'];

        foreach ($manifest['elections'] as $electionSpec) {
            Election::factory()->create([
                'id' => $electionSpec['id'],
                'owner' => $owner,
                'title' => $electionSpec['title'],
            ]);

            foreach ($electionSpec['ballots'] as $ballotSpec) {
                $this->seedBallot((string) $electionSpec['id'], $ballotSpec);
            }
        }

        $this->command->info('Engine demo data seeded for team ' . $owner . '.');
    }

    /**
     * @param  array<string,mixed>  $spec
     */
    private function seedBallot(string $electionId, array $spec): void
    {
        $state = (string) $spec['state'];

        // Realistic custody timestamps so the dashboard shows dates.
        //   open   : opened a few days ago, still running   (closed_at null)
        //   closed : opened further back, closed more recently
        //   empty / ready : never opened                    (both null)
        $openedAt = match ($state) {
            'open' => now()->subDays(3),
            'closed' => now()->subDays(10),
            default => null,
        };
        $closedAt = $state === 'closed' ? now()->subDays(2) : null;

        $ballot = Ballot::factory()->create([
            'id' => $spec['id'],
            'election_id' => $electionId,
            'title' => $spec['title'],
            'active' => $state === 'open',
            'finished' => $state === 'closed',
            'is_secret' => true,
            'quorum' => $spec['quorum'],
        ]);

        // opened_at/closed_at are intentionally NOT fillable (set only on transition),
        // so assign them directly here to give the demo realistic custody dates.
        $ballot->opened_at = $openedAt;
        $ballot->closed_at = $closedAt;
        $ballot->save();

        /** @var array<int,array<string,mixed>> $components */
        $components = $spec['components'] ?? [];
        $created = [];
        foreach (array_values($components) as $order => $componentSpec) {
            $created[] = $this->seedComponent($ballot->id, $order, $componentSpec);
        }

        $this->seedVotes(
            $ballot,
            $created,
            (int) ($spec['electorate'] ?? 0),
            (int) ($spec['turnout'] ?? 0),
        );
    }

    /**
     * @param  array<string,mixed>  $spec
     */
    private function seedComponent(string $ballotId, int $order, array $spec): BallotComponent
    {
        $type = (string) $spec['type'];
        $options = $this->optionsFor($type, $spec);

        return BallotComponent::factory()->create([
            'ballot_id' => $ballotId,
            'order' => $order,
            'type' => $type,
            'title' => $spec['title'],
            'options' => $options,
            'settings' => $spec['settings'] ?? null,
        ]);
    }

    /**
     * Issued-but-uncast codes (electorate − turnout) get a NULL `values`; the rest
     * get a generated selection per component so results render with real spreads.
     *
     * @param  array<int,BallotComponent>  $components
     */
    private function seedVotes(Ballot $ballot, array $components, int $electorate, int $turnout): void
    {
        if ($electorate <= 0) {
            return;
        }

        $cast = min($turnout, $electorate);

        for ($i = 0; $i < $cast; $i++) {
            $values = [];
            foreach ($components as $component) {
                $values[$component->id] = $this->answerFor($component, $i);
            }
            Vote::factory()->forBallot($ballot)->withValues($values)->create();
        }

        // Remaining issued codes were never used.
        $uncast = $electorate - $cast;
        if ($uncast > 0) {
            Vote::factory()->forBallot($ballot)->count($uncast)->create();
        }
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return list<string>
     */
    private function optionsFor(string $type, array $spec): array
    {
        if (isset($spec['options']) && is_array($spec['options'])) {
            /** @var list<string> */
            return array_values($spec['options']);
        }

        // YesNo has fixed options.
        return ['yes', 'no'];
    }

    /**
     * Deterministic-but-varied answer for vote #$i on a component, shaped per type.
     *
     * @return string|list<string>
     */
    private function answerFor(BallotComponent $component, int $i): string|array
    {
        /** @var list<string> $options */
        $options = array_values($component->options);
        $count = count($options);

        switch ($component->type) {
            case 'YesNo':
                // ~2/3 "yes" so two_thirds thresholds land near the line.
                return $i % 3 === 0 ? 'no' : 'yes';

            case 'ApprovalVote':
                // Approve a rotating subset (always at least one).
                $picked = [];
                foreach ($options as $j => $opt) {
                    if (($i + $j) % 2 === 0) {
                        $picked[] = $opt;
                    }
                }

                return $picked === [] ? [$options[0]] : $picked;

            case 'RankedChoice':
                // A rotation of the full options list so first-preferences spread out.
                $offset = $count > 0 ? $i % $count : 0;

                return array_merge(array_slice($options, $offset), array_slice($options, 0, $offset));

            case 'FirstPastThePost':
            default:
                return $count > 0 ? $options[$i % $count] : '';
        }
    }
}
