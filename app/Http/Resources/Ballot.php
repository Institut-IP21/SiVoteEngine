<?php

namespace App\Http\Resources;

use App\Models\Ballot as BallotModel;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\URL;

class Ballot extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
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
            'mode' => $this->mode,
            'votes_count' => $this->votes_count,
            'finished' => $this->finished,
            'description' => $this->description,
            'email_subject' => $this->email_subject,
            'email_template' => $this->email_template,
            'preview_url' => $preview_url,
            'components' => BallotComponent::collection($this->components)->keyBy('id')
        ];

        if ($this->mode === BallotModel::MODE_SESSION) {
            list($cursor, $keys) = Redis::scan(0, 'MATCH', "*:active-voters:*", 'COUNT', 10000);
            $resource['active_voters'] = count($keys);
        }
        return $resource;
    }
}
