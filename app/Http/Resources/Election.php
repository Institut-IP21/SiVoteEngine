<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Election extends JsonResource
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
            'mode' => $this->mode,
            'title' => $this->title,
            'description' => $this->description,
            'owner' => $this->owner,
            'active' => $this->active,
            'level' => $this->level,
            'locked' => $this->locked,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abstainable' => $this->abstainable,
            'ballots' => Ballot::collection($this->ballots)->keyBy('id')
        ];
    }
}
