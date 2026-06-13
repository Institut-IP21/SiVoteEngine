<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PersonalizationFull;
use App\Models\Personalization;

use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function updatePersonalization(Request $request): JsonResponse|JsonResource
    {
        $params = $request->all();
        $settings = [
            // Must be a real http(s) URL — this value is rendered on voter-facing
            // ballot pages, so reject javascript:/data: and other scheme injection.
            'photo_url' => ['required', 'url', 'regex:/^https?:\/\//i'],
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $owner = $this->getOwner();

        $personalization = Personalization::updateOrCreate(
            [
                'owner' => $owner
            ],
            [
                'photo_url' => $params['photo_url']
            ]
        );

        return new PersonalizationFull($personalization);
    }
}
