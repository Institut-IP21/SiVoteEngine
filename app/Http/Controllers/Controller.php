<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function findErrors($params, $settings)
    {
        $validator = Validator::make($params, $settings);
        $messages = $validator->errors();

        if (!$messages->isEmpty()) {
            return $this->basicResponse(
                400,
                [
                    'error' => "Request invalid.",
                    'field_errors' => $messages
                ]
            );
        }

        return null;
    }

    public function basicResponse($code = 200, $extra = [])
    {
        $data = [
            'success' => $code == 200,
        ];
        $data = array_merge($data, $extra);

        return response()->json(
            $data,
            $code
        );
    }

    protected function getOwner()
    {
        return Auth::user()->owner;
    }
}
