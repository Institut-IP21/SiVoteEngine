<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
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
        return [
            'id' => $this->id,
            'election_id' => $this->election_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'title' => $this->title,
            'active' => $this->active,
            'votes_count' => $this->votes_count,
            'finished' => $this->finished,
            'description' => $this->description,
            'email_subject' => $this->email_subject,
            'email_template' => $this->email_template,
            'result_url' => URL::temporarySignedRoute(
                'ballot.result',
                now()->addMinutes(15),
                ['election' => $this->election_id, 'ballot' => $this->id]
            ),
            'preview_url' => URL::temporarySignedRoute(
                'ballot.preview',
                now()->addMinutes(15),
                ['election' => $this->election_id, 'ballot' => $this->id]
            ),
            'components' => BallotComponent::collection($this->components)->keyBy('id')
        ];
    }
}
