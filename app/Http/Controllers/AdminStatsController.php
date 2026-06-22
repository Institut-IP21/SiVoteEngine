<?php

namespace App\Http\Controllers;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Read-only, cross-owner statistics for the web_app operator panel.
 *
 * Like the panel's other admin endpoints this is GLOBAL: it runs behind the
 * shared-token ApiAuth middleware (so an unauthenticated caller is still
 * rejected), but the Owner header is deliberately ignored — these counts span
 * every organizer, not just the header's owner.
 */
class AdminStatsController extends Controller
{
    /** Known election levels surfaced in the breakdown, always present (zero-filled). */
    private const LEVELS = [1, 2, 3];

    /**
     * Known ballot-component types surfaced in the breakdown, always present
     * (zero-filled). These are the engine's stored `type` values (the component
     * class names), not the snake_case wire aliases.
     */
    private const COMPONENT_TYPES = ['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote'];

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats' => [
                'elections_total' => Election::query()->count(),
                'elections_by_level' => $this->electionsByLevel(),
                'ballots_total' => Ballot::query()->count(),
                'ballots_active' => Ballot::query()->where('active', true)->count(),
                'ballots_finished' => Ballot::query()->where('finished', true)->count(),
                'questions_by_type' => $this->questionsByType(),
                'elections_by_month' => $this->electionsByMonth(),
            ],
        ]);
    }

    /**
     * Election counts grouped by integer level, keyed by the level as a string.
     * The three known levels are always present (zero-filled); a "0" key is added
     * only when level-0 rows actually exist.
     *
     * Keys are typed int|string because PHP coerces numeric-string array keys back
     * to int; json_encode emits them as string object keys ("1","2",...) regardless.
     *
     * @return array<int|string, int>
     */
    private function electionsByLevel(): array
    {
        $buckets = [];
        foreach (self::LEVELS as $level) {
            $buckets[(string) $level] = 0;
        }

        foreach (Election::query()->pluck('level') as $level) {
            $key = (string) (int) $level;
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
    }

    /**
     * Ballot-component counts grouped by type, restricted to the four known types
     * and zero-filled so each key is always present.
     *
     * @return array<string, int>
     */
    private function questionsByType(): array
    {
        $buckets = [];
        foreach (self::COMPONENT_TYPES as $type) {
            $buckets[$type] = 0;
        }

        foreach (BallotComponent::query()->pluck('type') as $type) {
            $key = (string) $type;
            if (array_key_exists($key, $buckets)) {
                $buckets[$key]++;
            }
        }

        return $buckets;
    }

    /**
     * Election creations per calendar month over the last 12 months (current month
     * inclusive), keyed YYYY-MM, zero-filled and ascending. Counted in PHP from a
     * plain created_at pluck — no raw column aliases (keeps larastan happy).
     *
     * @return list<array{month: string, count: int}>
     */
    private function electionsByMonth(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        // Seed the 12 buckets in ascending order so empty months still appear.
        $buckets = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $buckets[$cursor->format('Y-m')] = 0;
            $cursor->addMonth();
        }

        $createdAts = Election::query()
            ->where('created_at', '>=', $start)
            ->pluck('created_at');

        foreach ($createdAts as $createdAt) {
            if (! $createdAt instanceof Carbon) {
                continue;
            }
            $key = $createdAt->format('Y-m');
            if (array_key_exists($key, $buckets)) {
                $buckets[$key]++;
            }
        }

        $series = [];
        foreach ($buckets as $month => $count) {
            $series[] = ['month' => (string) $month, 'count' => $count];
        }

        return $series;
    }
}
