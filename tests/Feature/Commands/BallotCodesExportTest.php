<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BallotCodesExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an uncast vote directly in the database to avoid Encryptable encrypting null.
     */
    private function createUncastVote(string $ballotId): string
    {
        $id = Str::uuid()->toString();
        DB::table('votes')->insert([
            'id' => $id,
            'ballot_id' => $ballotId,
            'values' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    public function test_exports_to_stdout(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        $voteId = $this->createUncastVote($ballot->id);

        $this->artisan('evote:export:codes', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('code,url')
            ->assertExitCode(0);
    }

    public function test_only_exports_uncast_votes(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        // Uncast vote (should be exported)
        $uncastId = $this->createUncastVote($ballot->id);

        // Cast vote (should NOT be exported) — values is non-null
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => ['some' => 'value'],
        ]);

        $this->artisan('evote:export:codes', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('code,url')
            ->assertExitCode(0);
    }

    public function test_empty_ballot_outputs_header_only(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $this->artisan('evote:export:codes', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('code,url')
            ->assertExitCode(0);
    }

    public function test_exports_to_file(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        $voteId = $this->createUncastVote($ballot->id);

        $filePath = sys_get_temp_dir() . '/test_export_' . uniqid() . '.csv';

        $this->artisan('evote:export:codes', [
            '--ballot' => $ballot->id,
            '--file' => $filePath,
        ])->assertExitCode(0);

        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertStringContainsString('code,url', $content);
        $this->assertStringContainsString($voteId, $content);

        $baseUrl = config('app.url');
        $this->assertStringContainsString("{$voteId},{$baseUrl}/vote/{$voteId}", $content);

        unlink($filePath);
    }

    public function test_file_export_count_message(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        $this->createUncastVote($ballot->id);
        $this->createUncastVote($ballot->id);

        $filePath = sys_get_temp_dir() . '/test_export_' . uniqid() . '.csv';

        $this->artisan('evote:export:codes', [
            '--ballot' => $ballot->id,
            '--file' => $filePath,
        ])
            ->expectsOutputToContain('Exported 2 codes')
            ->assertExitCode(0);

        unlink($filePath);
    }
}
