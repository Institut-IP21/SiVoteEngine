<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BallotComplete extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    #[\Override]
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'election_id' => $this->election_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_secret' => $this->is_secret,
            'title' => $this->title,
            'active' => $this->active,
            'votes_count' => $this->votes_count,
            'electorate_size' => $this->electorate_size,
            'quorum_met' => $this->quorum_met,
            'cast_votes' => $this->anonymousCastVotes(),
            'finished' => $this->finished,
            'description' => $this->description,
            'email_subject' => $this->email_subject,
            'email_template' => $this->email_template,
            'quorum' => $this->quorum,
            'components' => BallotComponent::collection($this->components)->keyBy('id')
        ];
    }

    /**
     * Secret ballot: only the submitted values, no id/code, ballot_id or
     * timestamps, sorted by content so order carries no information.
     *
     * @return array<int, array<mixed>>
     */
    private function anonymousCastVotes(): array
    {
        $rows = array_map(fn ($vote): array => $vote->values ?? [], $this->cast_votes);
        usort($rows, fn (array $a, array $b): int => strcmp((string) json_encode($a), (string) json_encode($b)));

        return $rows;
    }
}
