<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Operations\HealthReport;
use Illuminate\Http\JsonResponse;

/**
 * Load-balancer readiness probe. Reports only ready / not ready; which
 * dependency failed is visible to administrators on the operations page.
 */
class ReadinessController extends Controller
{
    public function __invoke(HealthReport $health): JsonResponse
    {
        $ready = $health->isReady();

        return response()->json(['status' => $ready ? 'ready' : 'unavailable'], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
