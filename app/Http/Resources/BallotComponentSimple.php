<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BallotComponent extends JsonResource
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
            'title' => $this->id,
            'description' => $this->id,
            'type' => $this->id,
            'version' => $this->id,
            'options' => $this->id,
            'id' => $this->id,
        ];
    }
}
