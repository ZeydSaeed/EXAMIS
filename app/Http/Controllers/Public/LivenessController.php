<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Liveness probe: PHP answers. Replaces Laravel's /up page, which loads a
 * third-party script and shows the render time. Dependencies are checked by
 * the readiness probe.
 */
class LivenessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'up'])
            ->header('Cache-Control', 'no-store');
    }
}
