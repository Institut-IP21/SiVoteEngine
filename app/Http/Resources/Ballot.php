<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Models\Ballot as BallotModel;
use App\Models\ActiveSessionVoter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class Ballot extends JsonResource
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
        $preview_url = $this->mode === BallotModel::MODE_SESSION ? URL::temporarySignedRoute(
            'ballot.session',
            now()->addMinutes(15),
            ['election' => $this->election_id, 'ballot' => $this->id]
        ) : URL::temporarySignedRoute(
            'ballot.preview',
            now()->addMinutes(15),
            ['election' => $this->election_id, 'ballot' => $this->id]
        );

        $resource = [
            'id' => $this->id,
            'election_id' => $this->election_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_secret' => $this->is_secret,
            'title' => $this->title,
            'active' => $this->active,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'mode' => $this->mode,
            'votes_count' => $this->votes_count,
            'electorate_size' => $this->electorate_size,
            'quorum_met' => $this->quorum_met,
            'finished' => $this->finished,
            'description' => $this->description,
            'email_subject' => $this->email_subject,
            'email_template' => $this->email_template,
            'preview_url' => $preview_url,
            'quorum' => $this->quorum,
            'components' => BallotComponent::collection($this->components)->keyBy('id')
        ];

        if ($this->mode === BallotModel::MODE_SESSION) {
            $resource['active_voters'] = ActiveSessionVoter::where('ballot_id', $this->id)
                ->where('last_seen_at', '>=', now()->subSeconds(60))
                ->count();
        }

        return $resource;
    }
}
