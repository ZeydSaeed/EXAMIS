<?php

namespace App\Http\Middleware;

use App\Enums\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response, error pages included: it is
 * global middleware, outside the router.
 *
 * Scripts are restricted to same-origin files and nonce-carrying tags.
 * Styles allow 'unsafe-inline' because Radix UI and toast components set
 * inline styles at runtime; this is a documented, lower-risk exception
 * (docs/security/THREAT-MODEL.md). Responses that set their own CSP (e.g.
 * document downloads) keep it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }

        if (config('examis.security.content_security_policy') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if (config('examis.security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age='.(int) config('examis.security.hsts_max_age').'; includeSubDomains');
        }

        if (Portal::forRequest($request) === Portal::Staff) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
            $headers->set('Referrer-Policy', 'same-origin');
        }

        // Only requests that went through the session can have a user. Asking
        // the guard elsewhere (a 404, the health checks) could sign a user in
        // from the remember cookie outside any session.
        if ($request->hasSession() && $request->user() !== null) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();
        $devServer = $this->viteDevServerOrigin();
        $devSocket = $devServer !== null ? preg_replace('#^http#', 'ws', $devServer) : null;

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => array_filter(["'self'", "'nonce-{$nonce}'", $devServer]),
            'style-src' => array_filter(["'self'", "'unsafe-inline'", 'https://fonts.bunny.net', $devServer]),
            'font-src' => ["'self'", 'https://fonts.bunny.net', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => array_filter(["'self'", $devServer, $devSocket]),
            'frame-src' => ["'self'"],
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }

    private function viteDevServerOrigin(): ?string
    {
        $hotFile = Vite::hotFile();

        if (app()->isProduction() || ! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $host = str_contains($parts['host'], ':') && ! str_starts_with($parts['host'], '[') ? '['.$parts['host'].']' : $parts['host'];

        return $parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
