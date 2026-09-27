<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BallotService;
use Illuminate\Http\JsonResponse;

/**
 * Global, non-election-scoped statute/legal-reference text for every
 * registered ballot component type (see
 * local_docs/voting-components/statute-feature-spec.md). Static reference
 * data, identical for every organization — like `admin/stats` and
 * `owner/personalization`, this sits behind the shared-token `ApiAuth` (the
 * `api` middleware group) only, with no `scope.bindings`/`can:view,election`.
 */
class StatuteTextApiController extends Controller
{
    public function __construct(private readonly BallotService $ballotService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->ballotService->getStatuteText());
    }
}
