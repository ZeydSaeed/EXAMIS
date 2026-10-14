<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlates logs, audit events and queued jobs with the HTTP request.
 * An inbound X-Request-Id (from the load balancer) is reused when well formed.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $inbound = (string) $request->headers->get('X-Request-Id', '');
        $requestId = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $inbound) === 1 ? $inbound : (string) Str::uuid7();

        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
