<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No form or filter takes a NUL character, and PHP's date functions throw
 * on one (date_format, date, before, after), so such a request is refused
 * as malformed before validation instead of answering 500.
 */
class RejectNullBytes
{
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->input();
        $hasNullByte = false;

        array_walk_recursive($input, function (mixed $value) use (&$hasNullByte): void {
            $hasNullByte = $hasNullByte || (is_string($value) && str_contains($value, "\0"));
        });

        abort_if($hasNullByte, 400);

        return $next($request);
    }
}
