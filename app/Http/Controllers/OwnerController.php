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
            // an inline style on the ballot, so nothing but #rrggbb may pass. /D anchors
            // $ to the true end (no trailing newline).
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/D'],
        ];

        if ($errors = $this->findErrors($params, $settings)) {
            return $errors;
        }

        $owner = $this->getOwner();

        // Only touch brand_color when the caller actually sent it, so a logo-only
        // update (e.g. the current web_app flow) doesn't wipe a previously-set color.
        $values = ['photo_url' => $params['photo_url']];
        if (array_key_exists('brand_color', $params)) {
            $values['brand_color'] = $params['brand_color'];
        }

        $personalization = Personalization::updateOrCreate(['owner' => $owner], $values);

        return new PersonalizationFull($personalization);
    }
}
