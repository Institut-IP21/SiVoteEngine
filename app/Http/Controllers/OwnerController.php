<?php

namespace App\Http\Controllers;

use App\Http\Resources\PersonalizationFull;
use App\Models\Personalization;

use Illuminate\Http\Request;

class OwnerController extends Controller
{
    public function updatePersonalization(Request $request)
    {
        $params = $request->all();
        $settings = [
            'photo_url' => 'required|string',
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
