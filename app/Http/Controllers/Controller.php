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

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $settings
     */
    public function findErrors(array $params, array $settings): \Illuminate\Http\JsonResponse|null
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

    /**
     * @param array<string, mixed> $extra
     */
    public function basicResponse(int $code = 200, array $extra = []): \Illuminate\Http\JsonResponse
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

    protected function getOwner(): string
    {
        // Routes calling getOwner() run behind the auth.api middleware, which
        // logs in an ApiUser, so Auth::user() is never null here.
        /** @var \App\Models\ApiUser $user */
        $user = Auth::user();
        return $user->owner;
    }
}
