<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BallotComponent extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    #[\Override]
    public function toArray(\Illuminate\Http\Request $request)
    {
        return parent::toArray($request);
    }
}
