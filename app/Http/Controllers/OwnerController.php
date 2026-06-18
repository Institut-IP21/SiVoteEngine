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
            // Optional ballot accent color. Strict 6-digit hex only — it is emitted into
            // an inline style on the ballot, so nothing but #rrggbb may pass.
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
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
                'photo_url' => $params['photo_url'],
                'brand_color' => $params['brand_color'] ?? null,
            ]
        );

        return new PersonalizationFull($personalization);
    }
}
